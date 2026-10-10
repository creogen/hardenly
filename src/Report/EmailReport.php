<?php declare( strict_types=1 );

namespace Sitewarden\Report;

use Merkushin\Wpal\Service\Formatting;
use Merkushin\Wpal\Service\Mail;
use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\Service\SiteIdentity;
use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use Sitewarden\Scanner\Result;
use Sitewarden\Storage\ReportStore;
use Sitewarden\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The plain-text summary emailed after automatic scans.
 *
 * It lists the score, counts and the main problems; never file paths or other details from
 * disk, since email is less private than the dashboard.
 */
class EmailReport {
	/**
	 * Problems listed in the email at most.
	 */
	public const MAX_FINDINGS = 10;

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var ReportStore
	 */
	private $reports;

	/**
	 * @var Mail
	 */
	private $mail;

	/**
	 * @var Options
	 */
	private $options;

	/**
	 * @var Urls
	 */
	private $urls;

	/**
	 * @var SiteIdentity
	 */
	private $site;

	/**
	 * @var Formatting
	 */
	private $formatting;

	public function __construct( Settings $settings, ReportStore $reports ) {
		$this->settings   = $settings;
		$this->reports    = $reports;
		$this->mail       = ServiceFactory::create_mail();
		$this->options    = ServiceFactory::create_options();
		$this->urls       = ServiceFactory::create_urls();
		$this->site       = ServiceFactory::create_site_identity();
		$this->formatting = ServiceFactory::create_formatting();
	}

	/**
	 * Handles `sitewarden_scan_completed`: sends the summary when the settings call for it.
	 *
	 * @param mixed $report The finished report.
	 */
	public function maybe_send( $report ): void {
		if ( ! is_array( $report ) ) {
			return;
		}
		$history  = $this->reports->history();
		$previous = $history[1] ?? null;
		if ( ! self::should_send( $report, $previous, $this->settings->get() ) ) {
			return;
		}

		$to = (string) $this->settings->get()['email_to'];
		if ( '' === $to ) {
			$to = (string) $this->options->get_option( 'admin_email', '' );
		}
		if ( '' === $to ) {
			return;
		}

		$this->mail->wp_mail( $to, $this->subject( $report, $previous ), $this->body( $report, $previous ) );
	}

	/**
	 * Whether to email about a report.
	 *
	 * @param array<string, mixed>      $report   The finished report.
	 * @param array<string, mixed>|null $previous Summary of the scan before it, if any.
	 * @param array<string, mixed>      $settings Plugin settings.
	 */
	public static function should_send( array $report, ?array $previous, array $settings ): bool {
		if ( empty( $settings['email_enabled'] ) || 'schedule' !== ( $report['trigger'] ?? '' ) ) {
			return false;
		}
		if ( empty( $settings['email_only_changes'] ) || null === $previous ) {
			return true;
		}

		$dropped = (int) ( $report['score'] ?? 0 ) < (int) ( $previous['score'] ?? 0 );
		$new     = array_diff( Summary::serious_ids( $report ), (array) ( $previous['serious'] ?? [] ) );
		return $dropped || [] !== $new;
	}

	/**
	 * @param array<string, mixed>      $report   The finished report.
	 * @param array<string, mixed>|null $previous Summary of the scan before it.
	 */
	public function subject( array $report, ?array $previous ): string {
		return sprintf(
			/* translators: 1: site name, 2: security score, 3: change since the previous scan, e.g. " (-6)". */
			__( '[%1$s] Sitewarden security score: %2$d%3$s', 'sitewarden' ),
			(string) $this->formatting->wp_specialchars_decode( (string) $this->site->get_bloginfo( 'name' ), ENT_QUOTES ),
			(int) ( $report['score'] ?? 0 ),
			self::change( $report, $previous )
		);
	}

	/**
	 * @param array<string, mixed>      $report   The finished report.
	 * @param array<string, mixed>|null $previous Summary of the scan before it.
	 */
	public function body( array $report, ?array $previous ): string {
		$counts = (array) ( $report['counts'] ?? [] );
		$lines  = [
			/* translators: 1: security score, 2: change since the previous scan, e.g. " (-6)". */
			sprintf( __( 'Security score: %1$d of 100%2$s', 'sitewarden' ), (int) ( $report['score'] ?? 0 ), self::change( $report, $previous ) ),
			'',
			sprintf(
				/* translators: 1: critical, 2: high, 3: medium, 4: low problem counts. */
				__( 'Problems: %1$d critical, %2$d high, %3$d medium, %4$d low', 'sitewarden' ),
				(int) ( $counts['critical'] ?? 0 ),
				(int) ( $counts['high'] ?? 0 ),
				(int) ( $counts['medium'] ?? 0 ),
				(int) ( $counts['low'] ?? 0 )
			),
		];

		$problems = self::top_problems( $report );
		if ( [] !== $problems ) {
			$lines[] = '';
			$lines[] = __( 'Main problems:', 'sitewarden' );
			foreach ( $problems as $problem ) {
				$lines[] = sprintf( '- [%s] %s', strtoupper( (string) $problem['severity'] ), (string) $this->formatting->wp_strip_all_tags( (string) $problem['message'] ) );
			}
		}

		$lines[] = '';
		/* translators: %s: link to the Sitewarden dashboard. */
		$lines[] = sprintf( __( 'Details and how to fix them: %s', 'sitewarden' ), (string) $this->urls->admin_url( 'admin.php?page=sitewarden' ) );
		$lines[] = '';
		$lines[] = __( 'The score is an indicator, not a guarantee that the site is secure. You get this email because email reports are on in Sitewarden > Settings.', 'sitewarden' );

		return implode( "\n", $lines );
	}

	/**
	 * Problems not ignored, most severe first.
	 *
	 * @param array<string, mixed> $report Report.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function top_problems( array $report ): array {
		$problems = array_filter(
			(array) ( $report['results'] ?? [] ),
			static function ( $result ): bool {
				return is_array( $result ) && empty( $result['ignored'] )
					&& in_array( $result['status'] ?? '', [ Result::STATUS_FAIL, Result::STATUS_WARNING ], true );
			}
		);
		$order    = array_flip( Result::SEVERITIES );
		usort(
			$problems,
			static function ( array $a, array $b ) use ( $order ): int {
				return ( $order[ $a['severity'] ] ?? 99 ) <=> ( $order[ $b['severity'] ] ?? 99 );
			}
		);
		return array_slice( $problems, 0, self::MAX_FINDINGS );
	}

	/**
	 * " (+3)" or " (-6)" since the previous scan; empty without one or without change.
	 *
	 * @param array<string, mixed>      $report   Report.
	 * @param array<string, mixed>|null $previous Previous summary.
	 */
	private static function change( array $report, ?array $previous ): string {
		if ( null === $previous ) {
			return '';
		}
		$change = (int) ( $report['score'] ?? 0 ) - (int) ( $previous['score'] ?? 0 );
		return 0 === $change ? '' : sprintf( ' (%+d)', $change );
	}
}

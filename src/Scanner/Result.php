<?php declare( strict_types=1 );

namespace Hardenly\Scanner;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * One outcome of a check: a problem found, a passed check, or a check that could not run.
 *
 * Messages and details may contain file names and other data read from the site: they are
 * untrusted and must be escaped when shown.
 */
final class Result {
	public const STATUS_PASS    = 'pass';
	public const STATUS_WARNING = 'warning';
	public const STATUS_FAIL    = 'fail';
	public const STATUS_INFO    = 'info';
	public const STATUS_SKIPPED = 'skipped';

	public const STATUSES = [
		self::STATUS_PASS,
		self::STATUS_WARNING,
		self::STATUS_FAIL,
		self::STATUS_INFO,
		self::STATUS_SKIPPED,
	];

	public const SEVERITY_INFO     = 'info';
	public const SEVERITY_LOW      = 'low';
	public const SEVERITY_MEDIUM   = 'medium';
	public const SEVERITY_HIGH     = 'high';
	public const SEVERITY_CRITICAL = 'critical';

	/**
	 * Most severe first.
	 */
	public const SEVERITIES = [
		self::SEVERITY_CRITICAL,
		self::SEVERITY_HIGH,
		self::SEVERITY_MEDIUM,
		self::SEVERITY_LOW,
		self::SEVERITY_INFO,
	];

	/**
	 * At most this many details (file paths, plugin names) are kept per result.
	 */
	public const MAX_DETAILS = 500;

	/**
	 * Each detail is cut to this many characters.
	 */
	public const MAX_DETAIL_LENGTH = 500;

	/**
	 * @var string
	 */
	private $check_id;

	/**
	 * @var string
	 */
	private $status;

	/**
	 * @var string
	 */
	private $severity;

	/**
	 * Short summary of what was found, shown as the finding's title.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * How to fix it.
	 *
	 * @var string
	 */
	private $remediation;

	/**
	 * Why it matters.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * @var string[]
	 */
	private $details;

	/**
	 * Number of details before capping.
	 *
	 * @var int
	 */
	private $details_total;

	/**
	 * Tells apart several results of one check, e.g. one per file. Empty for most checks.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * @param string   $check_id    Check that produced the result.
	 * @param string   $status      One of the STATUS_* constants.
	 * @param string   $severity    One of the SEVERITY_* constants.
	 * @param string   $message     Short summary.
	 * @param string   $remediation How to fix it.
	 * @param string[] $details     File paths, versions and the like; capped at MAX_DETAILS.
	 * @param string   $description Why it matters.
	 * @param string   $key         Tells apart several results of one check.
	 * @param int|null $details_total Number of details before capping, when restoring a stored result.
	 *
	 * @throws InvalidArgumentException When the status or severity is unknown.
	 */
	public function __construct(
		string $check_id,
		string $status,
		string $severity,
		string $message,
		string $remediation = '',
		array $details = [],
		string $description = '',
		string $key = '',
		?int $details_total = null
	) {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			throw new InvalidArgumentException( 'Unknown result status: ' . $status ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output.
		}
		if ( ! in_array( $severity, self::SEVERITIES, true ) ) {
			throw new InvalidArgumentException( 'Unknown result severity: ' . $severity ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output.
		}

		$details = array_values( array_map( 'strval', $details ) );

		$this->check_id      = $check_id;
		$this->status        = $status;
		$this->severity      = $severity;
		$this->message       = $message;
		$this->remediation   = $remediation;
		$this->description   = $description;
		$this->key           = $key;
		$this->details_total = $details_total ?? count( $details );
		$this->details       = array_map(
			static function ( string $detail ): string {
				return self::cut( $detail );
			},
			array_slice( $details, 0, self::MAX_DETAILS )
		);
	}

	public static function pass( string $check_id, string $message ): self {
		return new self( $check_id, self::STATUS_PASS, self::SEVERITY_INFO, $message );
	}

	/**
	 * @param string   $check_id    Check that produced the result.
	 * @param string   $severity    One of the SEVERITY_* constants.
	 * @param string   $message     Short summary.
	 * @param string   $remediation How to fix it.
	 * @param string[] $details     File paths, versions and the like.
	 * @param string   $description Why it matters.
	 */
	public static function fail(
		string $check_id,
		string $severity,
		string $message,
		string $remediation = '',
		array $details = [],
		string $description = ''
	): self {
		return new self( $check_id, self::STATUS_FAIL, $severity, $message, $remediation, $details, $description );
	}

	/**
	 * A possible problem that needs a person to review it, such as a suspicious code pattern.
	 *
	 * @param string   $check_id    Check that produced the result.
	 * @param string   $severity    One of the SEVERITY_* constants.
	 * @param string   $message     Short summary.
	 * @param string   $remediation How to fix it.
	 * @param string[] $details     File paths, versions and the like.
	 * @param string   $description Why it matters.
	 */
	public static function warning(
		string $check_id,
		string $severity,
		string $message,
		string $remediation = '',
		array $details = [],
		string $description = ''
	): self {
		return new self( $check_id, self::STATUS_WARNING, $severity, $message, $remediation, $details, $description );
	}

	public static function info( string $check_id, string $message, string $description = '' ): self {
		return new self( $check_id, self::STATUS_INFO, self::SEVERITY_INFO, $message, '', [], $description );
	}

	public static function skipped( string $check_id, string $message ): self {
		return new self( $check_id, self::STATUS_SKIPPED, self::SEVERITY_INFO, $message );
	}

	/**
	 * Restores a result stored with to_array().
	 *
	 * @param array<string, mixed> $data Stored result.
	 *
	 * @throws InvalidArgumentException When the data is not a valid result.
	 */
	public static function from_array( array $data ): self {
		$details = isset( $data['details'] ) && is_array( $data['details'] ) ? $data['details'] : [];

		return new self(
			(string) ( $data['check_id'] ?? '' ),
			(string) ( $data['status'] ?? '' ),
			(string) ( $data['severity'] ?? '' ),
			(string) ( $data['message'] ?? '' ),
			(string) ( $data['remediation'] ?? '' ),
			$details,
			(string) ( $data['description'] ?? '' ),
			(string) ( $data['key'] ?? '' ),
			isset( $data['details_total'] ) ? (int) $data['details_total'] : null
		);
	}

	/**
	 * @return array{check_id: string, status: string, severity: string, message: string, remediation: string, description: string, details: string[], details_total: int, key: string}
	 */
	public function to_array(): array {
		return [
			'check_id'      => $this->check_id,
			'status'        => $this->status,
			'severity'      => $this->severity,
			'message'       => $this->message,
			'remediation'   => $this->remediation,
			'description'   => $this->description,
			'details'       => $this->details,
			'details_total' => $this->details_total,
			'key'           => $this->key,
		];
	}

	public function check_id(): string {
		return $this->check_id;
	}

	public function status(): string {
		return $this->status;
	}

	public function severity(): string {
		return $this->severity;
	}

	public function message(): string {
		return $this->message;
	}

	public function remediation(): string {
		return $this->remediation;
	}

	public function description(): string {
		return $this->description;
	}

	/**
	 * @return string[]
	 */
	public function details(): array {
		return $this->details;
	}

	public function details_total(): int {
		return $this->details_total;
	}

	public function key(): string {
		return $this->key;
	}

	/**
	 * Whether the result is a problem: a failure or a warning.
	 */
	public function is_problem(): bool {
		return self::STATUS_FAIL === $this->status || self::STATUS_WARNING === $this->status;
	}

	/**
	 * Shortens a detail, without splitting a multibyte character (WordPress polyfills mb_*).
	 */
	private static function cut( string $text ): string {
		if ( mb_strlen( $text ) <= self::MAX_DETAIL_LENGTH ) {
			return $text;
		}
		return mb_substr( $text, 0, self::MAX_DETAIL_LENGTH - 3 ) . '...';
	}
}

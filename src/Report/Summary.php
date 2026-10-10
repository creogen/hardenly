<?php declare( strict_types=1 );

namespace Hardenly\Report;

use InvalidArgumentException;
use Hardenly\Scanner\Result;
use Hardenly\Scanner\Score;

defined( 'ABSPATH' ) || exit;

/**
 * Score and counts of a report, leaving out ignored findings.
 *
 * Used when a scan finishes and again when a finding is ignored or shown again, so the
 * dashboard and email always agree with the list of ignored findings.
 */
class Summary {
	/**
	 * @var Score
	 */
	private $score;

	public function __construct( Score $score ) {
		$this->score = $score;
	}

	/**
	 * Id of a finding, as stored in the ignored findings: the check id, plus ":key" when set.
	 *
	 * @param array<string, mixed> $result Stored result.
	 */
	public static function finding_id( array $result ): string {
		$key = (string) ( $result['key'] ?? '' );
		return (string) ( $result['check_id'] ?? '' ) . ( '' === $key ? '' : ':' . $key );
	}

	/**
	 * The report with each result's id and ignored flag, and score and counts that leave out
	 * ignored findings.
	 *
	 * @param array<string, mixed> $report      Report with `results` and `checks`.
	 * @param string[]             $ignored_ids Ignored finding ids.
	 *
	 * @return array<string, mixed>
	 */
	public function apply( array $report, array $ignored_ids ): array {
		$ignored = array_flip( $ignored_ids );
		$groups  = [];
		foreach ( (array) ( $report['checks'] ?? [] ) as $id => $check ) {
			$groups[ (string) $id ] = (string) ( $check['group'] ?? '' );
		}

		$results = [];
		$active  = [];
		foreach ( (array) ( $report['results'] ?? [] ) as $data ) {
			if ( ! is_array( $data ) ) {
				continue;
			}
			try {
				$result = Result::from_array( $data );
			} catch ( InvalidArgumentException $e ) {
				continue;
			}
			$data['id']      = self::finding_id( $data );
			$data['ignored'] = $result->is_problem() && isset( $ignored[ $data['id'] ] );
			$results[]       = $data;
			if ( ! $data['ignored'] ) {
				$active[] = $result;
			}
		}

		$counts  = array_fill_keys( Result::SEVERITIES, 0 );
		$passed  = 0;
		$skipped = 0;
		foreach ( $active as $result ) {
			if ( $result->is_problem() ) {
				++$counts[ $result->severity() ];
			} elseif ( Result::STATUS_PASS === $result->status() ) {
				++$passed;
			} elseif ( Result::STATUS_SKIPPED === $result->status() ) {
				++$skipped;
			}
		}

		$report['results'] = $results;
		$report['score']   = $this->score->calculate( $active, $groups );
		$report['counts']  = $counts;
		$report['passed']  = $passed;
		$report['skipped'] = $skipped;
		$report['ignored'] = count( $results ) - count( $active );
		return $report;
	}

	/**
	 * Ids of the high and critical problems that are not ignored.
	 *
	 * @param array<string, mixed> $report Report after apply().
	 *
	 * @return string[]
	 */
	public static function serious_ids( array $report ): array {
		$ids = [];
		foreach ( (array) ( $report['results'] ?? [] ) as $result ) {
			if ( empty( $result['ignored'] )
				&& in_array( $result['status'] ?? '', [ Result::STATUS_FAIL, Result::STATUS_WARNING ], true )
				&& in_array( $result['severity'] ?? '', [ Result::SEVERITY_HIGH, Result::SEVERITY_CRITICAL ], true ) ) {
				$ids[] = self::finding_id( $result );
			}
		}
		return $ids;
	}
}

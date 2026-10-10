<?php declare( strict_types=1 );

namespace Hardenly\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * The security score: 100 minus penalties for problems, by severity.
 *
 * Penalties are summed per check group and each group's total is capped, so one group with
 * many findings cannot take the score to zero on its own. The score is an indicator, not a
 * guarantee.
 */
class Score {
	public const DEFAULT_WEIGHTS = [
		'severity'  => [
			Result::SEVERITY_CRITICAL => 25,
			Result::SEVERITY_HIGH     => 15,
			Result::SEVERITY_MEDIUM   => 8,
			Result::SEVERITY_LOW      => 3,
			Result::SEVERITY_INFO     => 0,
		],
		'group_cap' => 40,
	];

	/**
	 * Penalty per severity.
	 *
	 * @var array<string, int>
	 */
	private $severity_weights;

	/**
	 * @var int
	 */
	private $group_cap;

	/**
	 * @param array<string, mixed> $weights Overrides for DEFAULT_WEIGHTS; invalid values are ignored.
	 */
	public function __construct( array $weights = self::DEFAULT_WEIGHTS ) {
		$this->severity_weights = self::DEFAULT_WEIGHTS['severity'];
		if ( isset( $weights['severity'] ) && is_array( $weights['severity'] ) ) {
			foreach ( $this->severity_weights as $severity => $weight ) {
				if ( isset( $weights['severity'][ $severity ] ) && is_numeric( $weights['severity'][ $severity ] ) ) {
					$this->severity_weights[ $severity ] = max( 0, (int) $weights['severity'][ $severity ] );
				}
			}
		}

		$this->group_cap = self::DEFAULT_WEIGHTS['group_cap'];
		if ( isset( $weights['group_cap'] ) && is_numeric( $weights['group_cap'] ) ) {
			$this->group_cap = max( 0, (int) $weights['group_cap'] );
		}
	}

	/**
	 * @param Result[]              $results Results of a scan.
	 * @param array<string, string> $groups  Group id by check id; checks missing here count as one "other" group.
	 *
	 * @return int 0 to 100.
	 */
	public function calculate( array $results, array $groups ): int {
		$penalties = [];
		foreach ( $results as $result ) {
			if ( ! $result->is_problem() ) {
				continue;
			}
			$group               = $groups[ $result->check_id() ] ?? '';
			$penalties[ $group ] = ( $penalties[ $group ] ?? 0 ) + $this->severity_weights[ $result->severity() ];
		}

		$total = 0;
		foreach ( $penalties as $penalty ) {
			$total += min( $penalty, $this->group_cap );
		}

		return max( 0, 100 - $total );
	}
}

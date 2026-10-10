<?php declare( strict_types=1 );

namespace Hardenly\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * One security check. Register checks through the `hardenly_checks` filter.
 *
 * A check that may take longer than one batch (a walk over files) calls
 * Context::should_yield() as it works and, when it returns true, saves where it stopped with
 * Context::pause(). The scanner calls run() again in the next batch with that cursor in
 * Context::cursor(). Results returned from each call are kept.
 */
interface Check {
	/**
	 * Unique id: lower-case letters, digits, `_`, `-` and `.`, e.g. `config.debug_display`.
	 */
	public function id(): string;

	/**
	 * Translated name shown while the check runs, e.g. "Debug output".
	 */
	public function title(): string;

	/**
	 * Group id, one of the Group constants or a group added by an extension.
	 */
	public function group(): string;

	/**
	 * Runs the check, or the next part of it.
	 *
	 * @param Context $context Settings, time budget and the cursor of a paused run.
	 *
	 * @return Result[]
	 */
	public function run( Context $context ): array;
}

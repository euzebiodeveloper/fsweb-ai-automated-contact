<?php
/**
 * FSWEB Update Checker — Cron Manager
 *
 * Responsible for scheduling and clearing WP-Cron events for
 * periodic update checks.
 *
 * @package   FSWEB_Update_Checker
 * @version   1.0.0
 * @link      https://flowsyncweb.com
 * @license   GPL-2.0+
 */

namespace FSWEB\UpdateChecker\AiAutomatedContact\V1;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Cron Manager — handles WP-Cron scheduling for periodic update checks.
 */
class Cron {

	/**
	 * How often to check (in hours). 0 disables automatic checks.
	 *
	 * @var int
	 */
	private $check_period;

	/**
	 * Hook suffix for naming cron events.
	 *
	 * @var string
	 */
	private $hook_suffix;

	/**
	 * Full cron hook name.
	 *
	 * @var string
	 */
	private $cron_hook;

	/**
	 * Callable to invoke when the cron fires.
	 *
	 * @var callable
	 */
	private $callback;

	/**
	 * Constructor.
	 *
	 * @param int      $check_period How often to check (hours). 0 = disabled.
	 * @param string   $hook_suffix  Hook suffix (usually the plugin slug).
	 * @param callable $callback     Callback to invoke on cron fire.
	 */
	public function __construct( $check_period, $hook_suffix, $callback ) {
		$this->check_period = max( 0, (int) $check_period );
		$this->hook_suffix  = $hook_suffix;
		$this->cron_hook    = 'fsweb_check_updates_' . $hook_suffix;
		$this->callback     = $callback;
	}

	/**
	 * Schedule the cron event if not already scheduled.
	 *
	 * @return void
	 */
	public function schedule() {
		if ( $this->check_period <= 0 ) {
			return;
		}

		if ( ! wp_next_scheduled( $this->cron_hook ) ) {
			wp_schedule_event(
				time(),
				'fsweb_every_' . $this->check_period . 'hours',
				$this->cron_hook
			);

			add_filter( 'cron_schedules', array( $this, 'register_schedule' ) );
		}

		add_action( $this->cron_hook, $this->callback );
	}

	/**
	 * Clear the scheduled cron event.
	 *
	 * @return void
	 */
	public function clear() {
		$timestamp = wp_next_scheduled( $this->cron_hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $this->cron_hook );
		}
	}

	/**
	 * Register the custom cron schedule interval.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array
	 */
	public function register_schedule( $schedules ) {
		$key = 'fsweb_every_' . $this->check_period . 'hours';

		if ( ! isset( $schedules[ $key ] ) ) {
			/* translators: %d: number of hours */
			$display_label = sprintf(
				__( 'Every %d hours', 'fsweb-mercadopago' ),
				$this->check_period
			);
			$schedules[ $key ] = array(
				'interval' => $this->check_period * HOUR_IN_SECONDS,
				'display'  => $display_label,
			);
		}

		return $schedules;
	}
}

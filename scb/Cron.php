<?php

class scbCron {
	protected $schedule;
	protected $interval;
	protected $time;

	protected $hook;
	protected $callback_args;

	/**
	 * Create a new cron job
	 *
	 * @param string Reference to main plugin file
	 * @param array List of args:
	 		string $action OR callback $callback
			string $schedule OR number $interval
			array $callback_args ( optional )
	 */
	function __construct( $file, $args ) {
		extract( $args, EXTR_SKIP );

		// Set time & schedule
		if ( isset( $time ) )
			$this->time = $time;

		if ( isset( $interval ) ) {
			$this->schedule = $interval . 'secs';
			$this->interval = $interval;
		} elseif ( isset( $schedule ) ) {
			$this->schedule = $schedule;
		}

		// Set hook
		if ( isset( $action ) ) {
			$this->hook = $action;
		} elseif ( isset( $callback ) ) {
			$this->hook = self::_callback_to_string( $callback );
			add_action( $this->hook, $callback );
		} elseif ( method_exists( $this, 'callback' ) ) {
			$this->hook = self::_callback_to_string( array( $this, 'callback' ) );
			add_action( $this->hook, $callback );
		} else {
			trigger_error( '$action OR $callback not set', E_USER_WARNING );
		}

		if ( isset( $callback_args ) )
			$this->callback_args = ( array ) $callback_args;

		if ( $this->schedule ) {
			scbUtil::add_activation_hook( $file, array( $this, 'reset' ) );
			register_deactivation_hook( $file, array( $this, 'unschedule' ) );
		}

		add_filter( 'cron_schedules', array( $this, '_add_timing' ) );
	}

	/* Change the interval of the cron job
	 *
	 * @param array List of args:
			string $schedule OR number $interval
	 		timestamp $time ( optional )
	 */
	function reschedule( $args ) {
		extract( $args );

		if ( $schedule && $this->schedule != $schedule ) {
			$this->schedule = $schedule;
		} elseif ( $interval && $this->interval != $interval ) {
			$this->schedule = $interval . 'secs';
			$this->interval = $interval;
		}

		$this->time = $time;

		$this->reset();
	}

	/**
	 * Reset the schedule
	 */
	function reset() {
		$this->unschedule();
		$this->schedule();
	}

	/**
	 * Clear the cron job
	 */
	function unschedule() {
#		wp_clear_scheduled_hook( $this->hook, $this->callback_args );
		self::really_clear_scheduled_hook( $this->hook );
	}

	/**
	 * Execute the job now
	 * @param array $args List of arguments to pass to the callback
	 */
	function do_now( $args = null ) {
		if ( is_null( $args ) )
			$args = $this->callback_args;

		do_action_ref_array( $this->hook, $args );
	}

	/**
	 * Execute the job with a given delay
	 * @param int $delay in seconds
	 * @param array $args List of arguments to pass to the callback
	 */
	function do_once( $delay = 0, $args = null ) {
		if ( is_null( $args ) )
			$args = $this->callback_args;

		wp_clear_scheduled_hook( $this->hook, $args );
		wp_schedule_single_event( time() + $delay, $this->hook, $args );
	}


//_____INTERNAL METHODS_____


	function _add_timing( $schedules ) {
		if ( isset( $schedules[$this->schedule] ) )
			return $schedules;

		$schedules[$this->schedule] = array( 'interval' => $this->interval,
			'display' => $this->interval . ' seconds' );

		return $schedules;
	}

	protected function schedule() {
		if ( ! $this->time )
			$this->time = time();

		wp_schedule_event( $this->time, $this->schedule, $this->hook, $this->callback_args );
	}

	protected static function really_clear_scheduled_hook( $name ) {
		$crons = _get_cron_array();

		foreach ( $crons as $timestamp => $hooks ) {
			foreach ( $hooks as $hook => $args )
				if ( $hook == $name )
					unset( $crons[$timestamp][$hook] );

			if ( empty( $hooks ) )
				unset( $crons[$timestamp] );
		}

		_set_cron_array( $crons );
	}

	protected static function _callback_to_string( $callback ) {
		if ( ! is_array( $callback ) )
			$str = $callback;
		elseif ( ! is_string( $callback[0] ) )
			$str = get_class( $callback[0] ) . '_' . $callback[1];
		else
			$str = $callback[0] . '::' . $callback[1];

		$str .= '_hook';

		return $str;
	}
}


<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSP_Store_Cron {

	public function __construct() {
		add_action( 'wsp_pickup_reminder_event', array( $this, 'send_pickup_reminders' ) );
	}

	/**
	 * Find tomorrow pickup orders and send reminder emails
	 */
	public function send_pickup_reminders() {

		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		$tomorrow = date( 'Y-m-d', strtotime( '+1 day' ) );

		$args = array(
			'limit'        => -1,
			'status'       => 'completed',
			'meta_key'     => '_wsp_pickup_date',
			'meta_value'   => $tomorrow,
			'meta_compare' => '=',
		);

		$orders = wc_get_orders( $args );

		foreach ( $orders as $order ) {
			$this->send_reminder_email( $order );
		}
	}

	/**
	 * Send reminder email for a specific order
	 */
	private function send_reminder_email( $order ) {
		// Implement email sending logic here
	}
}
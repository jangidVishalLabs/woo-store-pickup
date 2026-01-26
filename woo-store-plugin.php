<?php
/**
 * Plugin Name: Store Plugin
 * Description: Store Pickup with reminder emails
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WSP_PATH', plugin_dir_path( __FILE__ ) );
define( 'WSP_URL', plugin_dir_url( __FILE__ ) );

/**
 * Run main plugin
 */
require_once WSP_PATH . 'includes/class-wsp-plugin.php';

function run_wsp_plugin() {
	$plugin = new WSP_Plugin();
	$plugin->run();
}
run_wsp_plugin();

/**
 * ===============================
 * PICKUP REMINDER CRON
 * ===============================
 */

/**
 * Schedule cron on plugin activation
 */
register_activation_hook( __FILE__, 'wsp_schedule_pickup_reminder_cron' );
register_deactivation_hook( __FILE__, 'wsp_clear_pickup_reminder_cron' );

function wsp_schedule_pickup_reminder_cron() {
	if ( ! wp_next_scheduled( 'wsp_pickup_reminder_event' ) ) {
		wp_schedule_event( time(), 'daily', 'wsp_pickup_reminder_event' );
	}
}

function wsp_clear_pickup_reminder_cron() {
	wp_clear_scheduled_hook( 'wsp_pickup_reminder_event' );
}

/**
 * Cron hook
 */
add_action( 'wsp_pickup_reminder_event', 'wsp_send_pickup_reminders' );

/**
 * Find tomorrow pickup orders
 */
function wsp_send_pickup_reminders() {
	error_log( '[WSP CRON] wsp_send_pickup_reminders() started at ' . current_time( 'mysql' ) );

	if ( ! function_exists( 'wc_get_orders' ) ) {
		error_log( '[WSP CRON] ERROR: wc_get_orders function not found' );
		return;
	}

	$tomorrow = date( 'Y-m-d', strtotime( '+1 day' ) );
	error_log( '[WSP CRON] Looking for orders with pickup date: ' . $tomorrow );

	$args = array(
		'limit'      => -1,
		'status'     => array( 'processing', 'completed', 'on-hold' ),
		'meta_query' => array(
			array(
				'key'     => '_pickup_date',
				'value'   => $tomorrow,
				'compare' => '=',
			),
			array(
				'key'     => '_pickup_reminder_sent',
				'compare' => 'NOT EXISTS',
			),
		),
	);

	$orders = wc_get_orders( $args );
	error_log( '[WSP CRON] Found ' . count( $orders ) . ' orders to process' );

	if ( empty( $orders ) ) {
		error_log( '[WSP CRON] No orders found, exiting' );
		return;
	}

	foreach ( $orders as $order ) {
		error_log( '[WSP CRON] Processing order #' . $order->get_id() );
		wsp_send_pickup_reminder_email( $order );
	}

	error_log( '[WSP CRON] wsp_send_pickup_reminders() completed' );
}

/**
 * Send reminder email
 */
function wsp_send_pickup_reminder_email( $order ) {

	if ( ! $order instanceof WC_Order ) {
		error_log( '[WSP CRON] ERROR: Invalid order object' );
		return;
	}

	// Ensure this is Store Pickup
	$is_pickup = false;
	foreach ( $order->get_shipping_methods() as $method ) {
		if ( $method->get_method_id() === 'wsp_store_pickup' ) {
			$is_pickup = true;
			break;
		}
	}
	if ( ! $is_pickup ) {
		error_log( '[WSP CRON] Order #' . $order->get_id() . ' is not a store pickup order, skipping' );
		return;
	}

	$order_id   = $order->get_id();
	$email      = $order->get_billing_email();
	$first_name = $order->get_billing_first_name();

	$pickup_date   = $order->get_meta( '_pickup_date' );
	$store_name    = $order->get_meta( '_pickup_store_name' );
	$store_address = $order->get_meta( '_pickup_store_address' );
	$map_url       = $order->get_meta( '_pickup_store_map' );

	if ( empty( $email ) || empty( $pickup_date ) ) {
		error_log( '[WSP CRON] Order #' . $order_id . ' missing email or pickup date, skipping' );
		return;
	}

	$subject = "Pickup Reminder – Order #{$order_id}";

	$message  = "Dear {$first_name},\n\n";
	$message .= "This is a reminder to collect your order.\n\n";
	$message .= "Order ID: #{$order_id}\n";
	$message .= "Pickup Date: {$pickup_date}\n";
	$message .= "Store: {$store_name}\n";
	$message .= "Address: {$store_address}\n";

	if ( ! empty( $map_url ) ) {
		$message .= "Map: {$map_url}\n";
	}

	$message .= "\nThank you for shopping with us!\n";
	$message .= "— Store Team";

	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

	if ( wp_mail( $email, $subject, $message, $headers ) ) {
		error_log( '[WSP CRON] Email sent successfully for order #' . $order_id . ' to ' . $email );
		$order->update_meta_data( '_pickup_reminder_sent', 'yes' );
		$order->save();
	} else {
		error_log( '[WSP CRON] ERROR: Failed to send email for order #' . $order_id . ' to ' . $email );
	}
}

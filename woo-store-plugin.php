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
define( 'WSP_VERSION', '1.0.0' );


/**
 * Run main plugin
 */
require_once WSP_PATH . 'includes/class-wsp-plugin.php';
require_once WSP_PATH . 'includes/admin/class-wsp-admin-orders.php';

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
	error_log( 'WSP Cron: wsp_schedule_pickup_reminder_cron called during plugin activation.' );
	if ( ! wp_next_scheduled( 'wsp_pickup_reminder_event_tomorrow' ) ) {
		error_log( 'WSP Cron: Scheduling wsp_pickup_reminder_event_tomorrow at ' . date( 'Y-m-d H:i:s', strtotime( 'tomorrow 00:05' ) ) );
		wp_schedule_event( strtotime( 'tomorrow 00:05' ), 'daily', 'wsp_pickup_reminder_event_tomorrow' );
	} else {
		error_log( 'WSP Cron: wsp_pickup_reminder_event_tomorrow already scheduled.' );
	}

	// 2️⃣ Reminder on pickup day morning (08:00)
	if ( ! wp_next_scheduled( 'wsp_pickup_reminder_event_today' ) ) {
		error_log( 'WSP Cron: Scheduling wsp_pickup_reminder_event_today.' );
		$timestamp = strtotime( 'today 08:00' );

		if ( $timestamp < time() ) {
			$timestamp = strtotime( 'tomorrow 08:00' );
			error_log( 'WSP Cron: Today 08:00 has passed, scheduling for tomorrow at ' . date( 'Y-m-d H:i:s', $timestamp ) );
		} else {
			error_log( 'WSP Cron: Scheduling for today at ' . date( 'Y-m-d H:i:s', $timestamp ) );
		}

		wp_schedule_event( $timestamp, 'daily', 'wsp_pickup_reminder_event_today' );
	} else {
		error_log( 'WSP Cron: wsp_pickup_reminder_event_today already scheduled.' );
	}
	error_log( 'WSP Cron: wsp_schedule_pickup_reminder_cron completed.' );
}

function wsp_clear_pickup_reminder_cron() {
	error_log( 'WSP Cron: wsp_clear_pickup_reminder_cron called during plugin deactivation.' );
	error_log( 'WSP Cron: Clearing wsp_pickup_reminder_event_tomorrow.' );
	wp_clear_scheduled_hook( 'wsp_pickup_reminder_event_tomorrow' );
	error_log( 'WSP Cron: Clearing wsp_pickup_reminder_event_today.' );
	wp_clear_scheduled_hook( 'wsp_pickup_reminder_event_today' );
	error_log( 'WSP Cron: wsp_clear_pickup_reminder_cron completed.' );
}

/**
 * Cron hook
 */
add_action(
	'wsp_pickup_reminder_event_tomorrow',
	'wsp_send_pickup_reminders_tomorrow'
);

add_action(
	'wsp_pickup_reminder_event_today',
	'wsp_send_pickup_reminders_today'
);

/**
 * Find tomorrow pickup orders
 */
function wsp_send_pickup_reminders_tomorrow() {
	error_log( 'WSP Cron: wsp_send_pickup_reminders_tomorrow event triggered.' );

	if ( ! function_exists( 'wc_get_orders' ) ) {
		error_log( 'WSP Cron: wc_get_orders function not found, exiting.' );
		return;
	}

	$tomorrow = date( 'Y-m-d', strtotime( '+1 day' ) );
	error_log( 'WSP Cron: Looking for orders with pickup date - ' . $tomorrow );

	$args = array(
		'limit'  => -1,
		'status' => array( 'processing', 'completed' ), // ✅ CONDITION
		'meta_query' => array(
			array(
				'key'   => '_pickup_date',
				'value' => $tomorrow,
			),
			array(
				'key'     => '_pickup_reminder_tomorrow_sent',
				'compare' => 'NOT EXISTS',
			),
		),
	);

	$orders = wc_get_orders( $args );
	error_log( 'WSP Cron: Found ' . count( $orders ) . ' orders requiring tomorrow reminder.' );

	foreach ( $orders as $order ) {
		error_log( 'WSP Cron: Processing tomorrow reminder for order ID - ' . $order->get_id() );
		wsp_send_pickup_reminder_email( $order, 'tomorrow' );
	}

	error_log( 'WSP Cron: wsp_send_pickup_reminders_tomorrow event completed.' );
}

function wsp_send_pickup_reminders_today() {
	error_log( 'WSP Cron: wsp_send_pickup_reminders_today event triggered.' );

	if ( ! function_exists( 'wc_get_orders' ) ) {
		error_log( 'WSP Cron: wc_get_orders function not found, exiting.' );
		return;
	}

	$today = date( 'Y-m-d' );
	$tomorrow = wp_date( 'Y-m-d', strtotime( '+1 day' ) );
	error_log( 'WSP Cron: Looking for orders with pickup date - ' . $today );

	$args = array(
		'limit'  => -1,
		'status' => array( 'processing', 'completed' ), // ✅ CONDITION
		'meta_query' => array(
			array(
				'key'   => '_pickup_date',
				'value' => $today,
			),
			array(
				'key'     => '_pickup_reminder_today_sent',
				'compare' => 'NOT EXISTS',
			),
		),
	);

	$orders = wc_get_orders( $args );
	error_log( 'WSP Cron: Found ' . count( $orders ) . ' orders requiring today reminder.' );

	foreach ( $orders as $order ) {
		error_log( 'WSP Cron: Processing today reminder for order ID - ' . $order->get_id() );
		wsp_send_pickup_reminder_email( $order, 'today' );
	}

	error_log( 'WSP Cron: wsp_send_pickup_reminders_today event completed.' );
}


/**
 * Send reminder email
 */
function wsp_send_pickup_reminder_email( $order, $type = 'tomorrow' ) {
	error_log( 'WSP Cron: wsp_send_pickup_reminder_email called for order ID - ' . $order->get_id() . ', type - ' . $type );

	if ( ! $order instanceof WC_Order ) {
		error_log( 'WSP Cron: Invalid order object for order ID - ' . ( isset( $order ) ? $order->get_id() : 'Unknown' ) );
		return;
	}

	// ✅ HARD STOP: Only processing & completed
	if ( ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
		error_log( 'WSP Cron: Order status not processing or completed, status - ' . $order->get_status() . ', skipping.' );
		return;
	}

	// Ensure Store Pickup
	$is_pickup = false;
	foreach ( $order->get_shipping_methods() as $method ) {
		if ( $method->get_method_id() === 'wsp_store_pickup' ) {
			$is_pickup = true;
			break;
		}
	}
	if ( ! $is_pickup ) {
		error_log( 'WSP Cron: Order does not have store pickup shipping method, skipping.' );
		return;
	}

	$order_id   = $order->get_id();
	$email      = $order->get_billing_email();
	$first_name = $order->get_billing_first_name();

	$pickup_date   = $order->get_meta( '_pickup_date' );
	$store_name    = $order->get_meta( '_pickup_store_name' );
	$store_address = $order->get_meta( '_pickup_store_address' );
	$map_url       = $order->get_meta( '_pickup_store_map' );

	error_log( 'WSP Cron: Order details - Email: ' . $email . ', Pickup Date: ' . $pickup_date . ', Store: ' . $store_name );

	if ( empty( $email ) || empty( $pickup_date ) ) {
		error_log( 'WSP Cron: Missing email or pickup date for order ID - ' . $order_id . ', skipping.' );
		return;
	}

	// Subject based on type
	$subject = ( $type === 'today' )
		? "Today Pickup Reminder – Order #{$order_id}"
		: "Pickup Reminder – Order #{$order_id}";

	$message  = "Dear {$first_name},\n\n";

	$message .= ( $type === 'today' )
		? "This is a reminder to collect your order TODAY.\n\n"
		: "This is a reminder to collect your order tomorrow.\n\n";

	$message .= "Order ID: #{$order_id}\n";
	$message .= "Pickup Date: {$pickup_date}\n";
	$message .= "Store: {$store_name}\n";
	$message .= "Address: {$store_address}\n";

	if ( $map_url ) {
		$message .= "Map: {$map_url}\n";
	}

	$message .= "\nThank you for shopping with us!\n— Store Team";

	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

	error_log( 'WSP Cron: Sending ' . $type . ' reminder email to ' . $email . ' for order ID - ' . $order_id );

	if ( wp_mail( $email, $subject, $message, $headers ) ) {
		error_log( 'WSP Cron: Email sent successfully for order ID - ' . $order_id );

		// Save flag
		$meta_key = ( $type === 'today' )
			? '_pickup_reminder_today_sent'
			: '_pickup_reminder_tomorrow_sent';

		$order->update_meta_data( $meta_key, 'yes' );
		$order->save();
		error_log( 'WSP Cron: Meta flag set - ' . $meta_key . ' for order ID - ' . $order_id );
	} else {
		error_log( 'WSP Cron: Failed to send email for order ID - ' . $order_id );
	}
}


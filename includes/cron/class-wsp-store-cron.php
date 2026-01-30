<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * ===============================
 * PICKUP REMINDER CRON SETUP
 * ===============================
 * 
 * Handles scheduling and clearing of WordPress cron jobs for sending
 * pickup reminder emails to customers at specified times.
 */


/**
 * Schedule pickup reminder cron events.
 *
 * Sets up three daily cron jobs:
 * - wsp_pickup_reminder_event_tomorrow: Tomorrow morning (00:05) for next-day pickups
 * - wsp_pickup_reminder_event_today: Pickup day morning (08:00) for same-day pickups  
 * - wsp_handle_missed_event: Daily at 09:00 to handle missed pickups
 *
 * @return void
 */
function wsp_schedule_pickup_reminder_cron() {
	if ( ! wp_next_scheduled( 'wsp_pickup_reminder_event_tomorrow' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 00:05' ), 'daily', 'wsp_pickup_reminder_event_tomorrow' );
	}

	if ( ! wp_next_scheduled( 'wsp_handle_missed_event' ) ) {
		wp_schedule_event( strtotime( 'today 09:00' ), 'daily', 'wsp_handle_missed_event' );
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

/**
 * Clear scheduled cron jobs on plugin deactivation.
 *
 * Removes all scheduled pickup reminder events when the plugin is deactivated.
 * Prevents orphaned cron jobs from running after plugin removal.
 *
 * @return void
 */
function wsp_clear_pickup_reminder_cron() {
	error_log( 'WSP Cron: wsp_clear_pickup_reminder_cron called during plugin deactivation.' );
	error_log( 'WSP Cron: Clearing wsp_pickup_reminder_event_tomorrow.' );
	wp_clear_scheduled_hook( 'wsp_pickup_reminder_event_tomorrow' );
	error_log( 'WSP Cron: Clearing wsp_pickup_reminder_event_today.' );
	wp_clear_scheduled_hook( 'wsp_pickup_reminder_event_today' );
	error_log( 'WSP Cron: wsp_clear_pickup_reminder_cron completed.' );
	wp_clear_scheduled_hook( 'wsp_handle_missed_event' );
}

/**
 * Register cron job hook handlers.
 * 
 * Connects the scheduled WordPress cron events to their corresponding callback functions.
 */
add_action(
	'wsp_pickup_reminder_event_tomorrow',
	'wsp_send_pickup_reminders_tomorrow'
);

add_action(
	'wsp_pickup_reminder_event_today',
	'wsp_send_pickup_reminders_today'
);

add_action( 'wsp_handle_missed_event', 'wsp_handle_missed_pickups' );

add_action(
	'woocommerce_email_after_order_table',
	'wsp_add_pickup_details_to_order_email',
	20,
	4
);

function wsp_add_pickup_details_to_order_email( $order, $sent_to_admin, $plain_text, $email ) {

	// Only customer emails
	if ( $sent_to_admin ) {
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
		return; // ✅ TC059
	}

	$store_name    = $order->get_meta( '_pickup_store_name' );
	$store_address = nl2br( esc_html( $order->get_meta( '_pickup_store_address' ) ) );
	$pickup_date   = esc_html( $order->get_meta( '_pickup_date' ) );
	$map_url       = esc_url( $order->get_meta( '_pickup_store_map' ) );

	if ( $plain_text ) {
		echo "\n--- Store Pickup Details ---\n";
		echo "Store: {$store_name}\n";
		echo "Address: " . strip_tags( $store_address ) . "\n";
		echo "Pickup Date: {$pickup_date}\n";
		if ( $map_url ) {
			echo "Map: {$map_url}\n";
		}
		return;
	}
	?>

	<h3><?php esc_html_e( 'Store Pickup Details', 'woo-store-plugin' ); ?></h3>
	<p><strong><?php esc_html_e( 'Store:', 'woo-store-plugin' ); ?></strong> <?php echo esc_html( $store_name ); ?></p>
	<p><strong><?php esc_html_e( 'Address:', 'woo-store-plugin' ); ?></strong><br><?php echo wp_kses_post( $store_address ); ?></p>
	<p><strong><?php esc_html_e( 'Pickup Date:', 'woo-store-plugin' ); ?></strong> <?php echo esc_html( $pickup_date ); ?></p>

	<?php if ( $map_url ) : ?>
	<p>
		<a href="<?php echo $map_url; ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'View on Google Maps', 'woo-store-plugin' ); ?>
		</a>
	</p>
	<?php endif; ?>
	<?php
}


/**
 * Send pickup reminder emails for orders with pickup date tomorrow.
 *
 * Queries for all processing/completed orders scheduled to be picked up tomorrow
 * and sends reminder emails to the customers. Executes daily at 00:05 AM.
 *
 * @return void
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
		'limit'      => -1,
		'status'     => array( 'processing', 'completed' ), // ✅ CONDITION
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

/**
 * Send pickup reminder emails for orders with pickup date today.
 *
 * Queries for all processing/completed orders scheduled to be picked up today
 * and sends reminder emails to the customers. Executes daily at 8:00 AM.
 *
 * @return void
 */
function wsp_send_pickup_reminders_today() {
	error_log( 'WSP Cron: wsp_send_pickup_reminders_today event triggered.' );

	if ( ! function_exists( 'wc_get_orders' ) ) {
		error_log( 'WSP Cron: wc_get_orders function not found, exiting.' );
		return;
	}

	$today    = date( 'Y-m-d' );
	$tomorrow = wp_date( 'Y-m-d', strtotime( '+1 day' ) );
	error_log( 'WSP Cron: Looking for orders with pickup date - ' . $today );

	$args = array(
		'limit'      => -1,
		'status'     => array( 'processing', 'completed' ), // ✅ CONDITION
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
 * Send a pickup reminder email to a customer.
 *
 * Composes and sends a reminder email containing pickup details to the customer.
 * Marks the order to prevent duplicate reminders.
 *
 * @param WC_Order $order The order object to send a reminder for.
 * @param string   $type  Optional. Type of reminder ('tomorrow' or 'today'). Default is 'tomorrow'.
 * @return void
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

	$message = "Dear {$first_name},\n\n";

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

/**
 * Handle processing orders with missed pickup dates.
 *
 * Queries for processing orders where the pickup date has passed and applies
 * extension or cancellation logic depending on circumstances. Executes daily at 09:00 AM.
 *
 * @return void
 */
function wsp_handle_missed_pickups() {
	error_log( 'WSP Cron: wsp_handle_missed_pickups event triggered.' );

	// Handle missed reminders for tomorrow
	$yesterday = wp_date( 'Y-m-d', strtotime( '-1 day' ) );

	$args   = array(
		'limit'      => -1,
		'status'     => array( 'processing' ),
		'meta_query' => array(
			array(
				'key'     => '_pickup_date',
				'value'   => $yesterday,
				'compare' => '<',
				'type'    => 'DATE',
			),
		),
	);
	$orders = wc_get_orders( $args );

	foreach ( $orders as $order ) {
		wsp_process_missed_pickup_order( $order );
	}
}

/**
 * Process a missed pickup order.
 *
 * Applies extension or cancellation logic to a processing order with a passed pickup date.
 * First-time missed pickups are extended; subsequent misses may be cancelled.
 *
 * @param WC_Order $order The order object to process.
 * @return void
 */
function wsp_process_missed_pickup_order( WC_Order $order ) {
	if ( $order->get_status() === 'completed' ) {
		error_log( "WSP Cron: Order {$order_id} already completed, skipping missed pickup logic." );
		return;
	}

	$order_id = $order->get_id();

	// Ensure Store Pickup
	$is_pickup = false;
	foreach ( $order->get_shipping_methods() as $method ) {
		if ( $method->get_method_id() === 'wsp_store_pickup' ) {
			$is_pickup = true;
			break;
		}
	}
	if ( ! $is_pickup ) {
		error_log( 'WSP Cron: Order ID ' . $order_id . ' is not a store pickup, skipping.' );
		return;
	}
	$pickup_date      = $order->get_meta( '_pickup_date' );
	$already_extended = $order->get_meta( '_pickup_extended' );
	/**
	 * Case 1 :  Not extended yet -> Extend + final Reminder
	 */
	if ( empty( $already_extended ) ) {
		$final_sent = $order->get_meta( '_pickup_final_reminder_sent' );

		if ( ! empty( $final_sent ) ) {
				error_log( "WSP Cron: Final reminder already sent for order {$order_id}, skipping." );
				return;
		}

		$new_date = date( 'Y-m-d', strtotime( $pickup_date . ' +1 day' ) );
		$order->update_meta_data( '_pickup_date', $new_date );
		$order->update_meta_data( '_pickup_extended', 'yes' );
		$order->update_meta_data( '_pickup_final_reminder_sent', 'yes' );
		$order->save();

		wsp_send_final_pickup_warning_email( $order, $new_date );
		error_log( 'WSP Cron: Order ID ' . $order_id . ' pickup extended to ' . $new_date . ' and final reminder sent.' );
		return;
	}
	/**
	 * CASE 2: Already extended -> Cancel Order
	 */
	$order->update_status( 'cancelled', 'Order cancelled due to uncollected pickup after extension.' );
	error_log( 'WSP Cron: Order ID ' . $order_id . ' cancelled due to uncollected pickup after extension.' );
	$order_id = $order->get_id();
	wp_delete_post( $order_id, true );
	error_log( 'WSP Cron: Order ID ' . $order_id . ' deleted from system.' );
}

function wsp_send_final_pickup_warning_email( WC_Order $order, $new_date ) {
	$order_id   = $order->get_id();
	$email      = $order->get_billing_email();
	$first_name = $order->get_billing_first_name();

	$store_name    = $order->get_meta( '_pickup_store_name' );
	$store_address = $order->get_meta( '_pickup_store_address' );
	$map_url       = $order->get_meta( '_pickup_store_map' );

	$subject = "Final Pickup Reminder – Order #{$order_id}";

	$message  = "Dear {$first_name},\n\n";
	$message .= "This is a final reminder to collect your order. Your pickup date has been extended to {$new_date}.\n\n";
	$message .= "Order ID: #{$order_id}\n";
	$message .= "New Pickup Date: {$new_date}\n";
	$message .= "Store: {$store_name}\n";
	$message .= "Address: {$store_address}\n";

	if ( $map_url ) {
		$message .= "Map: {$map_url}\n";
	}

	$message .= "\nPlease note that if the order is not collected by this date, it will be cancelled.\n";
	$message .= "Thank you for shopping with us!\n— Store Team";

	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

	wp_mail( $email, $subject, $message, $headers );
}

add_action( 'woocommerce_checkout_order_processed', 'wsp_schedule_pickup_reminder_for_order' );

function wsp_schedule_pickup_reminder_for_order( $order_id ) {

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	// Only Store Pickup
	$is_pickup = false;
	foreach ( $order->get_shipping_methods() as $method ) {
		if ( $method->get_method_id() === 'wsp_store_pickup' ) {
			$is_pickup = true;
			break;
		}
	}
	if ( ! $is_pickup ) {
		return; // ✅ TC065
	}

	$pickup_date = $order->get_meta( '_pickup_date' );
	if ( empty( $pickup_date ) ) {
		return;
	}

	$timestamp = strtotime( $pickup_date . ' -1 day 09:00' );

	if ( $timestamp <= time() ) {
		return;
	}

	wp_schedule_single_event(
		$timestamp,
		'wsp_send_pickup_reminder_single',
		array( $order_id )
	);
}

add_action( 'wsp_send_pickup_reminder_single', 'wsp_send_pickup_reminder_single_handler' );

function wsp_send_pickup_reminder_single_handler( $order_id ) {

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	// Do NOT send if cancelled/refunded/on-hold
	if ( ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
		return; // ✅ TC066, TC067, TC068
	}

	wsp_send_pickup_reminder_email( $order, 'tomorrow' );
}

add_action( 'woocommerce_order_status_cancelled', 'wsp_clear_pickup_reminder_for_order' );
add_action( 'woocommerce_order_status_refunded', 'wsp_clear_pickup_reminder_for_order' );

function wsp_clear_pickup_reminder_for_order( $order_id ) {
	wp_clear_scheduled_hook( 'wsp_send_pickup_reminder_single', array( $order_id ) );
}




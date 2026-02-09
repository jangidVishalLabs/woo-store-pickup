<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ===============================
 * PICKUP REMINDER CRON SETUP
 * ===============================
 */

// ── Scheduling & clearing ───────────────────────────────────────────────────

function wsp_schedule_pickup_reminder_cron() {
	if ( ! wp_next_scheduled( 'wsp_pickup_reminder_event_tomorrow' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 00:05' ), 'daily', 'wsp_pickup_reminder_event_tomorrow' );
	}

	if ( ! wp_next_scheduled( 'wsp_pickup_reminder_event_today' ) ) {
		$timestamp = strtotime( 'today 08:00' );
		if ( $timestamp <= time() ) {
			$timestamp = strtotime( 'tomorrow 08:00' );
		}
		wp_schedule_event( $timestamp, 'daily', 'wsp_pickup_reminder_event_today' );
	}

	if ( ! wp_next_scheduled( 'wsp_handle_missed_event' ) ) {
		wp_schedule_event( strtotime( 'today 09:00' ), 'daily', 'wsp_handle_missed_event' );
	}
}

function wsp_clear_pickup_reminder_cron() {
	wp_clear_scheduled_hook( 'wsp_pickup_reminder_event_tomorrow' );
	wp_clear_scheduled_hook( 'wsp_pickup_reminder_event_today' );
	wp_clear_scheduled_hook( 'wsp_handle_missed_event' );
}

// ── Hook bindings ────────────────────────────────────────────────────────────

add_action( 'wsp_pickup_reminder_event_tomorrow', 'wsp_send_pickup_reminders_tomorrow' );
add_action( 'wsp_pickup_reminder_event_today',    'wsp_send_pickup_reminders_today' );
add_action( 'wsp_handle_missed_event',            'wsp_handle_missed_pickups' );

add_action( 'woocommerce_email_after_order_table', 'wsp_add_pickup_details_to_order_email', 20, 4 );

// ── Email: pickup details in order confirmation ─────────────────────────────

function wsp_add_pickup_details_to_order_email( $order, $sent_to_admin, $plain_text, $email ) {

	if ( $sent_to_admin ) {
		return;
	}

	if ( ! wsp_order_is_pickup( $order ) ) {
		return;
	}

	$store_name    = $order->get_meta( '_pickup_store_name' );
	$store_address = $order->get_meta( '_pickup_store_address' );
	$pickup_date   = $order->get_meta( '_pickup_date' );
	$map_url       = $order->get_meta( '_pickup_store_map' );

	if ( $plain_text ) {
		echo "\n--- Store Pickup Details ---\n";
		echo "Store: " . esc_html( $store_name ) . "\n";
		echo "Address: " . esc_html( $store_address ) . "\n";
		echo "Pickup Date: " . esc_html( $pickup_date ) . "\n";
		if ( $map_url ) {
			echo "Map: " . esc_url( $map_url ) . "\n";
		}
		return;
	}
	?>
	<h3><?php esc_html_e( 'Store Pickup Details', 'woo-store-plugin' ); ?></h3>
	<p><strong><?php esc_html_e( 'Store:', 'woo-store-plugin' ); ?></strong> <?php echo esc_html( $store_name ); ?></p>
	<p><strong><?php esc_html_e( 'Address:', 'woo-store-plugin' ); ?></strong><br><?php echo nl2br( esc_html( $store_address ) ); ?></p>
	<p><strong><?php esc_html_e( 'Pickup Date:', 'woo-store-plugin' ); ?></strong> <?php echo esc_html( $pickup_date ); ?></p>
	<?php if ( $map_url ) : ?>
	<p>
		<a href="<?php echo esc_url( $map_url ); ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'View on Google Maps', 'woo-store-plugin' ); ?>
		</a>
	</p>
	<?php endif; ?>
	<?php
}

// ── Tomorrow reminder (runs 00:05) ──────────────────────────────────────────

function wsp_send_pickup_reminders_tomorrow() {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return;
	}

	$tomorrow = date( 'Y-m-d', strtotime( '+1 day' ) );

	$orders = wc_get_orders( array(
		'limit'      => -1,
		'status'     => array( 'processing', 'completed' ),
		'meta_query' => array(
			array( 'key' => '_pickup_date', 'value' => $tomorrow ),
			array( 'key' => '_pickup_reminder_tomorrow_sent', 'compare' => 'NOT EXISTS' ),
		),
	) );

	foreach ( $orders as $order ) {
		wsp_send_pickup_reminder_email( $order, 'tomorrow' );
	}
}

// ── Today reminder (runs 08:00) ──────────────────────────────────────────────

function wsp_send_pickup_reminders_today() {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return;
	}

	$today = date( 'Y-m-d' );

	$orders = wc_get_orders( array(
		'limit'      => -1,
		'status'     => array( 'processing', 'completed' ),
		'meta_query' => array(
			array( 'key' => '_pickup_date', 'value' => $today ),
			array( 'key' => '_pickup_reminder_today_sent', 'compare' => 'NOT EXISTS' ),
		),
	) );

	foreach ( $orders as $order ) {
		wsp_send_pickup_reminder_email( $order, 'today' );
	}
}

// ── Core reminder sender ─────────────────────────────────────────────────────

function wsp_send_pickup_reminder_email( $order, $type = 'tomorrow' ) {

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	if ( ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
		return;
	}

	if ( ! wsp_order_is_pickup( $order ) ) {
		return;
	}

	$order_id      = $order->get_id();
	$email         = $order->get_billing_email();
	$first_name    = $order->get_billing_first_name();
	$pickup_date   = $order->get_meta( '_pickup_store_name' ) ? $order->get_meta( '_pickup_date' ) : '';
	$store_name    = $order->get_meta( '_pickup_store_name' );
	$store_address = $order->get_meta( '_pickup_store_address' );
	$map_url       = $order->get_meta( '_pickup_store_map' );

	if ( empty( $email ) || empty( $pickup_date ) ) {
		return;
	}

	$subject = ( $type === 'today' )
		? sprintf( __( 'Today Pickup Reminder – Order #%s', 'woo-store-plugin' ), $order_id )
		: sprintf( __( 'Pickup Reminder – Order #%s', 'woo-store-plugin' ), $order_id );

	$message  = sprintf( __( 'Dear %s,', 'woo-store-plugin' ), $first_name ) . "\n\n";
	$message .= ( $type === 'today' )
		? __( 'This is a reminder to collect your order TODAY.', 'woo-store-plugin' ) . "\n\n"
		: __( 'This is a reminder to collect your order tomorrow.', 'woo-store-plugin' ) . "\n\n";
	$message .= sprintf( __( 'Order ID: #%s', 'woo-store-plugin' ), $order_id ) . "\n";
	$message .= sprintf( __( 'Pickup Date: %s', 'woo-store-plugin' ), $pickup_date ) . "\n";
	$message .= sprintf( __( 'Store: %s', 'woo-store-plugin' ), $store_name ) . "\n";
	$message .= sprintf( __( 'Address: %s', 'woo-store-plugin' ), $store_address ) . "\n";
	if ( $map_url ) {
		$message .= sprintf( __( 'Map: %s', 'woo-store-plugin' ), $map_url ) . "\n";
	}
	$message .= "\n" . __( 'Thank you for shopping with us!', 'woo-store-plugin' ) . "\n— Store Team";

	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

	if ( wp_mail( $email, $subject, $message, $headers ) ) {
		$meta_key = ( $type === 'today' ) ? '_pickup_reminder_today_sent' : '_pickup_reminder_tomorrow_sent';
		$order->update_meta_data( $meta_key, 'yes' );
		$order->save();
	}
}

// ── Missed-pickup handler (runs 09:00) ───────────────────────────────────────

function wsp_handle_missed_pickups() {
	$yesterday = date( 'Y-m-d', strtotime( '-1 day' ) );

	$orders = wc_get_orders( array(
		'limit'      => -1,
		'status'     => array( 'processing' ),
		'meta_query' => array(
			array(
				'key'     => '_pickup_date',
				'value'   => $yesterday,
				'compare' => '<=',          // FIX: was '<' which misses exact match on yesterday
				'type'    => 'DATE',
			),
		),
	) );

	foreach ( $orders as $order ) {
		wsp_process_missed_pickup_order( $order );
	}
}

/**
 * FIX: $order_id was used in the very first log line BEFORE it was assigned.
 * Moved assignment to the top.
 *
 * FIX: wp_delete_post() does not work on HPOS orders.  Use $order->delete()
 * instead (available in WC 8.x+).  Removed the hard delete entirely — a
 * cancelled order should stay in the system for audit/refund purposes.
 */
function wsp_process_missed_pickup_order( WC_Order $order ) {

	$order_id = $order->get_id();   // FIX: moved BEFORE first use

	if ( $order->get_status() === 'completed' ) {
		return;
	}

	if ( ! wsp_order_is_pickup( $order ) ) {
		return;
	}

	$pickup_date      = $order->get_meta( '_pickup_date' );
	$already_extended = $order->get_meta( '_pickup_extended' );

	// ── Case 1: first miss → extend by one day + final warning email
	if ( empty( $already_extended ) ) {
		$new_date = date( 'Y-m-d', strtotime( $pickup_date . ' +1 day' ) );

		$order->update_meta_data( '_pickup_date',                  $new_date );
		$order->update_meta_data( '_pickup_extended',              'yes' );
		$order->update_meta_data( '_pickup_final_reminder_sent',   'yes' );
		$order->save();

		wsp_send_final_pickup_warning_email( $order, $new_date );
		return;
	}

	// ── Case 2: already extended → cancel (keep in DB for records)
	$order->update_status( 'cancelled', 'Order cancelled due to uncollected pickup after extension.' );
}

function wsp_send_final_pickup_warning_email( WC_Order $order, $new_date ) {
	$order_id      = $order->get_id();
	$email         = $order->get_billing_email();
	$first_name    = $order->get_billing_first_name();
	$store_name    = $order->get_meta( '_pickup_store_name' );
	$store_address = $order->get_meta( '_pickup_store_address' );
	$map_url       = $order->get_meta( '_pickup_store_map' );

	$subject  = sprintf( __( 'Final Pickup Reminder – Order #%s', 'woo-store-plugin' ), $order_id );

	$message  = sprintf( __( 'Dear %s,', 'woo-store-plugin' ), $first_name ) . "\n\n";
	$message .= sprintf( __( 'This is a final reminder to collect your order. Your pickup date has been extended to %s.', 'woo-store-plugin' ), $new_date ) . "\n\n";
	$message .= sprintf( __( 'Order ID: #%s', 'woo-store-plugin' ), $order_id ) . "\n";
	$message .= sprintf( __( 'New Pickup Date: %s', 'woo-store-plugin' ), $new_date ) . "\n";
	$message .= sprintf( __( 'Store: %s', 'woo-store-plugin' ), $store_name ) . "\n";
	$message .= sprintf( __( 'Address: %s', 'woo-store-plugin' ), $store_address ) . "\n";
	if ( $map_url ) {
		$message .= sprintf( __( 'Map: %s', 'woo-store-plugin' ), $map_url ) . "\n";
	}
	$message .= "\n" . __( 'Please note that if the order is not collected by this date, it will be cancelled.', 'woo-store-plugin' ) . "\n";
	$message .= __( 'Thank you for shopping with us!', 'woo-store-plugin' ) . "\n— Store Team";

	wp_mail( $email, $subject, $message, array( 'Content-Type: text/plain; charset=UTF-8' ) );
}

// ── Per-order single reminder (scheduled at checkout) ───────────────────────

add_action( 'woocommerce_checkout_order_processed', 'wsp_schedule_pickup_reminder_for_order' );

function wsp_schedule_pickup_reminder_for_order( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || ! wsp_order_is_pickup( $order ) ) {
		return;
	}

	$pickup_date = $order->get_meta( '_pickup_date' );
	if ( empty( $pickup_date ) ) {
		return;
	}

	$timestamp = strtotime( $pickup_date . ' -1 day 09:00' );
	if ( $timestamp <= time() ) {
		return;
	}

	wp_schedule_single_event( $timestamp, 'wsp_send_pickup_reminder_single', array( $order_id ) );
}

add_action( 'wsp_send_pickup_reminder_single', 'wsp_send_pickup_reminder_single_handler' );

function wsp_send_pickup_reminder_single_handler( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}
	if ( ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
		return;
	}
	wsp_send_pickup_reminder_email( $order, 'tomorrow' );
}

// ── Clear per-order reminder on cancel / refund ─────────────────────────────

add_action( 'woocommerce_order_status_cancelled', 'wsp_clear_pickup_reminder_for_order' );
add_action( 'woocommerce_order_status_refunded',  'wsp_clear_pickup_reminder_for_order' );

function wsp_clear_pickup_reminder_for_order( $order_id ) {
	wp_clear_scheduled_hook( 'wsp_send_pickup_reminder_single', array( $order_id ) );
}

// ── Shared helper ────────────────────────────────────────────────────────────

/**
 * Return true when $order uses the wsp_store_pickup shipping method.
 */
function wsp_order_is_pickup( WC_Order $order ): bool {
	foreach ( $order->get_shipping_methods() as $method ) {
		if ( $method->get_method_id() === 'wsp_store_pickup' ) {
			return true;
		}
	}
	return false;
}
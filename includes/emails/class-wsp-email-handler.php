<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WSP_Email_Handler' ) ) {
	return;
}

/**
 * WSP_Email_Handler class.
 *
 * Handles the addition of pickup details to WooCommerce order email notifications.
 * Formats pickup information for both HTML and plain text email formats.
 *
 * @class WSP_Email_Handler
 * @version 1.0.0
 */
class WSP_Email_Handler {
	/**
	 * Add pickup details to WooCommerce order emails.
	 *
	 * Inserts pickup store information (name, address, date, and map link) into
	 * order confirmation and notification emails. Supports both HTML and plain text formats.
	 *
	 * @param WC_Order $order         The order object.
	 * @param bool     $sent_to_admin Whether the email is being sent to an administrator.
	 * @param bool     $plain_text    Whether the email format is plain text.
	 * @param object   $email         The email object being sent.
	 * @return void
	 */
	public function add_pickup_details_to_email( $order, $sent_to_admin, $plain_text, $email ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$store_name    = $order->get_meta( '_pickup_store_name' );
		$store_address = $order->get_meta( '_pickup_store_address' );
		$pickup_date   = $order->get_meta( '_pickup_date' );
		$map_url       = $order->get_meta( '_pickup_store_map' );

		if ( empty( $store_name ) || empty( $store_address ) || empty( $pickup_date ) ) {
			return;
		}

		if ( $plain_text ) {
			echo "\n" . 'Pickup Details:' . "\n";
			echo 'Store Name: ' . esc_html( $store_name ) . "\n";
			echo 'Store Address: ' . esc_html( $store_address ) . "\n";
			echo 'Pickup Date: ' . esc_html( $pickup_date ) . "\n";
			if ( ! empty( $map_url ) ) {
				echo 'Map: ' . esc_url( $map_url ) . "\n";
			}
		} else {
			echo '<h2>Pickup Details</h2>';
			echo '<p><strong>Store Name:</strong> ' . esc_html( $store_name ) . '</p>';
			echo '<p><strong>Store Address:</strong> ' . esc_html( $store_address ) . '</p>';
			echo '<p><strong>Pickup Date:</strong> ' . esc_html( $pickup_date ) . '</p>';
			if ( ! empty( $map_url ) ) {
				echo '<p><strong>Map:</strong> <a href="' . esc_url( $map_url ) . '" target="_blank">View Map</a></p>';
			}
		}
	}
}

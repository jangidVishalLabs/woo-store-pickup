<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WSP_Email_Handler' ) ) {
	return;
}

class WSP_Email_Handler {
	/**
	 * Add Pickup details with woocommeerce email.
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
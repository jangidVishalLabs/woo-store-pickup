<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WSP_Checkout_Fields' ) ) {
	return;
}

/**
 * WSP_Checkout_Fields class.
 *
 * Handles rendering of pickup store and date selection fields on the checkout page,
 * validation of these fields, saving order metadata, and displaying pickup details
 * to both customers and administrators.
 *
 * @class WSP_Checkout_Fields
 * @version 1.0.0
 */
class WSP_Checkout_Fields {

	/**
	 * Check if the store pickup shipping method is selected.
	 *
	 * Verifies that the customer has selected a shipping method containing the store pickup identifier.
	 *
	 * @return bool True if store pickup is selected, false otherwise.
	 */
	private function is_store_pickup() {

		if ( ! WC()->session ) {
			return false;
		}

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( empty( $chosen_methods ) || ! is_array( $chosen_methods ) ) {
			return false;
		}

		foreach ( $chosen_methods as $method ) {
			if ( false !== strpos( $method, 'wsp_store_pickup' ) ) {
				return true;
			}
		}

		return false;
	}
	/**
	 * Get selected pickup stores for the current checkout session.
	 *
	 * Retrieves the stores assigned to the selected shipping method instance
	 * from the shipping zone configuration.
	 *
	 * @return array Array of pickup store IDs available for the selected shipping method.
	 */
	function wsp_get_selected_pickup_stores_for_checkout() {
		$chosen = WC()->session->get( 'chosen_shipping_methods' );
		if ( empty( $chosen[0] ) ) {
			return array();
		}
		if ( false === strpos( $chosen[0], 'wsp_store_pickup:' ) ) {
			return array();
		}
		list ( $method_id, $instance_id ) = explode( ':', $chosen[0] );
		$shipping_method                  = WC_Shipping_Zones::get_shipping_method( $instance_id );
		if ( ! $shipping_method ) {
			return array();
		}
		$assigned_stores = $shipping_method->get_option( 'assigned_stores', array() );
		return $assigned_stores;
	}


	/**
	 * Render pickup store and date selection fields on the checkout page.
	 *
	 * Displays a store selector dropdown and pickup date input field only if the
	 * store pickup shipping method is selected.
	 *
	 * @param WC_Checkout $checkout The WC_Checkout object.
	 * @return void
	 */
	public function render_fields( $checkout ) {

		echo '<div id="wsp-pickup-fields" class="wsp-checkout-fields">';
		echo '<h3>' . esc_html__( 'Store Pickup Details', 'woo-store-plugin' ) . '</h3>';

		// Store Dropdown
		woocommerce_form_field(
			'wsp_store_id',
			array(
				'type'     => 'select',
				'class'    => array( 'form-row-wide' ),
				'label'    => esc_html__( 'Select Store Location', 'woo-store-plugin' ),
				'required' => true,
				'options'  => $this->get_store_options_with_address(),
			),
			$checkout->get_value( 'wsp_store_id' )
		);

		// Pickup Date
		woocommerce_form_field(
			'wsp_pickup_date',
			array(
				'type'              => 'date',
				'class'             => array( 'form-row-wide' ),
				'label'             => esc_html__( 'Preferred Pickup Date', 'woo-store-plugin' ),
				'required'          => true,
				'custom_attributes' => array(
					'min' => date( 'Y-m-d', strtotime( '+1 day' ) ),
				),
			),
			$checkout->get_value( 'wsp_pickup_date' )
		);

		echo '</div>';
	}



	/**
	 * Get active store options with addresses for the dropdown.
	 *
	 * Retrieves pickup stores assigned to the selected shipping method and formats them
	 * as options for the store selection dropdown.
	 *
	 * @return array Array of store options formatted for WooCommerce form fields.
	 */
	private function get_store_options_with_address() {

		$store_ids = $this->wsp_get_selected_pickup_stores_for_checkout();

		$options = array(
			'' => esc_html__( 'Select a store', 'woo-store-plugin' ),
		);

		if ( empty( $store_ids ) ) {
			return $options;
		}

		$stores = get_posts(
			array(
				'post_type'      => 'pickup_store',
				'post__in'       => $store_ids,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		foreach ( $stores as $store ) {
			$address               = get_post_meta( $store->ID, '_store_address', true );
			$options[ $store->ID ] = $store->post_title;
		}

		return $options;
	}


	/**
	 * Validate pickup store and date selections at checkout.
	 *
	 * Ensures both store location and pickup date are selected before checkout can proceed.
	 * Displays error notices if validation fails.
	 *
	 * @return void
	 */
	public function validate_fields() {

		if ( ! $this->is_store_pickup() ) {
			return;
		}

		if ( empty( $_POST['wsp_store_id'] ) ) {
			wc_add_notice(
				esc_html__( 'Please select a store location for pickup.', 'woo-store-plugin' ),
				'error'
			);
		}

		if ( empty( $_POST['wsp_pickup_date'] ) ) {
			wc_add_notice(
				esc_html__( 'Please select a preferred pickup date.', 'woo-store-plugin' ),
				'error'
			);
		} else {
	$selected = strtotime( sanitize_text_field( wp_unslash( $_POST['wsp_pickup_date'] ) ) );
	}
}
	}

	/**
	 * Save pickup store and date metadata to the order.
	 *
	 * Stores the selected store location and pickup date along with store details
	 * (name, address, map URL) to the order metadata for future reference.
	 *
	 * @param WC_Order $order The order object being created.
	 * @return void
	 */
	public function save_fields( $order ) {

		if ( ! $this->is_store_pickup() ) {
			return;
		}

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$store_id = isset( $_POST['wsp_store_id'] )
			? sanitize_text_field( wp_unslash( $_POST['wsp_store_id'] ) )
			: '';

		$date = isset( $_POST['wsp_pickup_date'] )
			? sanitize_text_field( wp_unslash( $_POST['wsp_pickup_date'] ) )
			: '';

		$order->update_meta_data( '_pickup_store_id', $store_id );
		$order->update_meta_data( '_pickup_date', $date );

		$order->update_meta_data( '_pickup_store_name', get_the_title( $store_id ) );
		$order->update_meta_data( '_pickup_store_address', get_post_meta( $store_id, '_store_address', true ) );
		$order->update_meta_data( '_pickup_store_map', get_post_meta( $store_id, '_store_map_url', true ) );
	}
	/**
	 * Display pickup details in the admin order page.
	 *
	 * Shows pickup store information (name, address, date, and map) below the billing address
	 * in the WooCommerce order admin page.
	 *
	 * @param WC_Order $order The order object.
	 * @return void
	 */
	public static function display_admin_order_pickup_details( $order ) {
		$store_name    = $order->get_meta( '_pickup_store_name' );
		$store_address = $order->get_meta( '_pickup_store_address' );
		$pickup_date   = $order->get_meta( '_pickup_date' );
		$map_url       = $order->get_meta( '_pickup_store_map' );

		if ( $store_name ) {
			echo '<p><strong>' . esc_html__( 'Pickup Store:', 'woo-store-plugin' ) . '</strong> ' . esc_html( $store_name ) . '</p>';
		}

		if ( $store_address ) {
			echo '<p><strong>' . esc_html__( 'Store Address:', 'woo-store-plugin' ) . '</strong> ' . esc_html( $store_address ) . '</p>';
		}

		if ( $pickup_date ) {
			echo '<p><strong>' . esc_html__( 'Pickup Date:', 'woo-store-plugin' ) . '</strong> ' . esc_html( $pickup_date ) . '</p>';
		}
		if ( $map_url ) {
			echo '<p><strong>' . esc_html__( 'Store Location:', 'woo-store-plugin' ) . '</strong> <a href="' . esc_url( $map_url ) . '" target="_blank">' . esc_html__( 'View on Map', 'woo-store-plugin' ) . '</a></p>';
		}
	}
	/**
	 * Convert Google Maps URLs to embeddable iframe formats.
	 *
	 * Attempts to extract coordinates from Google Maps URLs and converts them to
	 * embeddable iframe URLs. Falls back to address-based embed if needed.
	 *
	 * @param string $url                The Google Maps URL to convert.
	 * @param string $fallback_address   Optional. Address to use if URL conversion fails.
	 * @return string The embeddable Google Maps URL or empty string.
	 */
	public static function wsp_convert_google_maps_to_embed( $url, $fallback_address = '' ) {

		if ( empty( $url ) && empty( $fallback_address ) ) {
			return '';
		}

		// 1. Already embed URL → use directly
		if ( false !== strpos( $url, 'google.com/maps/embed' ) ) {
			return $url;
		}

		// 2. Try extracting coordinates from full maps URL
		if ( ! empty( $url ) && preg_match( '/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $m ) ) {
			return 'https://www.google.com/maps?q=' . $m[1] . ',' . $m[2] . '&output=embed';
		}

		// 3. Short URLs (maps.app.goo.gl / goo.gl) → USE ADDRESS
		if ( ! empty( $fallback_address ) ) {
			return 'https://www.google.com/maps?q=' . urlencode( $fallback_address ) . '&output=embed';
		}

		// 4. Absolute fallback → do not embed
		return '';
	}


	/**
	 * Display pickup details on the customer order page.
	 *
	 * Renders pickup store information including name, address, pickup date, and embedded map
	 * for customers viewing their order on the thank you page or account order details.
	 *
	 * @param int $order_id The ID of the order being displayed.
	 * @return void
	 */
	public static function display_customer_pickup_details( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$store_name    = $order->get_meta( '_pickup_store_name' );
		$store_address = $order->get_meta( '_pickup_store_address' );
		$pickup_date   = $order->get_meta( '_pickup_date' );
		$map_url       = $order->get_meta( '_pickup_store_map' );

		if ( empty( $store_name ) ) {
			return;
		}
		?>
		<section class="wsp-customer-pickup-details">
			<h2><?php esc_html_e( 'Store Pickup Details', 'woo-store-plugin' ); ?></h2>
			<p>
				<strong><?php esc_html_e( 'Pickup Store:', 'woo-store-plugin' ); ?></strong>
				<?php echo esc_html( $store_name ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Store Address:', 'woo-store-plugin' ); ?></strong>
				<?php echo esc_html( $store_address ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Pickup Date:', 'woo-store-plugin' ); ?></strong>
				<?php echo esc_html( $pickup_date ); ?>
			</p>
			<?php if ( $map_url ) : ?>
				<?php $embed_url = self::wsp_convert_google_maps_to_embed( $map_url, $store_address ); ?>
			<div style="margin-top:15px;">
				<strong><?php esc_html_e( 'Store Location:', 'woo-store-plugin' ); ?></strong>
				<iframe
					src="<?php echo esc_url( $embed_url ); ?>"
					width="100%"
					height="300"
					style="border:0; margin-top:10px;"
					loading="lazy"
					referrerpolicy="no-referrer-when-downgrade">
				</iframe>
				<p style="margin-top:8px;">
				<a href="<?php echo esc_url( $map_url ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Open in Google Maps', 'woo-store-plugin' ); ?>
				</a>
			</p>
			</div>
		<?php endif; ?>
		</section>

		<?php
	}

	/**
	 * Handle AJAX request to get pickup stores for the selected shipping method.
	 *
	 * Returns HTML options for the pickup store dropdown based on the currently
	 * selected shipping method instance.
	 *
	 * @return void
	 */
	public function wsp_get_pickup_stores_ajax() {

		// Initialize WC if needed
		if ( ! did_action( 'woocommerce_init' ) ) {
			wp_send_json_error( 'WooCommerce not initialized' );
		}

		if ( ! WC()->session ) {
			wp_send_json_error( 'No session available' );
		}

		$chosen = WC()->session->get( 'chosen_shipping_methods' );

		if ( empty( $chosen[0] ) || false === strpos( $chosen[0], 'wsp_store_pickup:' ) ) {
			wp_send_json_success( '<option value="">Select a store</option>' );
		}

		list( , $instance_id ) = explode( ':', $chosen[0] );

		$shipping_method = WC_Shipping_Zones::get_shipping_method( $instance_id );

		if ( ! $shipping_method ) {
			wp_send_json_success( '<option value="">Select a store</option>' );
		}

		$store_ids = (array) $shipping_method->get_option( 'assigned_stores', array() );
		if ( empty( $store_ids ) ) {
	wp_send_json_success(
		'<option value="">' . esc_html__( 'No stores available', 'woo-store-plugin' ) . '</option>'
	);
}


		$options = '<option value="">Select a store</option>';
			
		if ( $store_ids ) {
			$stores = get_posts(
				array(
					'post_type'   => 'pickup_store',
					'post__in'    => $store_ids,
					'post_status' => 'publish',
				)
			);

			foreach ( $stores as $store ) {
				$options .= sprintf(
					'<option value="%d">%s</option>',
					$store->ID,
					esc_html( $store->post_title )
				);
			}
		}

		wp_send_json_success( $options );
	}
}
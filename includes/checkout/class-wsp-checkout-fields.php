<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WSP_Checkout_Fields' ) ) {
	return;
}

/**
 * WSP_Checkout_Fields Class.
 */
class WSP_Checkout_Fields {

	/**
	 * Check if store pickup is selected.
	 */
	private function is_store_pickup() {

		if ( ! WC()->session ) {
			return false;
		}

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

		return is_array( $chosen_methods ) && in_array( 'wsp_store_pickup', $chosen_methods, true );
	}

	/**
	 * Render Checkout Fields.
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
				'options'  => $this->get_store_options(),
			),
			$checkout->get_value( 'wsp_store_id' )
		);

		// Pickup Date
		woocommerce_form_field(
			'wsp_pickup_date',
			array(
				'type'        => 'date',
				'class'       => array( 'form-row-wide' ),
				'label'       => esc_html__( 'Preferred Pickup Date', 'woo-store-plugin' ),
				'required'    => true,
				'custom_attributes' => array(
					'min' => date( 'Y-m-d', strtotime( '+1 day' ) ),
				),
			),
			$checkout->get_value( 'wsp_pickup_date' )
		);

		echo '</div>';
	}

	/**
	 * Get Active Store Options.
	 */
	private function get_store_options() {

		$stores = get_posts(
			array(
				'post_type'      => 'pickup_store',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		$options = array(
			'' => esc_html__( 'Select a store', 'woo-store-plugin' ),
		);

		foreach ( $stores as $store ) {
			$options[ $store->ID ] = $store->post_title;
		}

		return $options;
	}

	/**
	 * Validate Checkout Fields.
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
		}
	}

	/**
	 * Save order meta.
	 */
	public function save_fields( $order_id ) {

		if ( ! $this->is_store_pickup() ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
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

		// Snapshot store details
		$order->update_meta_data( '_pickup_store_name', get_the_title( $store_id ) );
		$order->update_meta_data( '_pickup_store_address', get_post_meta( $store_id, '_store_address', true ) );
		$order->update_meta_data( '_pickup_store_map', get_post_meta( $store_id, '_store_map_url', true ) );

		$order->save();
	}
	/**
	 * Store pickup details in admin order page.
	 */
	public static function display_admin_order_pickup_details( $order ) {
		error_log( 'Displaying pickup details in admin order page.' );
		$store_name	= $order->get_meta( '_pickup_store_name' );
		$store_address = $order->get_meta( '_pickup_store_address' );
		$pickup_date   = $order->get_meta( '_pickup_date' );
		$map_url	   = $order->get_meta( '_pickup_store_map' );

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
	 * Display pickup details for customer
	 */
	public static function display_customer_pickup_details( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$store_name	= $order->get_meta( '_pickup_store_name' );
		$store_address = $order->get_meta( '_pickup_store_address' );
		$pickup_date   = $order->get_meta( '_pickup_date' );
		$map_url	   = $order->get_meta( '_pickup_store_map' );

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
				<p>
					<strong><?php esc_html_e( 'Store Location:', 'woo-store-plugin' ); ?></strong>
					<a href="<?php echo esc_url( $map_url ); ?>" target="_blank">
						<?php esc_html_e( 'View on Map', 'woo-store-plugin' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</section>

		<?php
	}
}


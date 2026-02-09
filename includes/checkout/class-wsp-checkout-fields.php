<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WSP_Checkout_Fields' ) ) {
	return;
}

class WSP_Checkout_Fields {

	private function is_store_pickup() {
		if ( ! WC()->session ) {
			return false;
		}
		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
		if ( empty( $chosen_methods ) || ! is_array( $chosen_methods ) ) {
			return false;
		}
		foreach ( $chosen_methods as $method ) {
			if ( strpos( $method, 'wsp_store_pickup' ) !== false ) {
				return true;
			}
		}
		return false;
	}

	private function wsp_get_selected_pickup_stores_for_checkout() {
		$chosen = WC()->session->get( 'chosen_shipping_methods' );
		if ( empty( $chosen[0] ) || strpos( $chosen[0], 'wsp_store_pickup:' ) === false ) {
			return array();
		}
		list( , $instance_id ) = explode( ':', $chosen[0] );
		$shipping_method       = WC_Shipping_Zones::get_shipping_method( $instance_id );
		if ( ! $shipping_method ) {
			return array();
		}
		return (array) $shipping_method->get_option( 'assigned_stores', array() );
	}

	// ── Render ──────────────────────────────────────────────────
	public function render_fields( $checkout ) {
		// Enqueue inline CSS for dropdown text handling
		wp_add_inline_style( 'woocommerce-general', '
			#wsp_store_id {
				white-space: normal !important;
				height: auto !important;
				min-height: 40px;
			}
			#wsp_store_id option {
				white-space: normal;
				overflow: hidden;
				text-overflow: ellipsis;
				padding: 8px 12px;
			}
		' );

		echo '<div id="wsp-pickup-fields" class="wsp-checkout-fields">';
		echo '<h3>' . esc_html__( 'Store Pickup Details', 'woo-store-plugin' ) . '</h3>';

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
	 * Truncate store title for display while keeping full title as tooltip.
	 * Max 60 chars for readability in dropdown.
	 */
	private function truncate_store_title( $title, $max_length = 60 ) {
		if ( mb_strlen( $title ) <= $max_length ) {
			return $title;
		}
		return mb_substr( $title, 0, $max_length - 3 ) . '...';
	}

	private function get_store_options_with_address() {
		$store_ids = $this->wsp_get_selected_pickup_stores_for_checkout();
		$options   = array( '' => esc_html__( 'Select a store', 'woo-store-plugin' ) );

		if ( empty( $store_ids ) ) {
			return $options;
		}

		$stores = get_posts( array(
			'post_type'      => 'pickup_store',
			'post__in'       => $store_ids,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );

		foreach ( $stores as $store ) {
			// Truncate title to 60 chars for clean display
			$display_title = $this->truncate_store_title( $store->post_title, 60 );
			$options[ $store->ID ] = $display_title;
		}

		return $options;
	}

	// ── Validate ────────────────────────────────────────────────
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
			$selected = strtotime( sanitize_text_field( $_POST['wsp_pickup_date'] ) );
			$tomorrow = strtotime( date( 'Y-m-d', strtotime( '+1 day' ) ) );

			if ( $selected < $tomorrow ) {
				wc_add_notice(
					esc_html__( 'Same-day or past-date pickup is not allowed. Please select a future date.', 'woo-store-plugin' ),
					'error'
				);
			}
		}
	}

	// ── Save ────────────────────────────────────────────────────
	public function save_fields( $order ) {
		if ( ! $this->is_store_pickup() || ! $order instanceof WC_Order ) {
			return;
		}

		$store_id = isset( $_POST['wsp_store_id'] )
			? sanitize_text_field( wp_unslash( $_POST['wsp_store_id'] ) )
			: '';

		$date = isset( $_POST['wsp_pickup_date'] )
			? sanitize_text_field( wp_unslash( $_POST['wsp_pickup_date'] ) )
			: '';

		$order->update_meta_data( '_pickup_store_id',      $store_id );
		$order->update_meta_data( '_pickup_date',          $date );
		$order->update_meta_data( '_pickup_store_name',    get_the_title( $store_id ) );
		$order->update_meta_data( '_pickup_store_address', get_post_meta( $store_id, '_store_address', true ) );
		$order->update_meta_data( '_pickup_store_map',     get_post_meta( $store_id, '_store_map_url',  true ) );
	}

	// ── Admin order detail ──────────────────────────────────────
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

	// ── Customer order detail (thank-you / my-account) ─────────
	public static function wsp_convert_google_maps_to_embed( $url, $fallback_address = '' ) {
		if ( empty( $url ) && empty( $fallback_address ) ) {
			return '';
		}
		if ( strpos( $url, 'google.com/maps/embed' ) !== false ) {
			return $url;
		}
		if ( ! empty( $url ) && preg_match( '/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $m ) ) {
			return 'https://www.google.com/maps?q=' . $m[1] . ',' . $m[2] . '&output=embed';
		}
		if ( ! empty( $fallback_address ) ) {
			return 'https://www.google.com/maps?q=' . urlencode( $fallback_address ) . '&output=embed';
		}
		return '';
	}

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
			<p><strong><?php esc_html_e( 'Pickup Store:', 'woo-store-plugin' ); ?></strong> <?php echo esc_html( $store_name ); ?></p>
			<p><strong><?php esc_html_e( 'Store Address:', 'woo-store-plugin' ); ?></strong> <?php echo esc_html( $store_address ); ?></p>
			<p><strong><?php esc_html_e( 'Pickup Date:', 'woo-store-plugin' ); ?></strong> <?php echo esc_html( $pickup_date ); ?></p>
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

	// ── AJAX: reload store dropdown when shipping method changes ─
	public function wsp_get_pickup_stores_ajax() {
		if ( ! WC()->session ) {
			wp_send_json_error( 'No session available' );
		}

		$chosen = WC()->session->get( 'chosen_shipping_methods' );

		if ( empty( $chosen[0] ) || strpos( $chosen[0], 'wsp_store_pickup:' ) === false ) {
			wp_send_json_success( '<option value="">' . esc_html__( 'Select a store', 'woo-store-plugin' ) . '</option>' );
		}

		list( , $instance_id ) = explode( ':', $chosen[0] );
		$shipping_method       = WC_Shipping_Zones::get_shipping_method( $instance_id );

		if ( ! $shipping_method ) {
			wp_send_json_success( '<option value="">' . esc_html__( 'Select a store', 'woo-store-plugin' ) . '</option>' );
		}

		$store_ids = (array) $shipping_method->get_option( 'assigned_stores', array() );

		if ( empty( $store_ids ) ) {
			wp_send_json_success( '<option value="">' . esc_html__( 'No stores available', 'woo-store-plugin' ) . '</option>' );
		}

		$options = '<option value="">' . esc_html__( 'Select a store', 'woo-store-plugin' ) . '</option>';

		$stores = get_posts( array(
			'post_type'   => 'pickup_store',
			'post__in'    => $store_ids,
			'post_status' => 'publish',
		) );

		foreach ( $stores as $store ) {
			// Truncate long titles for AJAX response too
			$display_title = $this->truncate_store_title( $store->post_title, 60 );
			$options .= sprintf(
				'<option value="%d">%s</option>',
				$store->ID,
				esc_html( $display_title )
			);
		}

		wp_send_json_success( $options );
	}
}
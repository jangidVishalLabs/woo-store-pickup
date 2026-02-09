<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WSP_Store_Meta' ) ) {
	return;
}

class WSP_Store_Meta {

	public function add_meta_boxes() {
		add_meta_box(
			'pickup_store_details',
			__( 'Store Details', 'woo-store-plugin' ),
			array( $this, 'render_meta_box' ),
			'pickup_store'
		);

		if ( current_user_can( 'manage_options' ) ) {
			add_meta_box(
				'pickup_store_assignment',
				__( 'Store Assignment (Admin Only)', 'woo-store-plugin' ),
				array( $this, 'render_assignment_meta_box' ),
				'pickup_store',
				'side'
			);
		}
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'wsp_store_meta', 'wsp_store_meta_nonce' );

		$address = get_post_meta( $post->ID, '_store_address', true );
		$map_url = get_post_meta( $post->ID, '_store_map_url', true );
		$lat     = get_post_meta( $post->ID, '_store_lat',     true );
		$lng     = get_post_meta( $post->ID, '_store_lng',     true );
		?>
		<p>
			<label><strong><?php esc_html_e( 'Store Address', 'woo-store-plugin' ); ?></strong></label>
			<textarea name="store_address" style="width:100%;"><?php echo esc_textarea( $address ); ?></textarea>
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Google Map URL', 'woo-store-plugin' ); ?></strong></label><br>
			<input type="text" name="store_map_url" value="<?php echo esc_attr( $map_url ); ?>" style="width:100%;" />
			<small>Example: https://maps.google.com/?q=...</small>
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Latitude', 'woo-store-plugin' ); ?></strong></label><br>
			<input type="text" name="store_lat" value="<?php echo esc_attr( $lat ); ?>" />
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Longitude', 'woo-store-plugin' ); ?></strong></label><br>
			<input type="text" name="store_lng" value="<?php echo esc_attr( $lng ); ?>" />
		</p>
		<?php
	}

	public function render_assignment_meta_box( $post ) {
		$assigned_owner = get_post_meta( $post->ID, '_assigned_shop_owner', true );
		$zone_id        = get_post_meta( $post->ID, '_pickup_zone_id',      true );
		$shop_owners    = get_users( array( 'role' => 'shop_owner' ) );
		?>
		<p>
			<label><strong><?php esc_html_e( 'Assign to Shop Owner', 'woo-store-plugin' ); ?></strong></label><br>
			<select name="assigned_shop_owner" style="width:100%;">
				<option value="">— <?php esc_html_e( 'Select Owner', 'woo-store-plugin' ); ?> —</option>
				<?php foreach ( $shop_owners as $owner ) : ?>
					<option value="<?php echo esc_attr( $owner->ID ); ?>" <?php selected( $assigned_owner, $owner->ID ); ?>>
						<?php echo esc_html( $owner->display_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['wsp_store_meta_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['wsp_store_meta_nonce'], 'wsp_store_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( get_post_type( $post_id ) !== 'pickup_store' ) {
			return;
		}

		// Address
		update_post_meta(
			$post_id,
			'_store_address',
			isset( $_POST['store_address'] ) ? sanitize_textarea_field( $_POST['store_address'] ) : ''
		);

		// Map URL
		update_post_meta(
			$post_id,
			'_store_map_url',
			isset( $_POST['store_map_url'] ) ? esc_url_raw( $_POST['store_map_url'] ) : ''
		);

		// Coordinates
		update_post_meta( $post_id, '_store_lat', sanitize_text_field( $_POST['store_lat'] ?? '' ) );
		update_post_meta( $post_id, '_store_lng', sanitize_text_field( $_POST['store_lng'] ?? '' ) );

		// Admin-only: owner + zone assignment
		if ( current_user_can( 'manage_options' ) ) {
			update_post_meta( $post_id, '_assigned_shop_owner', absint( $_POST['assigned_shop_owner'] ?? 0 ) );
			update_post_meta( $post_id, '_pickup_zone_id',      absint( $_POST['pickup_zone_id']      ?? 0 ) );
		}
	}

	public function mandatory_title( $data, $postarr ) {
		if ( $data['post_type'] !== 'pickup_store' ) {
			return $data;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $data;
		}
		if ( empty( trim( $data['post_title'] ) ) ) {
			// Set a placeholder title so the post can save
			$data['post_title'] = __( '(no title)', 'woo-store-plugin' );

			// Store the error in a transient so we can show it after redirect
			set_transient( 'wsp_store_title_error_' . get_current_user_id(), true, 45 );
		}
		return $data;
	}

	/**
	 * Hook: admin_notices
	 * Display the validation error inline instead of using wp_die().
	 */
	public function show_title_validation_error() {
		$user_id = get_current_user_id();
		if ( get_transient( 'wsp_store_title_error_' . $user_id ) ) {
			delete_transient( 'wsp_store_title_error_' . $user_id );
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Store name is mandatory.', 'woo-store-plugin' ); ?></strong>
					<?php esc_html_e( 'Please provide a name for this pickup store.', 'woo-store-plugin' ); ?>
				</p>
			</div>
			<?php
		}
	}

	public function on_delete_remove_zone_mapping( $post_id ) {
		if ( get_post_type( $post_id ) !== 'pickup_store' ) {
			return;
		}
		delete_post_meta( $post_id, '_pickup_zone_id' );
		delete_post_meta( $post_id, '_assigned_shop_owner' );
	}

	public function custom_status_columns( $columns ) {
		$columns['status'] = __( 'Status', 'woo-store-plugin' );
		return $columns;
	}

	public function render_custom_status_columns( $column, $post_id ) {
		if ( $column === 'status' ) {
			echo esc_html( get_post_status( $post_id ) );
		}
	}
}
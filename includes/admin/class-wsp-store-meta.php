<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( class_exists( 'WSP_Store_Meta' ) ) {
	return;
}

/**
 * WSP_Store_Meta class.
 *
 * Handles meta box registration, rendering, and saving for the Pickup Store CPT.
 * Manages store details (address, map URL, coordinates) and admin-only assignment options.
 *
 * @class WSP_Store_Meta
 * @version 1.0.0
 */
class WSP_Store_Meta {

	/**
	 * Register meta boxes for the Pickup Store post type.
	 *
	 * Adds two meta boxes: one for store details (visible to all) and one for
	 * admin-only store assignment to shipping zones and shop owners.
	 *
	 * @return void
	 */
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

	/**
	 * Render the store details meta box.
	 *
	 * Displays fields for store address, Google Maps URL, and latitude/longitude coordinates.
	 *
	 * @param WP_Post $post The post object for the current store.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'wsp_store_meta', 'wsp_store_meta_nonce' );
		$address = get_post_meta( $post->ID, '_store_address', true );
		$map_url = get_post_meta( $post->ID, '_store_map_url', true );
		$lat     = get_post_meta( $post->ID, '_store_lat', true );
		$lng     = get_post_meta( $post->ID, '_store_lng', true );
		?>
		<p>
			<label><strong>Store Address</strong></label>
			<textarea name="store_address" style="width:100%">
				<?php echo esc_textarea( $address ); ?>
			</textarea>
		</p>
		<p>
		<label><strong>Google Map URL</strong></label><br>
		<input type="text" name="store_map_url" value="<?php echo esc_attr( $map_url ); ?>" style="width:100%;" />
		<small>Example: https://maps.google.com/?q=...</small>
		</p>

		<p>
		<label><strong>Latitude</strong></label><br>
		<input type="text" name="store_lat" value="<?php echo esc_attr( $lat ); ?>" />
		</p>

		<p>
		<label><strong>Longitude</strong></label><br>
		<input type="text" name="store_lng" value="<?php echo esc_attr( $lng ); ?>" />
		</p>
		<?php
	}
	/**
	 * Render the admin assignment meta box.
	 *
	 * Displays dropdowns for assigning the store to a shop owner and selecting a shipping zone.
	 * Only visible to administrators.
	 *
	 * @param WP_Post $post The post object for the current store.
	 * @return void
	 */
	public function render_assignment_meta_box( $post ) {
		$assigned_owner = get_post_meta( $post->ID, '_assigned_shop_owner', true );
		$zone_id        = get_post_meta( $post->ID, '_pickup_zone_id', true );

		// Get shop owners
		$shop_owners = get_users( array( 'role' => 'shop_owner' ) );

		// Get shipping zones
		$zones = WC_Shipping_Zones::get_zones();
		?>
		<p>
			<label><strong>Assign to Shop Owner</strong></label><br>
			<select name="assigned_shop_owner" style="width:100%;">
				<option value="">— Select Owner —</option>
				<?php foreach ( $shop_owners as $owner ) : ?>
					<option value="<?php echo esc_attr( $owner->ID ); ?>" <?php selected( $assigned_owner, $owner->ID ); ?>>
						<?php echo esc_html( $owner->display_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label><strong>Shipping Zone</strong></label><br>
			<select name="pickup_zone_id" style="width:100%;">
				<option value="">— Select Zone —</option>
				<?php foreach ( $zones as $zone_data ) : ?>
					<option value="<?php echo esc_attr( $zone_data['zone_id'] ); ?>" <?php selected( $zone_id, $zone_data['zone_id'] ); ?>>
						<?php echo esc_html( $zone_data['zone_name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}


	/**
	 * Save store meta data.
	 *
	 * Saves all store-related meta data including address, map URL, coordinates,
	 * and admin-only assignment fields. Includes nonce verification and permission checks.
	 *
	 * @param int $post_id The ID of the post being saved.
	 * @return void
	 */
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
		if ( get_post_type( $post_id ) != 'pickup_store' ) {
			return;
		}

		// Address (optional)
		update_post_meta(
			$post_id,
			'_store_address',
			isset( $_POST['store_address'] )
			? sanitize_textarea_field( $_POST['store_address'] )
			: ''
		);

			// Map URL (optional & sanitized)
			$map_url = isset( $_POST['store_map_url'] ) ? esc_url_raw( $_POST['store_map_url'] ) : '';
			update_post_meta( $post_id, '_store_map_url', $map_url );

			// Latitude / Longitude
			update_post_meta( $post_id, '_store_lat', sanitize_text_field( $_POST['store_lat'] ?? '' ) );
			update_post_meta( $post_id, '_store_lng', sanitize_text_field( $_POST['store_lng'] ?? '' ) );

			// Admin-only fields
		if ( current_user_can( 'manage_options' ) ) {
			update_post_meta( $post_id, '_assigned_shop_owner', absint( $_POST['assigned_shop_owner'] ?? 0 ) );
			update_post_meta( $post_id, '_pickup_zone_id', absint( $_POST['pickup_zone_id'] ?? 0 ) );
		}
	}

	/**
	 * Enforce mandatory store title.
	 *
	 * Validates that the store post has a non-empty title before saving.
	 * Prevents saving stores without a name.
	 *
	 * @param array $data    The post data being saved.
	 * @param array $postarr The array of post values and meta values.
	 * @return array The modified or unmodified post data array.
	 */
	public function mandatory_title( $data, $postarr ) {
		// Only validate our CPT
		if ( $data['post_type'] !== 'pickup_store' ) {
			return $data;
		}

		// Allow auto-drafts
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $data;
		}

		if ( empty( trim( $data['post_title'] ) ) ) {
			wp_die(
				__( 'Store name is mandatory.', 'wsp' ),
				__( 'Validation Error', 'wsp' ),
				array( 'back_link' => true )
			);
		}

		return $data;
	}

	/**
	 * Remove zone mapping when a store is deleted.
	 *
	 * Cleans up associated meta data when a store post is deleted,
	 * including zone and shop owner assignments.
	 *
	 * @param int $post_id The ID of the post being deleted.
	 * @return void
	 */
	public function on_delete_remove_zone_mapping( $post_id ) {
		if ( get_post_type( $post_id ) != 'pickup_store' ) {
			return;
		}

		// Remove zone mapping
		delete_post_meta( $post_id, '_pickup_zone_id' );
		delete_post_meta( $post_id, '_assigned_shop_owner' );
	}

	/**
	 * Add custom status column to the Pickup Store list table.
	 *
	 * Adds a "Status" column to the store post type listing in the admin.
	 *
	 * @param array $columns The array of column names.
	 * @return array Modified array of column names.
	 */
	public function custom_status_columns( $columns ) {
		error_log( 'custom_status_columns called' . print_r( $columns, true ) );
		$columns['status'] = 'Status';
		return $columns;
	}

	/**
	 * Render custom status column values.
	 *
	 * Displays the post status for each store in the status column of the list table.
	 *
	 * @param string $column   The name of the column being rendered.
	 * @param int    $post_id  The ID of the post.
	 * @return void
	 */
	public function render_custom_status_columns( $column, $post_id ) {
		if ( $column === 'status' ) {
			echo esc_html( get_post_status( $post_id ) );
		}
	}
}

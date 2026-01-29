<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( class_exists( 'WSP_Store_Meta' ) ) {
	return;
}

/**
 * WSP Store Meta Class.
 */
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
	 * Admin Assignment Meta Box.
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


	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['wsp_store_meta_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['wsp_store_meta_nonce'], 'wsp_store_meta' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( get_post_type( $post_id ) != 'pickup_store' ) return;


		if ( isset( $_POST['store_address'] ) ) {
			update_post_meta( $post_id, '_store_address', sanitize_textarea_field( $_POST['store_address'] ) );
		}

		if ( isset( $_POST['store_map_url'] ) ) {
        	update_post_meta( $post_id, '_store_map_url', esc_url_raw( $_POST['store_map_url'] ) );
    	}

    	if ( isset( $_POST['store_lat'] ) ) {
        	update_post_meta( $post_id, '_store_lat', sanitize_text_field( $_POST['store_lat'] ) );
    	}

    	if ( isset( $_POST['store_lng'] ) ) {
        	update_post_meta( $post_id, '_store_lng', sanitize_text_field( $_POST['store_lng'] ) );
    	}

		// Admin-only fields
		if ( current_user_can( 'manage_options' ) ) {
			if ( isset( $_POST['assigned_shop_owner'] ) ) {
				update_post_meta( $post_id, '_assigned_shop_owner', absint( $_POST['assigned_shop_owner'] ) );
			}

			if ( isset( $_POST['pickup_zone_id'] ) ) {
				update_post_meta( $post_id, '_pickup_zone_id', absint( $_POST['pickup_zone_id'] ) );
			}
		

	}

}
}
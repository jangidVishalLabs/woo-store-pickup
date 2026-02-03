<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WSP_Store_Meta' ) ) {
	return;
}

/**
 * WSP_Store_Meta class.
 *
 * Handles meta box registration, rendering, and saving for the Pickup Store CPT.
 * Manages store details (address, map URL, coordinates) and admin-only assignment options.
 * Implements Singleton pattern to ensure only one instance exists.
 *
 * @class WSP_Store_Meta
 * @version 1.0.0
 */
class WSP_Store_Meta {

	/**
	 * The single instance of the class.
	 *
	 * @var WSP_Store_Meta
	 */
	private static $instance = null;

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {}

	/**
	 * Get the single instance of the class.
	 *
	 * @return WSP_Store_Meta The single instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Prevent cloning of the instance.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing of the instance.
	 *
	 * @return void
	 */
	private function __wakeup() {}

	public function add_meta_boxes() {
		add_meta_box(
			'pickup_store_details',
			__( 'Store Details', 'woo-store-plugin' ),
			array( $this, 'render_meta_box' ),
			'pickup_store'
		);

		if ( current_user_can( 'manage_options' ) === true ) {
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
			<textarea name="store_address" style="width:100%;"><?php echo esc_textarea( $address ); ?></textarea>
		</p>

		<p>
			<label><strong>Google Map URL</strong></label><br>
			<input type="text" name="store_map_url" value="<?php echo esc_attr( $map_url ); ?>" style="width:100%;" />
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

	public function render_assignment_meta_box( $post ) {
		$assigned_owner = (int) get_post_meta( $post->ID, '_assigned_shop_owner', true );
		$zone_id        = (int) get_post_meta( $post->ID, '_pickup_zone_id', true );

		$shop_owners = get_users( array( 'role' => 'shop_owner' ) );
		$zones       = WC_Shipping_Zones::get_zones();
		?>
		<p>
			<label><strong>Assign to Shop Owner</strong></label><br>
			<select name="assigned_shop_owner" style="width:100%;">
				<option value=""><?php __('— Select Owner —', 'wsp' ); ?></option>
				<?php foreach ( $shop_owners as $owner ) : ?>
					<option value="<?php echo esc_attr( $owner->ID ); ?>" <?php selected( $assigned_owner, (int) $owner->ID, true ); ?>>
						<?php echo esc_html( $owner->display_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label><strong><?php __('Shipping Zone', 'wsp'); ?></strong></label><br>
			<select name="pickup_zone_id" style="width:100%;">
				<option value="">— Select Zone —</option>
				<?php foreach ( $zones as $zone_data ) : ?>
					<option value="<?php echo esc_attr( $zone_data['zone_id'] ); ?>" <?php selected( $zone_id, (int) $zone_data['zone_id'], true ); ?>>
						<?php echo esc_html( $zone_data['zone_name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	public function save_meta( $post_id ) {

		if ( isset( $_POST['wsp_store_meta_nonce'] ) === false ) {
			return;
		}

		if ( wp_verify_nonce( $_POST['wsp_store_meta_nonce'], 'wsp_store_meta' ) !== true ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE === true ) {
			return;
		}

		if ( get_post_type( $post_id ) !== 'pickup_store' ) {
			return;
		}

		update_post_meta(
			$post_id,
			'_store_address',
			isset( $_POST['store_address'] )
				? sanitize_textarea_field( wp_unslash( $_POST['store_address'] ) )
				: ''
		);

		update_post_meta(
			$post_id,
			'_store_map_url',
			isset( $_POST['store_map_url'] )
				? esc_url_raw( wp_unslash( $_POST['store_map_url'] ) )
				: ''
		);

		update_post_meta(
			$post_id,
			'_store_lat',
			sanitize_text_field( wp_unslash( $_POST['store_lat'] ?? '' ) )
		);

		update_post_meta(
			$post_id,
			'_store_lng',
			sanitize_text_field( wp_unslash( $_POST['store_lng'] ?? '' ) )
		);

		if ( current_user_can( 'manage_options' ) === true ) {
			update_post_meta( $post_id, '_assigned_shop_owner', absint( wp_unslash( $_POST['assigned_shop_owner'] ?? 0 ) ) );
			update_post_meta( $post_id, '_pickup_zone_id', absint( wp_unslash( $_POST['pickup_zone_id'] ?? 0 ) ) );
		}
	}

	public function mandatory_title( $data, $postarr ) {

		if ( $data['post_type'] !== 'pickup_store' ) {
			return $data;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE === true ) {
			return $data;
		}

		if ( trim( (string) $data['post_title'] ) === '' ) {
			wp_die(
				__( 'Store name is mandatory.', 'wsp' ),
				__( 'Validation Error', 'wsp' ),
				array( 'back_link' => true )
			);
		}

		return $data;
	}

	public function on_delete_remove_zone_mapping( $post_id ) {
		if ( get_post_type( $post_id ) !== 'pickup_store' ) {
			return;
		}

		delete_post_meta( $post_id, '_pickup_zone_id' );
		delete_post_meta( $post_id, '_assigned_shop_owner' );
	}

	public function custom_status_columns( $columns ) {
		$columns['status'] = 'Status';
		return $columns;
	}

	public function render_custom_status_columns( $column, $post_id ) {
		if ( $column === 'status' ) {
			echo esc_html( get_post_status( $post_id ) );
		}
	}
}

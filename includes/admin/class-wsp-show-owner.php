<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WSP_Shop_Owner' ) ) {
	return;
}

class WSP_Shop_Owner {

	public function register_role() {
		if ( get_role( 'shop_owner' ) ) {
			return;
		}

		add_role(
			'shop_owner',
			__( 'Shop Owner', 'woo-store-plugin' ),
			array(
				'read'                  => true,
				'edit_posts'            => true,

				// Orders
				'read_shop_order'       => true,
				'edit_shop_order'       => true,
				'edit_shop_orders'      => true,

				// Pickup Stores
				'edit_pickup_store'     => true,
				'edit_pickup_stores'    => true,
				'publish_pickup_stores' => true,
				'delete_pickup_stores'  => true,
				'read_pickup_store'     => true,
			)
		);
	}

	/**
	 * Restrict Pickup Store list to assigned stores
	 */
	public function filter_pickup_store_list( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->get( 'post_type' ) !== 'pickup_store' ) {
			return;
		}

		$user = wp_get_current_user();

		// Only filter for shop owners, not admins
		if ( ! in_array( 'shop_owner', (array) $user->roles, true ) ) {
			return;
		}

		$query->set(
			'meta_query',
			array(
				array(
					'key'   => '_assigned_shop_owner',
					'value' => get_current_user_id(),
				),
			)
		);
	}

	/**
	 * Filter HPOS orders by shop owner's stores
	 */
	public function filter_orders_by_store_hpos( $clauses, $query ) {
		global $wpdb;

		// Only run in admin
		if ( ! is_admin() ) {
			return $clauses;
		}

		$user = wp_get_current_user();

		// Only filter for shop owners
		if ( ! in_array( 'shop_owner', (array) $user->roles, true ) ) {
			return $clauses;
		}

		$store_ids = $this->get_store_ids_by_owner( get_current_user_id() );

		if ( empty( $store_ids ) ) {
			// No stores = no orders
			$clauses['where'] .= ' AND 1=0';
			return $clauses;
		}

		$ids = implode( ',', array_map( 'absint', $store_ids ) );

		// Add JOIN if not already there
		if ( strpos( $clauses['join'], 'shop_owner_store_meta' ) === false ) {
			$clauses['join'] .= " INNER JOIN {$wpdb->prefix}wc_orders_meta AS shop_owner_store_meta 
				ON {$wpdb->prefix}wc_orders.id = shop_owner_store_meta.order_id";
		}

		// Add WHERE condition
		$clauses['where'] .= " AND shop_owner_store_meta.meta_key = '_pickup_store_id' 
			AND shop_owner_store_meta.meta_value IN ({$ids})";

		return $clauses;
	}

	/**
	 * Get stores assigned to shop owner
	 */
	private function get_store_ids_by_owner( $user_id ) {
		return get_posts(
			array(
				'post_type'   => 'pickup_store',
				'fields'      => 'ids',
				'meta_key'    => '_assigned_shop_owner',
				'meta_value'  => $user_id,
				'numberposts' => -1,
			)
		);
	}

	/**
	 * Prevent shop owner from changing protected meta
	 */
	public function prevent_shop_owner_meta_change( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		$protected_keys = array( '_assigned_shop_owner', '_pickup_zone_id' );

		if ( ! in_array( $meta_key, $protected_keys, true ) ) {
			return $check;
		}

		// Block shop owners, allow admins
		if ( current_user_can( 'edit_pickup_store' ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return $check;
	}

	/**
	 * Prevent shop owner from adding protected meta
	 */
	public function wsp_prevent_shop_owner_add_meta( $check, $object_id, $meta_key, $meta_value, $unique ) {
		$protected_keys = array( '_assigned_shop_owner', '_pickup_zone_id' );

		if ( in_array( $meta_key, $protected_keys, true ) &&
			current_user_can( 'edit_pickup_store' ) &&
			! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return $check;
	}

	/**
	 * Hide admin menus for shop owner
	 */
	public function cleanup_admin_menus() {
		// Only for shop owners, not admins
		if ( ! current_user_can( 'edit_pickup_store' ) || current_user_can( 'manage_options' ) ) {
			return;
		}

		remove_menu_page( 'woocommerce-settings' );
		remove_menu_page( 'woocommerce-reports' );
		remove_menu_page( 'tools.php' );
	}
}

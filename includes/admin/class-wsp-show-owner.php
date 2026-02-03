<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WSP_Shop_Owner' ) ) {
	return;
}

/**
 * WSP_Shop_Owner class.
 *
 * Manages the Shop Owner user role, handles role-based filtering of pickup stores and orders,
 * and prevents shop owners from modifying protected metadata.
 * Implements Singleton pattern to ensure only one instance exists.
 *
 * @class WSP_Shop_Owner
 * @version 1.0.0
 */
class WSP_Shop_Owner {

	/**
	 * The single instance of the class.
	 *
	 * @var WSP_Shop_Owner
	 */
	private static $instance = null;

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {}

	/**
	 * Get the single instance of the class.
	 *
	 * @return WSP_Shop_Owner The single instance.
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

	/**
	 * Register the Shop Owner user role.
	 *
	 * Creates a custom user role with specific capabilities for managing
	 * pickup stores and shop orders. Only creates the role if it doesn't already exist.
	 *
	 * @return void
	 */
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
	 * Filter the Pickup Store list to show only assigned stores for Shop Owners.
	 *
	 * Restricts the pickup store post list in the admin to show only stores
	 * assigned to the current shop owner user. Admins see all stores.
	 *
	 * @param WP_Query $query The WP_Query object being executed.
	 * @return void
	 */
	public function filter_pickup_store_list( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'pickup_store' !== $query->get( 'post_type' ) ) {
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
	 * Filter HPOS (High-Performance Order Storage) orders by shop owner's stores.
	 *
	 * Modifies the query to show only orders with pickup store IDs assigned to the current shop owner.
	 * Admins see all orders regardless of assignment.
	 *
	 * @param array    $clauses Database query clauses (join, where).
	 * @param WP_Query $query   The WP_Query object.
	 * @return array Modified query clauses.
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
		if ( false === strpos( $clauses['join'], 'shop_owner_store_meta' ) ) {
			$clauses['join'] .= " INNER JOIN {$wpdb->prefix}wc_orders_meta AS shop_owner_store_meta 
				ON {$wpdb->prefix}wc_orders.id = shop_owner_store_meta.order_id";
		}

		// Add WHERE condition
		$clauses['where'] .= " AND shop_owner_store_meta.meta_key = '_pickup_store_id' 
			AND shop_owner_store_meta.meta_value IN ({$ids})";

		return $clauses;
	}

	/**
	 *
	 */
	public function filter_orders_legacy( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'shop_order' !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$store_ids = $this->get_store_ids_by_owner( get_current_user_id() );

		if ( empty( $store_ids ) ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		$query->set(
			'meta_query',
			array(
				array(
					'key'     => '_pickup_store_id',
					'value'   => $store_ids,
					'compare' => 'IN',
				),
			)
		);
	}


	/**
	 * Get store IDs assigned to a specific shop owner.
	 *
	 * @param int $user_id The ID of the shop owner user.
	 * @return array Array of pickup store post IDs assigned to the user.
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
	 * Prevent shop owners from updating protected metadata.
	 *
	 * Blocks shop owners from modifying admin-only fields like assignment and zone mappings.
	 * Allows administrators to modify all metadata.
	 *
	 * @param mixed  $check       The result of any previous filters.
	 * @param int    $object_id   The ID of the object.
	 * @param string $meta_key    The key of the metadata being updated.
	 * @param mixed  $meta_value  The new value for the metadata.
	 * @param mixed  $prev_value  The previous value of the metadata.
	 * @return mixed False to prevent the update, or original check value to allow.
	 */
	public function prevent_shop_owner_meta_change( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		$protected_keys = array( '_assigned_shop_owner', '_pickup_zone_id' );

		if ( ! in_array( $meta_key, $protected_keys, true ) ) {
			return $check;
		}

		// Block shop owners, allow admins
		if ( current_user_can( 'edit_pickup_store' ) && ! current_user_can( 'manage_options' ) ) {
			error_log(
				sprintf(
					'WSP SECURITY: User %d tried to modify %s on store %d',
					get_current_user_id(),
					$meta_key,
					$object_id
				)
			);
			return false;
		}

		return $check;
	}

	/**
	 * Prevent shop owners from adding protected metadata.
	 *
	 * Blocks shop owners from adding protected fields. Allows administrators to add any metadata.
	 *
	 * @param mixed  $check      The result of any previous filters.
	 * @param int    $object_id  The ID of the object.
	 * @param string $meta_key   The key of the metadata being added.
	 * @param mixed  $meta_value The value of the metadata.
	 * @param bool   $unique     Whether the metadata key should be unique.
	 * @return mixed False to prevent the addition, or original check value to allow.
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
	 * Hide admin menus for shop owner users.
	 *
	 * Removes certain admin menu items for shop owners while keeping them visible to administrators.
	 * Hides WooCommerce settings, reports, and tools menus.
	 *
	 * @return void
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

	public function block_unassigned_access() {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! isset( $_GET['post'], $_GET['action'] ) ) {
			return;
		}

		if ( 'edit' !== $_GET['action'] ) {
			return;
		}

		$post_id = absint( $_GET['post'] );
		if ( 'pickup_store' !== get_post_type( $post_id ) ) {
			return;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$assigned_owner = get_post_meta( $post_id, '_assigned_shop_owner', true );
		if ( get_current_user_id() != $assigned_owner ) {
			wp_die(
				__( 'You do not have permission to edit this store.', 'woo-store-plugin' ),
				__( 'Permission Denied', 'woo-store-plugin' ),
				array( 'response' => 403 )
			);
		}
	}
}

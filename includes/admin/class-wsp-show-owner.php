<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WSP_Shop_Owner' ) ) {
	return;
}

class WSP_Shop_Owner {

	/**
	 * Register (or silently update) the shop_owner role.
	 * Idempotent: add_cap() is a no-op when the cap already exists.
	 */
	public function register_role() {
		error_log( '[WSP_Shop_Owner] Starting role registration' );

		$role = get_role( 'shop_owner' );

		if ( ! $role ) {
			error_log( '[WSP_Shop_Owner] shop_owner role does not exist, creating it' );
			add_role(
				'shop_owner',
				__( 'Shop Owner', 'woo-store-plugin' ),
				array( 'read' => true )
			);
			$role = get_role( 'shop_owner' );
		}

		if ( ! $role ) {
			error_log( '[WSP_Shop_Owner] ERROR: Failed to create or retrieve shop_owner role' );
			return;
		}

		error_log( '[WSP_Shop_Owner] shop_owner role retrieved successfully' );

		$caps = array(
			'read_shop_orders',
			'read_shop_order',
			'edit_shop_order',
			'edit_shop_orders',
			'edit_others_shop_orders',
			'edit_published_shop_orders',
			'publish_shop_orders',
			'edit_private_shop_orders',
			'read_private_shop_orders',
			'read_pickup_store',
			'edit_pickup_store',
			'edit_pickup_stores',
			'publish_pickup_stores',
			'delete_pickup_stores',
			'edit_posts',
			'read',
		);

		foreach ( $caps as $cap ) {
			$role->add_cap( $cap );
		}

		error_log( '[WSP_Shop_Owner] All capabilities added to shop_owner role' );
	}

	// ── Pickup-store list filtering ─────────────────────────────
	public function filter_pickup_store_list( $query ) {
		error_log( '[WSP_Shop_Owner] filter_pickup_store_list called' );

		if ( ! is_admin() || ! $query->is_main_query() ) {
			error_log( '[WSP_Shop_Owner] Skipping filter: not admin or not main query' );
			return;
		}
		if ( $query->get( 'post_type' ) !== 'pickup_store' ) {
			error_log( '[WSP_Shop_Owner] Skipping filter: post_type is not pickup_store' );
			return;
		}
		if ( current_user_can( 'manage_options' ) ) {
			error_log( '[WSP_Shop_Owner] Skipping filter: user is admin' );
			return;
		}

		$user_id = get_current_user_id();
		error_log( '[WSP_Shop_Owner] Filtering pickup stores for user ID: ' . $user_id );

		$query->set(
			'meta_query',
			array(
				array(
					'key'   => '_assigned_shop_owner',
					'value' => $user_id,
				),
			)
		);
	}

	// ── HPOS order-list filtering ───────────────────────────────
	public function filter_orders_by_store_hpos( $clauses, $query ) {
		global $wpdb;
		error_log( '[WSP_Shop_Owner] filter_orders_by_store_hpos called' );

		if ( ! is_admin() || current_user_can( 'manage_options' ) ) {
			error_log( '[WSP_Shop_Owner] Skipping HPOS filter: not admin or user is admin' );
			return $clauses;
		}
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			error_log( '[WSP_Shop_Owner] Skipping HPOS filter: user cannot edit shop orders' );
			return $clauses;
		}

		$user_id = get_current_user_id();
		$store_ids = $this->get_store_ids_by_owner( $user_id );
		error_log( '[WSP_Shop_Owner] User ID: ' . $user_id . ', Store IDs: ' . print_r( $store_ids, true ) );

		if ( empty( $store_ids ) ) {
			error_log( '[WSP_Shop_Owner] No stores found for user, blocking all orders' );
			$clauses['where'] .= ' AND 1=0';
			return $clauses;
		}

		$ids   = implode( ',', array_map( 'absint', $store_ids ) );
		$alias = 'wsp_owner_store_meta';
		error_log( '[WSP_Shop_Owner] Filtering HPOS orders by store IDs: ' . $ids );

		if ( strpos( $clauses['join'], $alias ) === false ) {
			$clauses['join'] .= " INNER JOIN {$wpdb->prefix}wc_orders_meta AS {$alias}
				ON {$wpdb->prefix}wc_orders.id = {$alias}.order_id";
		}

		$clauses['where'] .= "
			AND {$alias}.meta_key   = '_pickup_store_id'
			AND {$alias}.meta_value IN ({$ids})
		";

		error_log( '[WSP_Shop_Owner] HPOS filter applied successfully' );
		return $clauses;
	}

	// ── Legacy order-list filtering ─────────────────────────────
	public function filter_orders_legacy( $query ) {
		error_log( '[WSP_Shop_Owner] filter_orders_legacy called' );

		if ( ! is_admin() || ! $query->is_main_query() ) {
			error_log( '[WSP_Shop_Owner] Skipping legacy filter: not admin or not main query' );
			return;
		}
		if ( $query->get( 'post_type' ) !== 'shop_order' ) {
			error_log( '[WSP_Shop_Owner] Skipping legacy filter: post_type is not shop_order' );
			return;
		}
		if ( current_user_can( 'manage_options' ) ) {
			error_log( '[WSP_Shop_Owner] Skipping legacy filter: user is admin' );
			return;
		}

		$user_id = get_current_user_id();
		$store_ids = $this->get_store_ids_by_owner( $user_id );
		error_log( '[WSP_Shop_Owner] Legacy filter - User ID: ' . $user_id . ', Store IDs: ' . print_r( $store_ids, true ) );

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

	// ── Per-order capability gate ───────────────────────────────
	/**
	 * STRICT RULES — breaking any of these causes an infinite loop:
	 *
	 *   1. NEVER call user_can() / current_user_can()
	 *        → they fire map_meta_cap again → recursion.
	 *
	 *   2. NEVER call wc_get_order()
	 *        → WC checks permissions on the order → fires map_meta_cap → recursion.
	 *
	 *   3. Use a static re-entrance guard as a safety net.
	 */
	public function map_order_edit_caps( $caps, $cap, $user_id, $args ) {

		// ── Re-entrance guard (safety net) ──────────────────────
		static $in_progress = false;
		if ( $in_progress ) {
			error_log( '[WSP_Shop_Owner] Re-entrance detected in map_order_edit_caps, returning caps' );
			return $caps;
		}

		// ── Only care about these two caps ───────────────────────
		if ( ! in_array( $cap, array( 'edit_post', 'edit_shop_order' ), true ) ) {
			return $caps;
		}

		$order_id = isset( $args[0] ) ? absint( $args[0] ) : 0;
		if ( ! $order_id ) {
			return $caps;
		}

		error_log( '[WSP_Shop_Owner] map_order_edit_caps called - Cap: ' . $cap . ', User ID: ' . $user_id . ', Order ID: ' . $order_id );

		// ── Role check — read WP_User directly, NO user_can() ───
		$user = new WP_User( $user_id );

		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			error_log( '[WSP_Shop_Owner] User is administrator, allowing access' );
			return $caps;   // admins pass through unconditionally
		}

		if ( ! in_array( 'shop_owner', (array) $user->roles, true ) ) {
			error_log( '[WSP_Shop_Owner] User is not shop_owner, passing through' );
			return $caps;   // not a shop_owner — not our business, pass through
		}

		error_log( '[WSP_Shop_Owner] User is shop_owner, checking store ownership' );

		// ── Lock before any DB call ──────────────────────────────
		$in_progress = true;

		// ── Read _pickup_store_id straight from DB, no wc_get_order() ──
		$store_id = (int) $this->get_order_meta_direct( $order_id, '_pickup_store_id' );
		error_log( '[WSP_Shop_Owner] Store ID for order ' . $order_id . ': ' . $store_id );

		// ── Unlock immediately ────────────────────────────────────
		$in_progress = false;

		if ( ! $store_id ) {
			error_log( '[WSP_Shop_Owner] No store ID found for order, denying access' );
			return array( 'do_not_allow' );
		}

		// ── Ownership check ──────────────────────────────────────
		$allowed_stores = array_map( 'intval', $this->get_store_ids_by_owner( $user_id ) );
		error_log( '[WSP_Shop_Owner] Allowed stores for user ' . $user_id . ': ' . print_r( $allowed_stores, true ) );

		if ( ! in_array( $store_id, $allowed_stores, true ) ) {
			error_log( '[WSP_Shop_Owner] Store ID ' . $store_id . ' not in allowed stores, denying access' );
			return array( 'do_not_allow' );
		}

		error_log( '[WSP_Shop_Owner] Access granted for order ' . $order_id );
		return $caps;   // allowed
	}

	// ── Status whitelist ────────────────────────────────────────
	public function allow_shop_owner_order_statuses( $statuses, $order ) {
		error_log( '[WSP_Shop_Owner] allow_shop_owner_order_statuses called' );

		if ( current_user_can( 'manage_options' ) ) {
			error_log( '[WSP_Shop_Owner] User is admin, returning all statuses' );
			return $statuses;
		}

		if ( current_user_can( 'edit_shop_orders' ) ) {
			error_log( '[WSP_Shop_Owner] User can edit shop orders, returning limited statuses' );
			return array( 'on-hold', 'processing', 'completed' );
		}

		error_log( '[WSP_Shop_Owner] Returning default statuses' );
		return $statuses;
	}

	// ── Helpers ─────────────────────────────────────────────────

	private function get_store_ids_by_owner( $user_id ) {
		error_log( '[WSP_Shop_Owner] get_store_ids_by_owner called for user: ' . $user_id );
		$store_ids = get_posts(
			array(
				'post_type'      => 'pickup_store',
				'fields'         => 'ids',
				'meta_key'       => '_assigned_shop_owner',
				'meta_value'     => $user_id,
				'posts_per_page' => -1,
			)
		);
		error_log( '[WSP_Shop_Owner] Found ' . count( $store_ids ) . ' stores for user ' . $user_id . ': ' . print_r( $store_ids, true ) );
		return $store_ids;
	}

	/**
	 * Read order meta directly from the database.
	 *
	 * Tries the HPOS table (wc_orders_meta) first, then falls back to
	 * legacy post_meta.  Does ZERO permission checks, so it is safe to
	 * call from inside map_meta_cap.
	 */
	private function get_order_meta_direct( $order_id, $key ) {
		global $wpdb;
		error_log( '[WSP_Shop_Owner] get_order_meta_direct called for order: ' . $order_id . ', key: ' . $key );

		// HPOS table (WooCommerce 8+)
		$table = $wpdb->prefix . 'wc_orders_meta';
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$table} WHERE order_id = %d AND meta_key = %s LIMIT 1",
				$order_id,
				$key
			)
		);

		if ( $value !== null ) {
			error_log( '[WSP_Shop_Owner] Found value in HPOS table: ' . $value );
			return $value;
		}

		// Legacy post_meta fallback
		$legacy_value = get_post_meta( $order_id, $key, true );
		error_log( '[WSP_Shop_Owner] HPOS value not found, using legacy post_meta: ' . $legacy_value );
		return $legacy_value;
	}
}
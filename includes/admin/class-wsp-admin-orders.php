<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WSP_Admin_Orders' ) ) {
	return;
}

class WSP_Admin_Orders {

	/**
	 * Add custom columns.
	 */
	public function add_columns( $columns ) {

		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( 'billing_address' === $key ) {
				$new_columns['pickup_store'] = __( 'Pickup Store', 'woo-store-plugin' );
				$new_columns['pickup_date']  = __( 'Pickup Date', 'woo-store-plugin' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render column values.
	 */
	public function render_columns( $column, $order ) {

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( 'pickup_store' === $column ) {
			echo esc_html( $order->get_meta( '_pickup_store_name' ) ?: '—' );
		}

		if ( 'pickup_date' === $column ) {
			echo esc_html( $order->get_meta( '_pickup_date' ) ?: '—' );
		}
	}

	/**
	 * Add Pickup Date filter to HPOS order list
	 */
	public function add_pickup_date_filter( $which ) {
		// Only show on top filters
		if ( 'shop_order' !== $which ) {
			return;
		}

		$value = isset( $_GET['wsp_pickup_date'] )
		? sanitize_text_field( wp_unslash( $_GET['wsp_pickup_date'] ) )
		: '';

		?>
	<label for="wsp_pickup_date" style="margin-left:10px;">
		<?php esc_html_e( 'Pickup Date:', 'woo-store-plugin' ); ?>
	</label>
	<input
		type="date"
		id="wsp_pickup_date"
		name="wsp_pickup_date"
		value="<?php echo esc_attr( $value ); ?>"
		style="margin-left:5px;"
	/>
		<?php
	}

	/**
	 * Filter orders by Pickup Date (HPOS compatible)
	 */
	public function filter_orders_by_pickup_date_hpos( $clauses, $query ) {
		global $wpdb;

		// Check if pickup date filter is set
		if ( empty( $_GET['wsp_pickup_date'] ) ) {
			return $clauses;
		}

		// Additional safety check - only run in admin
		if ( ! is_admin() ) {
			return $clauses;
		}

		// Get current screen - more reliable than $pagenow
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		$pickup_date = sanitize_text_field( wp_unslash( $_GET['wsp_pickup_date'] ) );

		// Add JOIN for meta table
		$join_alias = 'pickup_meta_filter';

		// Check if this specific alias already exists
		if ( strpos( $clauses['join'], $join_alias ) === false ) {
			$clauses['join'] .= " INNER JOIN {$wpdb->prefix}wc_orders_meta AS {$join_alias} ON {$wpdb->prefix}wc_orders.id = {$join_alias}.order_id";
		}

		// Add WHERE condition
		$clauses['where'] .= $wpdb->prepare(
			" AND {$join_alias}.meta_key = '_pickup_date' AND {$join_alias}.meta_value = %s",
			$pickup_date
		);

		return $clauses;
	}

	/**
	 * Add Pickup Date filter to legacy order list
	 */
	public function add_pickup_date_filter_legacy() {
		global $pagenow, $typenow;

		if ( 'edit.php' !== $pagenow || 'shop_order' !== $typenow ) {
			return;
		}

		$value = isset( $_GET['wsp_pickup_date'] )
			? sanitize_text_field( wp_unslash( $_GET['wsp_pickup_date'] ) )
			: '';

		echo '<input
			type="date"
			name="wsp_pickup_date"
			placeholder="' . esc_attr__( 'Filter by Pickup Date', 'woo-store-plugin' ) . '"
			value="' . esc_attr( $value ) . '"
			style="margin-left:10px;"
		/>';
	}

	/**
	 * Filter orders by Pickup Date for legacy orders
	 */
	public function filter_orders_by_pickup_date_legacy( $query ) {
		global $pagenow, $typenow;

		if ( 'shop_order' !== $typenow || empty( $_GET['wsp_pickup_date'] ) ) {
			return;
		}

		$meta_query   = (array) $query->get( 'meta_query' );
		$meta_query[] = array(
			'key'     => '_pickup_date',
			'value'   => sanitize_text_field( wp_unslash( $_GET['wsp_pickup_date'] ) ),
			'compare' => '=',
		);
		$query->set( 'meta_query', $meta_query );
	}

	public function search_orders_by_pickup_store_hpos( $clauses, $query ) {
		global $wpdb;

		if ( ! is_admin() ) {
			return $clauses;
		}

		$search = $query->get( 's' );
		if ( empty( $search ) ) {
			return $clauses;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'woocommerce_page_wc-orders' !== $screen->id ) {
			return $clauses;
		}

		$search = '%' . $wpdb->esc_like( $search ) . '%';
		$alias  = 'pickup_store_search';

		if ( strpos( $clauses['join'], $alias ) === false ) {
			$clauses['join'] .= "
			LEFT JOIN {$wpdb->prefix}wc_orders_meta AS {$alias}
			ON {$wpdb->prefix}wc_orders.id = {$alias}.order_id
		";
		}

		$clauses['where'] .= $wpdb->prepare(
			" OR ( {$alias}.meta_key = '_pickup_store_name' AND {$alias}.meta_value LIKE %s )",
			$search
		);

		return $clauses;
	}
}

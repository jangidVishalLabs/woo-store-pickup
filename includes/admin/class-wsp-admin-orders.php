<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WSP_Admin_Orders' ) ) {
	return;
}

/**
 * WSP_Admin_Orders class.
 *
 * Handles admin order list customizations including custom columns for pickup store and date,
 * filtering capabilities, and display of pickup details in the order admin interface.
 * Supports both HPOS (High-Performance Order Storage) and legacy post-based orders.
 *
 * @class WSP_Admin_Orders
 * @version 1.0.0
 */
class WSP_Admin_Orders {

	/**
	 * Add custom columns to the order list table.
	 *
	 * Inserts "Pickup Store" and "Pickup Date" columns after the billing address column.
	 * Works with both HPOS and legacy order lists.
	 *
	 * @param array $columns Existing order list columns.
	 * @return array Modified columns array with new pickup columns.
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
	 * Render custom column values in the order list.
	 *
	 * Displays the pickup store name and pickup date for each order in the custom columns.
	 *
	 * @param string  $column The name of the column being rendered.
	 * @param WC_Order|object $order The order object (WC_Order for HPOS, post ID for legacy).
	 * @return void
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
	 * Add pickup date filter input to HPOS order list.
	 *
	 * Renders a date input field above the order list for filtering orders by pickup date.
	 *
	 * @param string $which Placement indicator ('top' or 'bottom').
	 * @return void
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
	 * Filter HPOS orders by pickup date.
	 *
	 * Modifies the database query to filter orders based on the selected pickup date.
	 *
	 * @param array    $clauses Database query clauses (join, where).
	 * @param WP_Query $query   The WP_Query object.
	 * @return array Modified query clauses.
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
	 * Add pickup date filter input to legacy order list.
	 *
	 * Renders a date input field for filtering legacy (post-based) orders by pickup date.
	 *
	 * @return void
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
	 * Filter legacy orders by pickup date.
	 *
	 * Modifies the query to filter legacy post-based orders based on the selected pickup date.
	 *
	 * @param WP_Query $query The WP_Query object being executed.
	 * @return void
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

	/**
	 * Search HPOS orders by pickup store name.
	 *
	 * Enables searching orders by the pickup store name in the HPOS order list.
	 *
	 * @param array    $clauses Database query clauses (join, where).
	 * @param WP_Query $query   The WP_Query object.
	 * @return array Modified query clauses.
	 */
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

	function wsp_admin_edit_pickup_date_field( $order ) {

	// Only pickup orders
	foreach ( $order->get_shipping_methods() as $method ) {
		if ( $method->get_method_id() === 'wsp_store_pickup' ) {

			$value = $order->get_meta( '_pickup_date' );

			woocommerce_wp_text_input(
				array(
					'id'    => '_pickup_date',
					'label' => 'Pickup Date',
					'type'  => 'date',
					'value' => $value,
				)
			);
			break;
		}
	}
}

	function wsp_save_admin_pickup_date( $order_id ) {

	if ( empty( $_POST['_pickup_date'] ) ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$new_date = sanitize_text_field( wp_unslash( $_POST['_pickup_date'] ) );

	$order->update_meta_data( '_pickup_date', $new_date );
	$order->save();

	// OPTIONAL: reschedule reminder if you implemented per-order cron
	wp_clear_scheduled_hook( 'wsp_send_pickup_reminder_single', array( $order_id ) );
	wsp_schedule_pickup_reminder_for_order( $order_id );
}
}

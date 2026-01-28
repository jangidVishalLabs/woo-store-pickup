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
	 * Add pickup date filter.
	 */
	public function add_pickup_date_filter() {

		global $typenow;

		if ( 'shop_order' !== $typenow ) {
			return;
		}

		$value = isset( $_GET['pickup_date'] )
			? sanitize_text_field( wp_unslash( $_GET['pickup_date'] ) )
			: '';

		echo '<input type="date" name="pickup_date" value="' . esc_attr( $value ) . '" />';
	}

	/**
	 * Apply pickup date filter.
	 */
	public function filter_orders_by_pickup_date( $query ) {

		global $pagenow;

		if (
			! is_admin() ||
			'edit.php' !== $pagenow ||
			empty( $_GET['pickup_date'] )
		) {
			return;
		}

		$meta_query = (array) $query->get( 'meta_query' );

		$meta_query[] = array(
			'key'   => '_pickup_date',
			'value' => sanitize_text_field( wp_unslash( $_GET['pickup_date'] ) ),
		);

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Enable search by pickup store.
	 */
	public function enable_store_search( $meta_keys ) {
		$meta_keys[] = '_pickup_store_name';
		return $meta_keys;
	}
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( class_exists( 'WSP_Shipping_Pickup' ) ) {
	return;
}
/**
 * WSP Shipping Pickup Class.
 */
class WSP_Shipping_Pickup  extends WC_Shipping_Method {
	
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'wsp_store_pickup';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Store Pickup', 'woo-store-plugin' );
		$this->method_description = __( 'Allows customers to pick up their orders from a physical store location.', 'woo-store-plugin' );
		$this->enabled            = "yes";
		$this->title              = __( 'Store Pickup', 'woo-store-plugin' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
		);

		$this->init();
	}
	/**
	 * Initialize settings.
	 */
	public function init() {
		// Load the settings API
		$this->init_form_fields();
		$this->init_settings();

		$this->title = $this->get_option( 'title', 'Store Pickup' );
		$this->enabled = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}
	/**
	 * Admin Settings Fields.
	 */
	public function init_form_fields() {

		$this->instance_form_fields = array(
			'title' => array(
				'title'       => __( 'Method Title', 'woo-store-plugin' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woo-store-plugin' ),
				'default'     => __( 'Store Pickup', 'woo-store-plugin' ),
				'desc_tip'    => true,
			),
			'enabled' => array(
				'title'       => __( 'Enable/Disable', 'woo-store-plugin' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Store Pickup Method', 'woo-store-plugin' ),
				'default'     => 'yes',
			),
			'assigned_stores' => array(
				'title'       => __( 'Assigned Stores', 'woo-store-plugin' ),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Select the stores available for pickup.', 'woo-store-plugin' ),
				'default'     => array(),
				'options'     => $this->get_store_options_safe(),
				'desc_tip'    => true,
			),
			'cost' => array(
				'title'       => __( 'Pickup Cost', 'woo-store-plugin' ),
				'type'        => 'price',
				'description' => __( 'Cost for store pickup. Use 0 for free pickup.', 'woo-store-plugin' ),
				'default'     => '0',
				'desc_tip'    => true,
			),

		);
	}

	private function get_store_options_safe() {

		$options = array();

		// Safety check: CPT exists?
		if ( ! post_type_exists( 'pickup_store' ) ) {
			return $options;
		}

		$stores = get_posts(
			array(
				'post_type'      => 'pickup_store',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		foreach ( $stores as $store ) {
			$options[ $store->ID ] = $store->post_title;

		}
		return $options;
	}

	/**
	 * Calculate Shipping Cost.
	 */
	public function calculate_shipping( $package = array() ) {
		if ( 'yes' !== $this->get_option( 'enabled' ) ) {
			return;
		}
		$assigned_stores = $this->get_assigned_stores();
		if ( empty( $assigned_stores ) ) {
			$label = $this->title . ' (' . __( 'No stores available', 'woo-store-plugin' ) . ')';
		} else {
			$label = $this->title;
		}

		$cost = (float) $this->get_option( 'cost', 0 );
		$rate = array(
			'id'    => $this->id . ':' . $this->instance_id,
			'label' => $label,
			'cost'  => $cost,
		);
		$this->add_rate( $rate );
	}
	/**
	 * Get Assigned Stores.
	 */
	public function get_assigned_stores() {
		$stores = $this->get_option( 'assigned_stores', array() );
		if ( empty( $stores ) ) {
			return array();
		}
		if ( ! is_array( $stores ) ) {
			$stores = array_map('absint', explode( ',', $stores ) );
		}

		return array_filter( array_map( 'absint', $stores ) );
	}



}


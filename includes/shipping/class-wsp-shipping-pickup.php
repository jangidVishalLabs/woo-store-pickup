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
	
	public function __construct() {
		$this->id                 = 'wsp_store_pickup';
		$this->method_title       = __( 'Store Pickup', 'woo-store-plugin' );
		$this->method_description = __( 'Allows customers to pick up their orders from a physical store location.', 'woo-store-plugin' );
		$this->enabled            = "yes";
		$this->title              = __( 'Store Pickup', 'woo-store-plugin' );

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
		$this->form_fields = array(
			'enabled' => array(
				'title'       => __( 'Enable', 'woo-store-plugin' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Store Pickup', 'woo-store-plugin' ),
				'default'     => 'yes',
			),
			'title' => array(
				'title'       => __( 'Method Title', 'woo-store-plugin' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woo-store-plugin' ),
				'default'     => __( 'Store Pickup', 'woo-store-plugin' ),
				'desc_tip'    => true,
			),
		);
	}
	/**
	 * Calculate Shipping Cost.
	 */
	public function calculate_shipping( $package = array() ) {
		$rate = array(
			'id'    => $this->id,
			'label' => $this->title,
			'cost'  => '0.00',
		);
		$this->add_rate( $rate );
	}
}


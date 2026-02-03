<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( class_exists( 'WSP_Store_CPT' ) ) {
	return;
}

/**
 * WSP_Store_CPT class.
 *
 * Handles the registration of the "Pickup Store" custom post type.
 * This CPT is used to create and manage physical store locations where customers can pick up orders.
 * Implements Singleton pattern to ensure only one instance exists.
 *
 * @class WSP_Store_CPT
 * @version 1.0.0
 */
class WSP_Store_CPT {

	/**
	 * The single instance of the class.
	 *
	 * @var WSP_Store_CPT
	 */
	private static $instance = null;

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {}

	/**
	 * Get the single instance of the class.
	 *
	 * @return WSP_Store_CPT The single instance.
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
	 * Register the Pickup Store custom post type.
	 *
	 * Creates a custom post type called 'pickup_store' with appropriate labels,
	 * capabilities, and settings for managing store locations.
	 *
	 * @return void
	 */
	public function register_cpt() {

		register_post_type(
			'pickup_store',
			array(
				'labels'              => array(
					'name'          => __( 'Pickup Stores', 'wsp' ),
					'singular_name' => __( 'Pickup Store', 'wsp' ),
				),
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'capability_type'     => 'post',
				'show_url'            => true,
				'menu_icon'           => 'dashicons-store',
				'supports'            => array( 'title' ),
			)
		);
	}
}

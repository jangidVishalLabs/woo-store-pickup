<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( class_exists( 'WSP_Store_CPT' ) ) {
	return;
}

class WSP_Store_CPT {

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

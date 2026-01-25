<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( class_exists( 'WSP_Loader' ) ) {
	return;
}

class WSP_Loader {
	protected $actions = array();

	public function add_action( $hook, $component, $callback ) {
		$this->actions[] = compact( 'hook', 'component', 'callback' );
	}

	public function run() {
		foreach ( $this->actions as $action ) {
			add_action( $action['hook'], array( $action['component'], $action['callback'] ) );
		}
	}
}
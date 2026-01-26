<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WSP_Loader' ) ) {
	return;
}

class WSP_Loader {

	protected $actions = array();

	public function add_action(
		$hook,
		$component,
		$callback,
		$priority = 10,
		$accepted_args = 1
	) {
		$this->actions[] = compact(
			'hook',
			'component',
			'callback',
			'priority',
			'accepted_args'
		);
	}

	public function run() {
		foreach ( $this->actions as $action ) {
			add_action(
				$action['hook'],
				array( $action['component'], $action['callback'] ),
				$action['priority'],
				$action['accepted_args']
			);
		}
	}
}

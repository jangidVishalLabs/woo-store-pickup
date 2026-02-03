	<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( class_exists( 'WSP_Loader' ) ) {
		return;
	}

	/**
	 * WSP_Loader class.
	 *
	 * Manages the registration and execution of WordPress actions and filters.
	 * This is a generic loader class that helps organize and run hooks in a clean manner.
	 * Implements Singleton pattern to ensure only one instance exists.
	 *
	 * @class WSP_Loader
	 * @version 1.0.0
	 */
	class WSP_Loader {

		/**
		 * The single instance of the class.
		 *
		 * @var WSP_Loader
		 */
		private static $instance = null;

		/**
		 * Array of registered actions to be executed.
		 *
		 * @var array
		 */
		public $actions = array();

		/**
		 * Array of registered filters to be executed.
		 *
		 * @var array
		 */
		/**
		 * Array of registered filters to be executed.
		 *
		 * @var array
		 */
		public $filters = array();

		/**
		 * Private constructor to prevent direct instantiation.
		 */
		private function __construct() {}

		/**
		 * Get the single instance of the class.
		 *
		 * @return WSP_Loader The single instance.
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
		 * Add a new action to the actions array.
		 *
		 * @param string $hook          The name of the WordPress action hook.
		 * @param object $component     The object to which the action is bound.
		 * @param string $callback      The name of the function to execute on the action.
		 * @param int    $priority      Optional. The priority of the action. Default is 10.
		 * @param int    $accepted_args Optional. The number of arguments to pass to the callback. Default is 1.
		 * @return void
		 */
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

		/**
		 * Add a new filter to the filters array.
		 *
		 * @param string $hook          The name of the WordPress filter hook.
		 * @param object $component     The object to which the filter is bound.
		 * @param string $callback      The name of the function to execute on the filter.
		 * @param int    $priority      Optional. The priority of the filter. Default is 10.
		 * @param int    $accepted_args Optional. The number of arguments to pass to the callback. Default is 1.
		 * @return void
		 */
		public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
			$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
		}

		/**
		 * Register all actions and filters with WordPress.
		 *
		 * This method loops through all registered actions and filters and executes the
		 * WordPress add_action() and add_filter() functions to register them.
		 *
		 * @return void
		 */
		public function run() {
			foreach ( $this->actions as $action ) {
				add_action(
					$action['hook'],
					array( $action['component'], $action['callback'] ),
					$action['priority'],
					$action['accepted_args']
				);
			}
			foreach ( $this->filters as $filter ) {
				add_filter(
					$filter['hook'],
					array( $filter['component'], $filter['callback'] ),
					$filter['priority'],
					$filter['accepted_args']
				);
			}
		}
	}

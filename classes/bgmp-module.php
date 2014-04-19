<?php

if ( ! class_exists( 'BGMP_Module' ) ) {
	abstract class BGMP_Module {

		/**
		 * Maintain backwards compatibility for camelCase methods
		 *
		 * Originally this plugin used camelCase method names, but switched to underscore_case
		 * in 2.0 to align with WordPress coding standards. This magic method allows other plugins
		 * to continue calling the old method names until there has been sufficient time for
		 * developers to switch to using the new ones.
		 *
		 * @param string $method
		 * @param array  $parameters
		 * @return mixed
		 */
		public function __call( $method, $parameters ) {
			$underscore_name = strtolower( preg_replace( '/(?!^)[[:upper:]][[:lower:]]/', '_$0', $method ) );
			$results         = null;

			if ( method_exists( $this, $underscore_name ) ) {
				_deprecated_function( __CLASS__ . '->' . $method, '2.0', __CLASS__ . '->' . $underscore_name );
				$results = call_user_func_array( array( $this, $underscore_name ), $parameters );
			}

			return $results;
		}
	}
}

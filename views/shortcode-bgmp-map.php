$bgmpData = sprintf(
				"bgmpData.options = %s;\r\nbgmpData.markers = %s",
				json_encode( $this->getMapOptions() ),
				json_encode( $this->getMapPlacemarks() )
			);
			wp_localize_script( 'bgmp', 'bgmpData', array( 'l10n_print_after' => $bgmpData ) );
			
			$output = sprintf('
				<div id="%smap-canvas">
					<p>'. __( 'Loading map...', 'bgmp' ) .'</p>
					<p><img src="%s" alt="'. __( 'Loading', 'bgmp' ) .'" /></p>
				</div>',
				self::PREFIX,
				plugins_url( 'images/loading.gif', __FILE__ )
			);	// @todo - escape alt attr?
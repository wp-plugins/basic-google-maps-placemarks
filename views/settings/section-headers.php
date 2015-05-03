<?php if ( 'bgmp_map-settings' == $section['id'] ) : ?>

	<h3><?php _e( 'Map Settings', 'bgmp' ); ?></h3>
	<p>
		<?php printf(
			__( 'The map(s) will use these settings as defaults, but you can override them on individual maps using shortcode arguments. See <a href="%s">the Installation page</a> for details.', 'bgmp' ),
			'http://wordpress.org/extend/plugins/basic-google-maps-placemarks/installation/'
		); ?>
	</p>

<?php elseif ( 'bgmp_marker-cluster-settings' == $section['id'] ) : ?>

	<h3><?php _e( 'Marker Clustering', 'bgmp' ); ?></h3>
	<p><?php _e( 'You can group large numbers of markers into a single cluster by enabling the Cluster Markers option.', 'bgmp' ); ?></p>

<?php endif; ?>

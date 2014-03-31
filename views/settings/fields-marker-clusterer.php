<?php if ( 'bgmp_marker-clustering' == $field['label_for'] ) : ?>

	<input id="bgmp_marker-clustering" name="bgmp_marker-clustering" type="checkbox" <?php echo checked( $markerClustering, 'on', false ); ?> />
	<label for="bgmp_marker-clustering">
		<?php _e( ' Enable marker clustering', 'bgmp' ); ?>
	</label>

<?php elseif ( 'bgmp_cluster-max-zoom' == $field['label_for'] ) : ?>

	<input id="bgmp_cluster-max-zoom" name="bgmp_cluster-max-zoom" type="text" value="<?php echo esc_attr( $clusterMaxZoom ); ?>" class="small-text" />
	<?php printf( __( '%d (farthest) to %d (closest)', 'bgmp' ), BasicGoogleMapsPlacemarks::ZOOM_MIN, BasicGoogleMapsPlacemarks::ZOOM_MAX ); ?>
	<p class="description">
		<?php _e( 'When the maximum zoom level is reached, all markers will be shown without clustering.', 'bgmp' ); ?>
	</p>

<?php elseif ( 'bgmp_cluster-grid-size' == $field['label_for'] ) : ?>

	<input id="bgmp_cluster-grid-size" name="bgmp_cluster-grid-size" type="text" value="<?php echo esc_attr( $clusterGridSize ); ?>" class="small-text" />
	<p class="description">
		<?php _e( 'The grid size of a cluster, in pixels. Each cluster will be a square. Larger grids can be rendered faster.', 'bgmp' ); ?>
	</p>

<?php elseif ( 'bgmp_cluster-style' == $field['label_for'] ) : ?>

	<select id="bgmp_cluster-style" name="bgmp_cluster-style">
		<option value="default"      <?php selected( $clusterStyle, 'default',      false ); ?>><?php _e( 'Default',      'bgmp' ); ?></option>
		<option value="people"       <?php selected( $clusterStyle, 'people',       false ); ?>><?php _e( 'People',       'bgmp' ); ?></option>
		<option value="hearts"       <?php selected( $clusterStyle, 'hearts',       false ); ?>><?php _e( 'Hearts',       'bgmp' ); ?></option>
		<option value="conversation" <?php selected( $clusterStyle, 'conversation', false ); ?>><?php _e( 'Conversation', 'bgmp' ); ?></option>
	</select>

<?php endif; ?>

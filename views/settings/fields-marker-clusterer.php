<?php if ( $field['label_for'] == 'bgmp_marker-clustering' ) : ?>

	<input id="bgmp_marker-clustering" name="bgmp_marker-clustering" type="checkbox" <?php echo checked( $markerClustering, 'on', false ); ?> />
	<label for="bgmp_marker-clustering">
		<?php esc_html_e( ' Enable marker clustering', 'bgmp' ); ?>
	</label>

<?php elseif ( $field['label_for'] == 'bgmp_cluster-max-zoom' ) : ?>

	<input id="bgmp_cluster-max-zoom" name="bgmp_cluster-max-zoom" type="text" value="<?php esc_attr_e( $clusterMaxZoom ); ?>" class="small-text" />
	<?php printf( __( '%d (farthest) to %d (closest)', 'bgmp' ), BasicGoogleMapsPlacemarks::ZOOM_MIN, BasicGoogleMapsPlacemarks::ZOOM_MAX ); ?>
	<p class="description">
		<?php esc_html_e( 'When the maximum zoom level is reached, all markers will be shown without clustering.', 'bgmp' ); ?>
	</p>

<?php elseif ( $field['label_for'] == 'bgmp_cluster-grid-size' ) : ?>

	<input id="bgmp_cluster-grid-size" name="bgmp_cluster-grid-size" type="text" value="<?php esc_attr_e( $clusterGridSize ); ?>" class="small-text" />
	<p class="description">
		<?php esc_html_e( 'The grid size of a cluster, in pixels. Each cluster will be a square. Larger grids can be rendered faster.', 'bgmp' ); ?>
	</p>

<?php elseif ( $field['label_for'] == 'bgmp_cluster-style' ) : ?>

	<select id="bgmp_cluster-style" name="bgmp_cluster-style">
		<option value="default"      <?php echo selected( $clusterStyle, 'default',      false ); ?>><?php esc_html_e( 'Default',      'bgmp' ); ?></option>
		<option value="people"       <?php echo selected( $clusterStyle, 'people',       false ); ?>><?php esc_html_e( 'People',       'bgmp' ); ?></option>
		<option value="hearts"       <?php echo selected( $clusterStyle, 'hearts',       false ); ?>><?php esc_html_e( 'Hearts',       'bgmp' ); ?></option>
		<option value="conversation" <?php echo selected( $clusterStyle, 'conversation', false ); ?>><?php esc_html_e( 'Conversation', 'bgmp' ); ?></option>
	</select>

<?php endif; ?>

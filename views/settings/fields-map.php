<?php if ( 'bgmp_map-width' == $field['label_for'] ) : ?>
	
	<input id="bgmp_map-width" name="bgmp_map-width" type="text" value="<?php echo esc_attr( $mapWidth ); ?>" class="small-text" />
	<?php _e( 'pixels', 'bgmp' ); ?>

<?php elseif ( 'bgmp_map-height' == $field['label_for'] ) : ?>

	<input id="bgmp_map-height" name="bgmp_map-height" type="text" value="<?php echo esc_attr( $mapHeight ); ?>" class="small-text" />
	<?php _e( 'pixels', 'bgmp' ); ?>

<?php elseif ( 'bgmp_map-address' == $field['label_for'] ) : ?>

	<input id="bgmp_map-address" name="bgmp_map-address" type="text" value="<?php echo esc_attr( $mapAddress ); ?>" class="regular-text" />

	<?php if ( $mapAddress && ! BasicGoogleMapsPlacemarks::validateCoordinates( $mapAddress ) && $mapLatitude && $mapLongitude ) : ?>
		<em>(<?php _e( 'Geocoded to:', 'bgmp' ); ?> <?php echo esc_html( $mapLatitude ); ?>, <?php echo esc_html( $mapLongitude ); ?>)</em>
	<?php elseif ( $mapAddress && ( ! $mapLatitude || ! $mapLongitude ) ) : ?>
		<em><?php _e( "(Error geocoding address. Please make sure it's correct and try again.)", 'bgmp' ); ?></em>
	<?php endif; ?>

	<p class="description">
		<?php _e( 'You can type in anything that you would type into a Google Maps search field, from a full address to an intersection, landmark, city, zip code or latitude/longitude coordinates.', 'bgmp' ); ?>
	</p>

<?php elseif ( 'bgmp_map-zoom' == $field['label_for'] ) : ?>

	<input id="bgmp_map-zoom" name="bgmp_map-zoom" type="text" value="<?php echo esc_attr( $mapZoom ); ?>" class="small-text" />
	<?php printf( __( '%d (farthest) to %d (closest)', 'bgmp' ), BasicGoogleMapsPlacemarks::ZOOM_MIN, BasicGoogleMapsPlacemarks::ZOOM_MAX ); ?>

<?php elseif ( 'bgmp_map-type' == $field['label_for'] ) : ?>

	<select id="bgmp_map-type" name="bgmp_map-type">';

		<?php foreach ( $mapTypes as $code => $label ) : ?>
			<option value="<?php echo $code; ?>" <?php selected( $mapType, $code  ); ?>>
				<?php echo esc_html( $label ); ?>
			</option>
		<?php endforeach; ?>

	</select>

<?php elseif ( 'bgmp_map-type-control' == $field['label_for'] ) : ?>

	<select id="bgmp_map-type-control" name="bgmp_map-type-control">
		<option value="off"            <?php selected( $mapTypeControl, 'off'            ); ?>><?php _e( 'Off',            'bgmp' ); ?></option>
		<option value="DEFAULT"        <?php selected( $mapTypeControl, 'DEFAULT'        ); ?>><?php _e( 'Automatic',      'bgmp' ); ?></option>
		<option value="HORIZONTAL_BAR" <?php selected( $mapTypeControl, 'HORIZONTAL_BAR' ); ?>><?php _e( 'Horizontal Bar', 'bgmp' ); ?></option>
		<option value="DROPDOWN_MENU"  <?php selected( $mapTypeControl, 'DROPDOWN_MENU'  ); ?>><?php _e( 'Dropdown Menu',  'bgmp' ); ?></option>
	</select>
	
	<p class="description">
		<?php _e( ' "Automatic" will automatically switch to the appropriate control based on the window size and other factors.', 'bgmp' ); ?>
	</p>

<?php elseif ( 'bgmp_map-navigation-control' == $field['label_for'] ) : ?>

	<select id="bgmp_map-navigation-control" name="bgmp_map-navigation-control">
		<option value="off"      <?php selected( $mapNavigationControl, 'DEFAULT' );  ?>><?php _e( 'Off',       'bgmp' ); ?></option>
		<option value="DEFAULT"  <?php selected( $mapNavigationControl, 'DEFAULT' );  ?>><?php _e( 'Automatic', 'bgmp' ); ?></option>
		<option value="SMALL"    <?php selected( $mapNavigationControl, 'SMALL' );    ?>><?php _e( 'Small',     'bgmp' ); ?></option>
		<option value="ANDROID"  <?php selected( $mapNavigationControl, 'ANDROID' );  ?>><?php _e( 'Android',   'bgmp' ); ?></option>
		<option value="ZOOM_PAN" <?php selected( $mapNavigationControl, 'ZOOM_PAN' ); ?>><?php _e( 'Zoom/Pan',  'bgmp' ); ?></option>
	</select>

	<p class="description">
		<?php esc_html_e( ' "Automatic" will automatically switch to the appropriate control based on the window size and other factors.', 'bgmp' ); ?>
	</p>

<?php elseif ( 'bgmp_map-info-window-width' == $field['label_for'] ) : ?>

	<input id="bgmp_map-info-window-width" name="bgmp_map-info-window-width" type="text" value="<?php echo esc_attr( $mapInfoWindowMaxWidth ); ?>" class="small-text" />
	<?php _e( 'pixels', 'bgmp' ); ?>

<?php endif; ?>

<?php do_action( 'bgmp_meta-z-index-before' ); ?>

	<p><?php _e( 'When two markers overlap, the marker with the higher stacking order will be on top. The Default is 0.', 'bgmp' ); ?></p>

	<p>
		<label for="bgmp_z_index"><?php _e( 'Stacking Order:', 'bgmp' ); ?></label>
		<input id="bgmp_z_index" name="bgmp_z_index" type="text" size="4" value="<?php echo esc_attr( $z_index ); ?>" />
	</p>

<?php do_action( 'bgmp_meta-z-index-after' ); ?>

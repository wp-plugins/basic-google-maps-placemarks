<p><?php _e( 'When two markers overlap, the marker with the higher stacking order will be on top. The Default is 0.', self::I18N_DOMAIN ); ?></p>

<p>
	<label for="<?php echo self::PREFIX; ?>zIndex"><?php _e( 'Stacking Order:', self::I18N_DOMAIN ); ?></label>
	<input id="<?php echo self::PREFIX; ?>zIndex" name="<?php echo self::PREFIX; ?>zIndex" type="text" size="4" value="<?php echo $zIndex; ?>" />
</p>
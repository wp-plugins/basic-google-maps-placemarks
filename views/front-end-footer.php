<!-- Begin Basic Google Map Placemarks footer -->
<form>
	<p>
		<input id="<?php echo self::PREFIX .'postURL'; ?>" type="hidden" value="<?php echo admin_url('admin-ajax.php'); ?>" />
		<input id="<?php echo self::PREFIX . 'nonce'; ?>" type="hidden" value="<?php echo wp_create_nonce( self::PREFIX . 'nonce'); ?>" />
	</p>
</form>
<!-- End Basic Google Map Placemarks footer -->

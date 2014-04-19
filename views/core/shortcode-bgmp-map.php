<script type="text/javascript">
	var bgmpData = {
		options: <?php echo json_encode( $this->get_map_options( $attributes ) ); ?>,
		markers: <?php echo json_encode( $this->get_map_placemarks( $attributes ) ); ?>
	};
</script>

<div id="bgmp_map-canvas">
	<p><?php _e( 'Loading map...', 'bgmp' ); ?></p>

	<p>
		<img src="<?php echo plugins_url( 'images/loading.gif', dirname( dirname( __FILE__ ) ) ); ?>" alt="<?php _e( 'Loading', 'bgmp' ); ?>" />
	</p>
</div>

<!-- Begin Basic Google Map Placemarks head -->
<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />

<style type="text/css">
	#bgmp_map-canvas
	{
		width: <?php echo $this->settings['map-width']; ?>px;
		height: <?php echo $this->settings['map-height']; ?>px;
	}
	
		.bgmp_placemark
		{
			width: <?php echo $this->settings['map-info-window-width']; ?>px;
			height: <?php echo $this->settings['map-info-window-height']; ?>px;
		}
		
			.bgmp_placemark h1
			{
				padding: 5px 0;
			}
		
		/* Override theme styles that will interfere with the map */
		#bgmp_map-canvas img
		{
			background: none !important;
			padding: 0;
			-webkit-box-shadow: none;
			-moz-box-shadow: none;
			box-shadow: none;
		}
		
		#bgmp_map-canvas #content
		{
			width: auto;
			height: auto;
			overflow: auto;
		}
</style>
<!-- End Basic Google Map Placemarks head -->

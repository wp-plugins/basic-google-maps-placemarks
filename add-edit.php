<p>Enter the latitude and longitude of the placemark below. You can geocode an address (convert it to latitude/longtide) at <a href="http://www.gpsvisualizer.com/geocode">GPS Visualizer's Quick Geocoder</a>.</p>

<table id="bgmp-placemark-coordinates">
	<tbody>
		<tr>
			<th><label for="<?php echo self::PREFIX; ?>latitude">Latitude:</label></th>
			<td><input id="<?php echo self::PREFIX; ?>latitude" name="<?php echo self::PREFIX; ?>latitude" type="text" value="<?php echo $latitude; ?>" /></td>
		</tr>
		
		<tr>
			<th><label for="<?php echo self::PREFIX; ?>longitude">Longitude:</label></th>
			<td><input id="<?php echo self::PREFIX; ?>longitude" name="<?php echo self::PREFIX; ?>longitude" type="text" value="<?php echo $longitude; ?>" /></td>
		</tr>
	</tbody>
</table>
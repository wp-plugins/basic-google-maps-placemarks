<p>Enter the address of the placemark. You can type in anything that you would type into a Google Maps search field, from a full address to an intersection, landmark, city or just a zip code.</p>

<table id="bgmp-placemark-coordinates">
	<tbody>
		<tr>
			<th><label for="<?php echo self::PREFIX; ?>address">Address:</label></th>
			<td>
				<input id="<?php echo self::PREFIX; ?>address" name="<?php echo self::PREFIX; ?>address" type="text" value="<?php echo $address; ?>" />
				
				<?php if( $showGeocodeResults ) : ?>
					<em>(Geocoded to: <?php echo $latitude; ?>, <?php echo $longitude; ?>)</em>
			
				<?php elseif( $showGeocodeError ) : ?>
					<em>(Error geocoding address. Please make sure it's correct and try again.)</em>
				<?php endif; ?>
			</td>
		</tr>
	</tbody>
</table>
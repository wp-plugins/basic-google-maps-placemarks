/**
 * A Wordpress plugin that adds a custom post type for placemarks and builds a Google Map with them
 * @package BasicGoogleMapsPlacemarks
 * @author Ian Dunn <ian@iandunn.name>
 * @link http://wordpress.org/extend/plugins/basic-google-maps-placemarks/
 */

$ = jQuery.noConflict();

/**
 * Main jQuery function
 * @author Ian Dunn <ian@iandunn.name>
 */
$(document).ready( function()
{
	bgmp.canvas = document.getElementById("bgmp-map-canvas");	// bgmp is created inline via wp_localize_script() before this file is included. Also, we have to use getElementById instead of a jQuery selector here in order to pass it to the Maps API.
		
	if(bgmp.canvas)
		bgmp_buildMap(bgmp.canvas);
} );


/**
 * jQuery AJAX error handler
 * @author Ian Dunn <ian@iandunn.name>
 */
$(document).ajaxError( function(event, jqxhr, settings, exception)
{
	if( window.console )
		console.log(".ajaxError():\nreadyState: " + jqxhr.readyState + "\nstatus: " + jqxhr.status + "\nresponseText: " + jqxhr.responseText + "\nexception: "+ exception);
	$(bgmp.canvas).html('Error: Generic AJAX failure.');
} );


/**
 * Pull in the map options from Wordpress' database and create the map
 * @author Ian Dunn <ian@iandunn.name>
 */
function bgmp_buildMap()
{
	var mapOptions;
	
	// @todo - need to test here when options haven't been set yet. show error telling user to set them
	
	$.post
	(
		bgmp.postURL,
		{
			action: 'bgmp_get_map_options',
			nonce: bgmp.nonce
		},
		function( response ) 
		{
			if( response == -1 )
				$(bgmp.canvas).html("Error: couldn't load map options.");
			else
			{
				if( response.zoom == '' || response.latitude == '' || response.longitude == '' )
				{
					$(bgmp.canvas).html("Error: map options not set.");
					return;
				}
				
				mapOptions = 
				{
					zoom: parseInt(response.zoom),
					center: new google.maps.LatLng( parseFloat(response.latitude), parseFloat(response.longitude) ),
					mapTypeId: google.maps.MapTypeId.ROADMAP,
					mapTypeControl: false
				};
				
				bgmp.infoWindowWidth = response.infoWindowWidth;
				bgmp.infoWindowHeight = response.infoWindowHeight;
				
				try
				{
					map = new google.maps.Map(bgmp.canvas, mapOptions);
					bgmp_addPlacemarks(map);
				}
				catch(e)
				{
					$(bgmp.canvas).html("Error: couln't build map.");
					if(window.console)
						console.log('bgmp_buildMap: '+ e);
				}
			}
		}
	);
}


/**
 * Pull the placemark posts from Wordpress' database and add them to the map
 * @author Ian Dunn <ian@iandunn.name>
 * @param object map Google Maps map
 */
function bgmp_addPlacemarks(map)
{
	var placemarks;
	
	$.post(
		bgmp.postURL,
		{
			action: 'bgmp_get_placemarks',
			nonce: bgmp.nonce
		},
		function( placemarks ) 
		{
			if(placemarks == -1)
				$(bgmp.canvas).html("Error: couldn't load map placemarks.");
			else
			{
				if( placemarks.length > 0 )
					for(var p in placemarks)
						bgmp_createMarker( map, placemarks[p]['title'], parseFloat(placemarks[p]['latitude']), parseFloat(placemarks[p]['longitude']), placemarks[p]['details'], placemarks[p]['icon'] );
			}
		} 
	);
}


/**
 * Create a marker with an information window
 * @author Ian Dunn <ian@iandunn.name>
 * @param object map Google Maps map
 * @param string title Placemark title
 * @param float latituded
 * @param float longitude
 * @param string details Content of the infowinder
 * @param string icon URL of the icon
 * @return bool True on success, false on failure
 */
function bgmp_createMarker( map, title, latitude, longitude, details, icon)
{
	// @todo - clean up variable names
	
	var infowindowcontent, infowindow, marker;
	
	if( latitude == '' || longitude == '' || icon == null )
	{
		if( window.console )
			console.log('bgmp_createMarker(): Not all of the required data was passed in.');
		return false;
	}
	
	infowindowcontent = '<div class="bgmp_placemark"> <h1>'+ title +'</h1> <div>'+ details +'</div> </div>';
	
	try
	{
		infowindow = new google.maps.InfoWindow( { content: infowindowcontent } );
		marker = new google.maps.Marker(
		{
			position: new google.maps.LatLng(latitude, longitude),
			map: map,
			icon: icon
		} );
		
		google.maps.event.addListener( marker, 'click', function()
		{
			if( bgmp.previousInfoWindow != '')
				bgmp.previousInfoWindow.close();
			infowindow.open(map, marker);
			bgmp.previousInfoWindow = infowindow;
		} );
		
		return true;
	}
	catch(e)
	{
		//$(bgmp.canvas).append("<p>Error: couldn't add map placemarks.</p>");		// add class for making red? other places need this too?	// @todo - need to figure out a good way to alert user that placemarks couldn't be added
		if( window.console )
			console.log('bgmp_createMarker: '+ e);
	}
}
/**
 * @package BasicGoogleMapsPlacemarks
 * @author Ian Dunn <ian@iandunn.name>
 * @link http://wordpress.org/extend/plugins/basic-google-maps-placemarks/
 */

 
/**
 * Wrapper function to safely use $
 * @author Ian Dunn <ian@iandunn.name>
 */
function bgmp_wrapper($) 
{
	// @todo - figure out if wrapper bad for memory consumption (https://developer.mozilla.org/en/JavaScript/Reference/Functions_and_function_scope#Efficiency_considerations)
	
	var bgmp = 
	{
		/**
		 * Main entry point
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		init : function()
		{
			bgmp.name				= 'Basic Google Maps Placemarks';
			bgmp.canvas				= document.getElementById("bgmp_map-canvas");	// We have to use getElementById instead of a jQuery selector here in order to pass it to the Maps API.
			bgmp.postURL			= $('#bgmp_postURL').val();
			bgmp.nonce				= $('#bgmp_nonce').val();
			bgmp.previousInfoWindow	= '';
			bgmp.infoWindowWidth	= bgmp.infoWindowHeight = 0;
			
			if( bgmp.canvas && bgmp.postURL && bgmp.nonce )
				bgmp.buildMap( bgmp.canvas );
			else
				$( bgmp.canvas ).html( bgmp.name + " error: couldn't retrieve DOM elements.");
		},
		
		/**
		 * Pull in the map options from Wordpress' database and create the map
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		buildMap : function()
		{
			var mapOptions;
			
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
						$( bgmp.canvas ).html( bgmp.name + " error: couldn't load map options.");
					else
					{
						if( response.mapWidth == '' || response.mapHeight == ''|| response.latitude == '' || response.longitude == '' || response.zoom == '' || response.infoWindowWidth == '' || response.infoWindowHeight == '' )
						{
							$( bgmp.canvas ).html( bgmp.name + " error: map options not set.");
							return;
						}
						
						mapOptions = 
						{
							zoom			: parseInt(response.zoom),
							center			: new google.maps.LatLng( parseFloat(response.latitude), parseFloat(response.longitude) ),
							mapTypeId		: google.maps.MapTypeId.ROADMAP,
							mapTypeControl	: false
						};
						
						// Override default width/heights from settings
						$('#bgmp_map-canvas').css('width', response.mapWidth );
						$('#bgmp_map-canvas').css('height', response.mapHeight );
						bgmp.infoWindowMaxWidth = response.infoWindowMaxWidth;
						
						// Create map
						try
						{
							map = new google.maps.Map( bgmp.canvas, mapOptions );
							bgmp.addPlacemarks(map);
						}
						catch(e)
						{
							$( bgmp.canvas ).html( bgmp.name + " error: couln't build map.");
							if(window.console)
								console.log('bgmp_buildMap: '+ e);
						}
					}
				}
			);
		},

		/**
		 * Pull the placemark posts from Wordpress' database and add them to the map
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param object map Google Maps map
		 */
		addPlacemarks : function(map)
		{
			var placemarks;
			
			$.post
			(
				bgmp.postURL,
				{
					action	: 'bgmp_get_placemarks',
					nonce	: bgmp.nonce
				},
				
				function( placemarks ) 
				{
					if( placemarks == -1 )
						$( bgmp.canvas ).html( bgmp.name + " error: couldn't load map placemarks." );
					else
					{
						if( placemarks.length > 0 )
							for(var p in placemarks)
								bgmp.createMarker( map, placemarks[p]['title'], parseFloat(placemarks[p]['latitude']), parseFloat(placemarks[p]['longitude']), placemarks[p]['details'], placemarks[p]['icon'] );
					}
				}
			);
		},

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
		createMarker : function( map, title, latitude, longitude, details, icon )
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
				infowindow = new google.maps.InfoWindow( {
					content:	infowindowcontent,
					maxWidth:	bgmp.infoWindowMaxWidth
				} );
				
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
				//$( bgmp.canvas ).append( '<p>' + bgmp.name + " error: couldn't add map placemarks.</p>");		// add class for making red? other places need this too?	// @todo - need to figure out a good way to alert user that placemarks couldn't be added
				if( window.console )
					console.log('bgmp_createMarker: '+ e);
			}
		}
	} // end bgmp
	
	/**
	 * jQuery AJAX error handler
	 * @author Ian Dunn <ian@iandunn.name>
	 * @param ? event
	 * @param ? jqxhr jQuery XML HTTP request object
	 * @param ? settings
	 * @param ? exception
	 */
	$(document).ajaxError( function(event, jqxhr, settings, exception)
	{
		// @todo - update docs w/ object types
		
		if( window.console )
			console.log(".ajaxError():\nreadyState: " + jqxhr.readyState + "\nstatus: " + jqxhr.status + "\nresponseText: " + jqxhr.responseText + "\nexception: "+ exception);
		$( bgmp.canvas ).html( bgmp.name + ' error: Generic AJAX failure.');
	} );
	
	// Kick things off...
	$(document).ready( bgmp.init );
	
} // end bgmp_wrapper()

bgmp_wrapper(jQuery);
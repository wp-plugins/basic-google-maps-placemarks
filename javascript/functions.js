/**
 * @package Basic_Google_Maps_Placemarks
 * @link    http://wordpress.org/extend/plugins/basic-google-maps-placemarks/
 */

var BasicGoogleMapsPlacemarks = ( function( $ ) {
	var name, canvas, map, markerClusterer, markers, infoWindowContent, templateOptions;
	
	/**
	 * Constructor
	 */
	function init() {
		// Initialize variables
		name              = 'Basic Google Maps Placemarks';
		canvas            = document.getElementById( 'bgmp_map-canvas' );    // We have to use getElementById instead of a jQuery selector here in order to pass it to the Maps API.
		map               = undefined;
		markerClusterer   = undefined;
		markers           = {};
		infoWindowContent = {};
		templateOptions   = {
			evaluate:    /<#([\s\S]+?)#>/g,
			interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
			escape:      /\{\{([^\}]+?)\}\}(?!\})/g
		};

		if ( 'undefined' === typeof bgmpData ) {
			$( canvas ).html( name + " error: bgmpData undefined." );
			return;
		}

		// Initialize single info window to reuse for each placemark
		infoWindow = new google.maps.InfoWindow( {
			content : '',
			maxWidth: bgmpData.options.infoWindowMaxWidth,
			pixelOffset: new google.maps.Size( bgmpData.options.infoWindowPixelOffset.width, bgmpData.options.infoWindowPixelOffset.height )
		} );

		// Format numbers
		bgmpData.options.zoom                = parseInt( bgmpData.options.zoom );
		bgmpData.options.latitude            = parseFloat( bgmpData.options.latitude );
		bgmpData.options.longitude           = parseFloat( bgmpData.options.longitude );
		bgmpData.options.clustering.maxZoom  = parseInt( bgmpData.options.clustering.maxZoom );
		bgmpData.options.clustering.gridSize = parseInt( bgmpData.options.clustering.gridSize );

		// Register event handlers
		$( '.' + 'bgmp_list' ).find( 'a' ).filter( '.' + 'bgmp_view-on-map' ).click( viewOnMap );

		// Build map
		if ( canvas ) {
			buildMap();
		} else {
			$( canvas ).html( name + " error: couldn't retrieve DOM elements." );
		}
	}

	/**
	 * Pull in the map options from WordPress' database and create the map
	 */
	function buildMap() {
		var mapOptions;

		if ( '' == bgmpData.options.mapWidth || '' == bgmpData.options.mapHeight || '' == bgmpData.options.latitude || '' == bgmpData.options.longitude || '' == bgmpData.options.zoom || '' == bgmpData.options.infoWindowMaxWidth ) {
			// @todo update w/ cluster options?

			$( canvas ).html( name + " error: map options not set." );
			return;
		}

		mapOptions = {
			'zoom'                     : bgmpData.options.zoom,
			'center'                   : new google.maps.LatLng( bgmpData.options.latitude, bgmpData.options.longitude ),
			'mapTypeId'                : google.maps.MapTypeId[ bgmpData.options.type ],
			'mapTypeControl'           : 'off' == bgmpData.options.typeControl ? false : true,
			'mapTypeControlOptions'    : { style: google.maps.MapTypeControlStyle[ bgmpData.options.typeControl ] },
			'navigationControl'        : 'off' == bgmpData.options.navigationControl ? false : true,
			'navigationControlOptions' : { style: google.maps.NavigationControlStyle[ bgmpData.options.navigationControl ] },
			'streetViewControl'        : bgmpData.options.streetViewControl
		};

		// Override default width/heights from settings
		$( '#' + 'bgmp_map-canvas' ).css( 'width',  bgmpData.options.mapWidth );    // @todo use canvas intead of hardcoding it?
		$( '#' + 'bgmp_map-canvas' ).css( 'height', bgmpData.options.mapHeight );
		// @todo this prevents users from using their own stylesheet?


		// Create the map
		try {
			map = new google.maps.Map( canvas, mapOptions );
		} catch ( e ) {
			$( canvas ).html( name + " error: couldn't build map." );
			if ( window.console )
				console.log( 'bgmp_buildMap: ' + e );

			return;
		}

		addPlacemarks( map );    // @todo not supposed to add them when clustering is enabled? http://www.youtube.com/watch?v=Z2VF9uKbQjI


		// Activate marker clustering
		if ( bgmpData.options.clustering.enabled ) {
			// BGMP stores markers in an object for direct access (e.g., markers[ 15 ] for ID 15), but MarkerCluster requires an array instead, so we convert them 
			var markersArray = [];
			for ( var m in markers ) {
				markersArray.push( markers[ m ] );
			}

			markerClusterer = new MarkerClusterer(
				map,
				markersArray,
				{
					maxZoom  : bgmpData.options.clustering.maxZoom,
					gridSize : bgmpData.options.clustering.gridSize,
					styles   : bgmpData.options.clustering.styles[ bgmpData.options.clustering.style ]
				}
			);
		}
	}

	/**
	 * Checks if the value is an integer. Slightly modified version of original.
	 * @author Invent Partners
	 * @link http://www.inventpartners.com/content/javascript_is_int
	 *
	 * @param mixed value
	 *
	 * @return bool
	 */
	function isInt( value ) {
		if ( !isNaN( value ) && parseFloat( value ) == parseInt( value ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Pull the placemark posts from WordPress' database and add them to the map
	 *
	 * @param object map Google Maps map
	 */
	function addPlacemarks( map ) {
		// @todo - should probably refactor this since you pulled out the ajax. update phpdoc too

		if ( bgmpData.markers.length > 0 ) {
			for ( var m in bgmpData.markers ) {
				createMarker(
					map,
					bgmpData.markers[ m ][ 'id' ],
					bgmpData.markers[ m ][ 'title' ],
					bgmpData.markers[ m ][ 'latitude' ],
					bgmpData.markers[ m ][ 'longitude' ],
					bgmpData.markers[ m ][ 'details' ],
					bgmpData.markers[ m ][ 'icon' ],
					parseInt( bgmpData.markers[ m ][ 'zIndex' ] )
				);
			}
		}
	}

	/**
	 * Create a marker with an information window
	 *
	 * @param object map Google Maps map
	 * @param int    id ID of the marker post
	 * @param string title Placemark title
	 * @param float  latituded
	 * @param float  longitude
	 * @param string details Content of the infowinder
	 * @param string icon URL of the icon
	 * @param int    zIndex The desired position in the placemark stacking order
	 *
	 * @return bool True on success, false on failure
	 */
	function createMarker( map, id, title, latitude, longitude, details, icon, zIndex ) {
		var infoWindowContent, marker,
			infoWindowTemplate = _.template( $( '#tmpl-bgmp-info-window-content' ).html(), null, templateOptions );

		if ( isNaN( latitude ) || isNaN( longitude ) ) {
			if ( window.console )
				console.log( prefix + "createMarker(): " + title + " latitude and longitude weren't valid." );

			return false;
		}

		if ( null == icon ) {
			// @todo - this check may not be needed anymore

			if ( window.console ) {
				console.log( prefix + "createMarker(): " + title + "  icon wasn't passed in." );
			}

			return false;
		}

		if ( ! isInt( zIndex ) ) {
			//if( window.console )
			//console.log( prefix + "createMarker():  "+ title +" z-index wasn't valid." );	// this would fire any time it's empty

			zIndex = 0;
		}

		infoWindowContent = infoWindowTemplate( {
			id:		   id,
			title:     title,
			details:   details,
			latitude:  latitude,
			longitude: longitude,
			icon:      icon
		} );

		try {
			// Replace commas with periods. Some (human) languages use commas to delimit the fraction from the whole number, but Google Maps doesn't accept that.
			latitude  = parseFloat( latitude.replace(  ',', '.' ) );
			longitude = parseFloat( longitude.replace( ',', '.' ) );

			marker = new google.maps.Marker( {
				'bgmpID'   : id,
				'position' : new google.maps.LatLng( latitude, longitude ),
				'map'      : map,
				'icon'     : icon,
				'title'    : title,
				'zIndex'   : zIndex
			} );

			markers[ id ]           = marker;	// todo just have a single object to store all this, like wordcamp central theme does it. probably other lessons to learn from there too
			infoWindowContent[ id ] = infoWindowContent;

			google.maps.event.addListener( marker, 'click', function () {
				openInfoWindow( map, marker, infoWindowContent );
			} );

			return true;
		} catch ( e ) {
			//$( canvas ).append( '<p>' + name + " error: couldn't add map placemarks.</p>");		// add class for making red? other places need this too?	// @todo - need to figure out a good way to alert user that placemarks couldn't be added

			if ( window.console ) {
				console.log( 'bgmp_createMarker: ' + e );
			}
		}
	}

	/**
	 * Opens an info window on the map
	 *
	 * @param object map
	 * @param object marker
	 * @param string infoWindowContent
	 */
	function openInfoWindow( map, marker, infoWindowContent ) {
		infoWindow.setContent( infoWindowContent );
		infoWindow.open( map, marker );

		if ( bgmpData.options.viewOnMapScroll ) {
			$( 'html, body' ).animate(
				{ scrollTop: $( '#' + 'bgmp_map-canvas' ).offset().top },
				900
			);
		}
	}

	/**
	 * Focuses the [bgmp-map] on the marker that corresponds to the [bgmp-list] link that was clicked
	 *
	 * @param object event
	 */
	function viewOnMap( event ) {
		var id = $( this ).data( 'marker-id' );
		openInfoWindow( map, markers[ id ], infoWindowContent[ id ] );
	}

	/*
	 * Reveal public methods
	 */
	return {
		init: init
	};
} )( jQuery );

jQuery( document ).ready( BasicGoogleMapsPlacemarks.init( bgmpData ) );

<?php

if( basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__) )
	die("Access denied.");

if( !class_exists('BGMPSettings') )
{
	/**
	 * Registers and handles the plugin's settings
	 *
	 * @package BasicGoogleMapsPlacemarks
	 * @author Ian Dunn <ian@iandunn.name>
	 * @link http://wordpress.org/extend/plugins/basic-google-maps-placemarks/
	 */
	class BGMPSettings
	{
		protected $bgmp;
		public $mapWidth, $mapHeight, $mapAddress, $mapLatitude, $mapLongitude, $mapZoom, $mapInfoWindowWidth, $mapInfoWindowHeight;
		const PREFIX = 'bgmp_';
		
		/**
		 * Constructor
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function __construct( $bgmp )
		{
			$this->bgmp					= $bgmp;
			$this->mapWidth				= get_option( self::PREFIX . 'map-width' );
			$this->mapHeight			= get_option( self::PREFIX . 'map-height' );
			$this->mapAddress			= get_option( self::PREFIX . 'map-address' );
			$this->mapLatitude			= get_option( self::PREFIX . 'map-latitude' );
			$this->mapLongitude			= get_option( self::PREFIX . 'map-longitude' );
			$this->mapZoom				= get_option( self::PREFIX . 'map-zoom' );
			$this->mapInfoWindowWidth	= get_option( self::PREFIX . 'map-info-window-width' );
			$this->mapInfoWindowHeight	= get_option( self::PREFIX . 'map-info-window-height' );
			
			add_action( 'admin_init', array($this, 'addSettings') );
			add_filter( 'plugin_action_links_basic-google-maps-placemarks/basic-google-maps-placemarks.php', array($this, 'addSettingsLink') );
			
			$this->updateMapCoordinates();
		}
		
		/**
		 * Get the map center coordinates from the address and update the database values
		 * The latitude/longitude need to be updated when the address changes, but there's no way to do that with the settings API
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		protected function updateMapCoordinates()
		{
			$haveCoordinates = true;
			
			if( isset($_POST) && array_key_exists( self::PREFIX . 'map-address', $_POST ) )
			{
				if( empty( $_POST[ self::PREFIX . 'map-address' ] ) )
					$haveCoordinates = false;
				else
				{
					$coordinates = $this->bgmp->geocode( $_POST[ self::PREFIX . 'map-address'] );
				
					if( !$coordinates )
						$haveCoordinates = false;
				}
				
				if( $haveCoordinates)
				{
					update_option( self::PREFIX . 'map-latitude', $coordinates['latitude'] );
					update_option( self::PREFIX . 'map-longitude', $coordinates['longitude'] );
				}
				else
				{
					// add error message for user
					
					update_option( self::PREFIX . 'map-latitude', '' );
					update_option( self::PREFIX . 'map-longitude', '' );
				}
			}
		}
		
		/**
		 * Adds a 'Settings' link to the Plugins page
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $links The links currently mapped to the plugin
		 * @return array
		 */
		public function addSettingsLink($links)
		{
			array_unshift($links, '<a href="options-writing.php">Settings</a>');
			return $links; 
		}
		
		/**
		 * Adds our custom settings to the admin Settings pages
		 * We intentionally don't register the map-latitude and map-longitude settings because they're set by updateMapCoordinates()
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function addSettings()
		{
			add_settings_section(self::PREFIX . 'map-settings', 'Basic Google Maps Placemarks', array($this, 'settingsSectionCallback'), 'writing');
			
			add_settings_field(self::PREFIX . 'map-width',				'Map Width',			array($this, 'mapWidthCallback'),				'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-height',				'Map Height',			array($this, 'mapHeightCallback'),				'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-address',			'Map Center Address',	array($this, 'mapAddressCallback'),				'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-latitude',			'Map Center Latitude',	array($this, 'mapLatitudeCallback'),			'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-longitude',			'Map Center Longitude',	array($this, 'mapLongitudeCallback'),			'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-zoom',				'Zoom',					array($this, 'mapZoomCallback'),				'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-info-window-width',	'Info Window Width',	array($this, 'mapInfoWindowWidthCallback'),		'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-info-window-height',	'Info Window Height',	array($this, 'mapInfoWindowHeightCallback'),	'writing', self::PREFIX . 'map-settings');
			
			register_setting('writing', self::PREFIX . 'map-width');
			register_setting('writing', self::PREFIX . 'map-height');
			register_setting('writing', self::PREFIX . 'map-address');
			register_setting('writing', self::PREFIX . 'map-zoom');
			register_setting('writing', self::PREFIX . 'map-info-window-width');
			register_setting('writing', self::PREFIX . 'map-info-window-height');
			
			// need to add labels to the names so they can click on name?
		}
		
		/**
		 * Adds the section introduction text to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function settingsSectionCallback()
		{
			echo '<p>These settings determine the size and center of the map, zoom level and popup window size. For the center address, you can type in anything that you would type into a Google Maps search field, from a full address to an intersection, landmark, city or just a zip code.</p>';
		}
		
		/**
		 * Adds the map-width field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapWidthCallback()
		{
			echo '<input id="'. self::PREFIX .'map-width" name="'. self::PREFIX .'map-width" type="text" value="'. $this->mapWidth .'" class="code" /> pixels';
		}
		
		/**
		 * Adds the map-height field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapHeightCallback()
		{
			echo '<input id="'. self::PREFIX .'map-height" name="'. self::PREFIX .'map-height" type="text" value="'. $this->mapHeight .'" class="code" /> pixels';
		}
		
		/**
		 * Adds the address field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapAddressCallback()
		{
			echo '<input id="'. self::PREFIX .'map-address" name="'. self::PREFIX .'map-address" type="text" value="'. $this->mapAddress .'" class="code" />';
		}
		
		/**
		 * Adds the latitude field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapLatitudeCallback()
		{
			echo '<input id="'. self::PREFIX .'map-latitude" name="'. self::PREFIX .'map-latitude" type="text" value="'. $this->mapLatitude .'" class="code" readonly="readonly" />';
		}
		
		/**
		 * Adds the longitude field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapLongitudeCallback()
		{
			echo '<input id="'. self::PREFIX .'map-longitude" name="'. self::PREFIX .'map-longitude" type="text" value="'. $this->mapLongitude .'" class="code" readonly="readonly" />';
		}
		
		/**
		 * Adds the zoom field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapZoomCallback()
		{
			echo '<input id="'. self::PREFIX .'map-zoom" name="'. self::PREFIX .'map-zoom" type="text" value="'. $this->mapZoom .'" class="code" />';
		}
		
		/**
		 * Adds the info-window-width field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapInfoWindowWidthCallback()
		{
			echo '<input id="'. self::PREFIX .'map-info-window-width" name="'. self::PREFIX .'map-info-window-width" type="text" value="'. $this->mapInfoWindowWidth .'" class="code" /> pixels';
		}
		
		/**
		 * Adds the info-window-height field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapInfoWindowHeightCallback()
		{
			echo '<input id="'. self::PREFIX .'map-info-window-height" name="'. self::PREFIX .'map-info-window-height" type="text" value="'. $this->mapInfoWindowHeight .'" class="code" /> pixels';
		}
	} // end BGMPSettings
}

?>
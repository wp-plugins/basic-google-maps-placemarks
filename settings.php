<?php

if( $_SERVER['SCRIPT_FILENAME'] == __FILE__ )
	die("Access denied.");

if( !class_exists( 'BGMPSettings' ) )
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
		public $mapWidth, $mapHeight, $mapAddress, $mapLatitude, $mapLongitude, $mapZoom, $mapType, $mapTypes, $mapTypeControl, $mapNavigationControl, $mapInfoWindowMaxWidth;
					
		/**
		 * Constructor
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param object BasicGoogleMapsPlacemarks object
		 */
		public function __construct( $bgmp )
		{
			$this->bgmp						= $bgmp;
			$this->mapWidth					= get_option( BasicGoogleMapsPlacemarks::PREFIX . 'map-width',				600 );
			$this->mapHeight				= get_option( BasicGoogleMapsPlacemarks::PREFIX . 'map-height',				400 );
			$this->mapAddress				= get_option( BasicGoogleMapsPlacemarks::PREFIX . 'map-address',			'Seattle' );
			$this->mapLatitude				= get_option( BasicGoogleMapsPlacemarks::PREFIX . 'map-latitude',			47.6062095 );
			$this->mapLongitude				= get_option( BasicGoogleMapsPlacemarks::PREFIX . 'map-longitude',			-122.3320708 );
			$this->mapZoom					= get_option( BasicGoogleMapsPlacemarks::PREFIX . 'map-zoom',				7 );
			$this->mapType					= get_option( BasicGoogleMapsPlacemarks::PREFIX . 'map-type',				'ROADMAP' );
			$this->mapTypes					= array(
				'ROADMAP'	=> 'Street Map',
				'SATELLITE'	=> 'Satellite Images',
				'HYBRID'	=> 'Hybrid',
				'TERRAIN'	=> 'Terrain'
			);
			$this->mapTypeControl			= get_option( BasicGoogleMapsPlacemarks::PREFIX . 'map-type-control',		'off' );
			$this->mapNavigationControl		= get_option( BasicGoogleMapsPlacemarks::PREFIX . 'map-navigation-control',	'DEFAULT' );
			$this->mapInfoWindowMaxWidth	= get_option( BasicGoogleMapsPlacemarks::PREFIX . 'map-info-window-width',	500 );	// @todo - this isn't DRY, same values in BGMP::singleActivate() and upgrade()
			
			add_action( 'admin_menu',		array( $this, 'addSettingsPage' ) );
			add_action( 'admin_init',		array( $this, 'addSettings') );			// @todo - this may need to fire after admin_menu
			add_filter( 'plugin_action_links_basic-google-maps-placemarks/basic-google-maps-placemarks.php', array($this, 'addSettingsLink') );
			
			$this->updateMapCoordinates();	// @todo - set this to fire on a hook
		}
		
		/**
		 * Get the map center coordinates from the address and update the database values
		 * The latitude/longitude need to be updated when the address changes, but there's no way to do that with the settings API
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		protected function updateMapCoordinates()
		{
			$haveCoordinates = true;
			
			if( isset( $_POST[ BasicGoogleMapsPlacemarks::PREFIX . 'map-address' ] ) )
			{
				if( empty( $_POST[ BasicGoogleMapsPlacemarks::PREFIX . 'map-address' ] ) )
					$haveCoordinates = false;
				else
				{
					$coordinates = $this->bgmp->geocode( $_POST[ BasicGoogleMapsPlacemarks::PREFIX . 'map-address'] );
				
					if( !$coordinates )
						$haveCoordinates = false;
				}
				
				if( $haveCoordinates )
				{
					update_option( BasicGoogleMapsPlacemarks::PREFIX . 'map-latitude', $coordinates['latitude'] );
					update_option( BasicGoogleMapsPlacemarks::PREFIX . 'map-longitude', $coordinates['longitude'] );
				}
				else
				{
					// @todo - can't call protected from this class - $this->bgmp->enqueueMessage('That address couldn\'t be geocoded, please make sure that it\'s correct.', 'error' );
					
					update_option( BasicGoogleMapsPlacemarks::PREFIX . 'map-latitude', '' );	// @todo - update these
					update_option( BasicGoogleMapsPlacemarks::PREFIX . 'map-longitude', '' );
				}
			}
		}
		
		/**
		 * Adds a page to Settings menu
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function addSettingsPage()
		{
			add_options_page( BGMP_NAME .' Settings', BGMP_NAME, 'manage_options', BasicGoogleMapsPlacemarks::PREFIX . 'settings', array( $this, 'markupSettingsPage' ) );
		}
		
		/**
		 * Creates the markup for the settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function markupSettingsPage()
		{
			if( current_user_can( 'manage_options' ) )
				require_once( dirname(__FILE__) . '/views/settings.php' );
			else
				wp_die( 'Access denied.' );
		}
		
		/**
		 * Adds a 'Settings' link to the Plugins page
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $links The links currently mapped to the plugin
		 * @return array
		 */
		public function addSettingsLink( $links )
		{
			array_unshift( $links, '<a href="http://wordpress.org/extend/plugins/basic-google-maps-placemarks/faq/">'. __( 'Help', BasicGoogleMapsPlacemarks::I18N_DOMAIN ) .'</a>' );
			array_unshift( $links, '<a href="options-general.php?page='. BasicGoogleMapsPlacemarks::PREFIX . 'settings">'. __( 'Settings', BasicGoogleMapsPlacemarks::I18N_DOMAIN ) .'</a>' );
			
			return $links; 
		}
		
		/**
		 * Adds our custom settings to the admin Settings pages
		 * We intentionally don't register the map-latitude and map-longitude settings because they're set by updateMapCoordinates()
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function addSettings()
		{
			add_settings_section( BasicGoogleMapsPlacemarks::PREFIX . 'map-settings', '', array($this, 'settingsSectionCallback'), BasicGoogleMapsPlacemarks::PREFIX . 'settings' );
			
			add_settings_field( BasicGoogleMapsPlacemarks::PREFIX . 'map-width',				__( 'Map Width', BasicGoogleMapsPlacemarks::I18N_DOMAIN ),					array($this, 'mapWidthCallback'),					BasicGoogleMapsPlacemarks::PREFIX . 'settings', BasicGoogleMapsPlacemarks::PREFIX . 'map-settings',	 array( 'label_for' => BasicGoogleMapsPlacemarks::PREFIX . 'map-width' ) );
			add_settings_field( BasicGoogleMapsPlacemarks::PREFIX . 'map-height',				__( 'Map Height', BasicGoogleMapsPlacemarks::I18N_DOMAIN ),					array($this, 'mapHeightCallback'),					BasicGoogleMapsPlacemarks::PREFIX . 'settings', BasicGoogleMapsPlacemarks::PREFIX . 'map-settings',	 array( 'label_for' => BasicGoogleMapsPlacemarks::PREFIX . 'map-height' ) );
			add_settings_field( BasicGoogleMapsPlacemarks::PREFIX . 'map-address',				__( 'Map Center Address', BasicGoogleMapsPlacemarks::I18N_DOMAIN ),			array($this, 'mapAddressCallback'),					BasicGoogleMapsPlacemarks::PREFIX . 'settings', BasicGoogleMapsPlacemarks::PREFIX . 'map-settings',	 array( 'label_for' => BasicGoogleMapsPlacemarks::PREFIX . 'map-address' ) );
			add_settings_field( BasicGoogleMapsPlacemarks::PREFIX . 'map-zoom',					__( 'Zoom', BasicGoogleMapsPlacemarks::I18N_DOMAIN ),						array($this, 'mapZoomCallback'),					BasicGoogleMapsPlacemarks::PREFIX . 'settings', BasicGoogleMapsPlacemarks::PREFIX . 'map-settings',	 array( 'label_for' => BasicGoogleMapsPlacemarks::PREFIX . 'map-zoom' ) );
			add_settings_field( BasicGoogleMapsPlacemarks::PREFIX . 'map-type',					__( 'Map Type', BasicGoogleMapsPlacemarks::I18N_DOMAIN ),					array($this, 'mapTypeCallback'),					BasicGoogleMapsPlacemarks::PREFIX . 'settings', BasicGoogleMapsPlacemarks::PREFIX . 'map-settings',	 array( 'label_for' => BasicGoogleMapsPlacemarks::PREFIX . 'map-type' ) );			
			add_settings_field( BasicGoogleMapsPlacemarks::PREFIX . 'map-type-control',			__( 'Type Control', BasicGoogleMapsPlacemarks::I18N_DOMAIN ),				array($this, 'mapTypeControlCallback'),				BasicGoogleMapsPlacemarks::PREFIX . 'settings', BasicGoogleMapsPlacemarks::PREFIX . 'map-settings',	 array( 'label_for' => BasicGoogleMapsPlacemarks::PREFIX . 'map-type-control' ) );
			add_settings_field( BasicGoogleMapsPlacemarks::PREFIX . 'map-navigation-control',	__( 'Navigation Control', BasicGoogleMapsPlacemarks::I18N_DOMAIN ),			array($this, 'mapNavigationControlCallback'),		BasicGoogleMapsPlacemarks::PREFIX . 'settings', BasicGoogleMapsPlacemarks::PREFIX . 'map-settings',	 array( 'label_for' => BasicGoogleMapsPlacemarks::PREFIX . 'map-navigation-control' ) );
			add_settings_field( BasicGoogleMapsPlacemarks::PREFIX . 'map-info-window-width',	__( 'Info. Window Maximum Width', BasicGoogleMapsPlacemarks::I18N_DOMAIN ),	array($this, 'mapInfoWindowMaxWidthCallback'),		BasicGoogleMapsPlacemarks::PREFIX . 'settings', BasicGoogleMapsPlacemarks::PREFIX . 'map-settings',	 array( 'label_for' => BasicGoogleMapsPlacemarks::PREFIX . 'map-info-window-width' ) );
			
			register_setting( BasicGoogleMapsPlacemarks::PREFIX . 'settings', BasicGoogleMapsPlacemarks::PREFIX . 'map-width' );
			register_setting( BasicGoogleMapsPlacemarks::PREFIX . 'settings', BasicGoogleMapsPlacemarks::PREFIX . 'map-height' );
			register_setting( BasicGoogleMapsPlacemarks::PREFIX . 'settings', BasicGoogleMapsPlacemarks::PREFIX . 'map-address' );
			register_setting( BasicGoogleMapsPlacemarks::PREFIX . 'settings', BasicGoogleMapsPlacemarks::PREFIX . 'map-zoom' );
			register_setting( BasicGoogleMapsPlacemarks::PREFIX . 'settings', BasicGoogleMapsPlacemarks::PREFIX . 'map-type' );
			register_setting( BasicGoogleMapsPlacemarks::PREFIX . 'settings', BasicGoogleMapsPlacemarks::PREFIX . 'map-type-control' );
			register_setting( BasicGoogleMapsPlacemarks::PREFIX . 'settings', BasicGoogleMapsPlacemarks::PREFIX . 'map-navigation-control' );
			register_setting( BasicGoogleMapsPlacemarks::PREFIX . 'settings', BasicGoogleMapsPlacemarks::PREFIX . 'map-info-window-width' );
			
			// @todo - add input validation  -- http://ottopress.com/2009/wordpress-settings-api-tutorial/
		}
		
		/**
		 * Adds the section introduction text to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function settingsSectionCallback()
		{
			echo '<p>'. __( 'The map(s) will use these settings as defaults, but you can override them on individual maps using shortcode arguments. See <a href="http://wordpress.org/extend/plugins/basic-google-maps-placemarks/installation/">the Installation page</a> for details.', BasicGoogleMapsPlacemarks::I18N_DOMAIN ) .'</p>';
		}
		
		/**
		 * Adds the map-width field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapWidthCallback()
		{
			echo '<input id="'. BasicGoogleMapsPlacemarks::PREFIX .'map-width" name="'. BasicGoogleMapsPlacemarks::PREFIX .'map-width" type="text" value="'. $this->mapWidth .'" class="small-text" /> ';
			_e( 'pixels', BasicGoogleMapsPlacemarks::I18N_DOMAIN );
		}
		
		/**
		 * Adds the map-height field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapHeightCallback()
		{
			echo '<input id="'. BasicGoogleMapsPlacemarks::PREFIX .'map-height" name="'. BasicGoogleMapsPlacemarks::PREFIX .'map-height" type="text" value="'. $this->mapHeight .'" class="small-text" /> ';
			_e( 'pixels', BasicGoogleMapsPlacemarks::I18N_DOMAIN );
		}
		
		/**
		 * Adds the address field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapAddressCallback()
		{
			echo '<input id="'. BasicGoogleMapsPlacemarks::PREFIX .'map-address" name="'. BasicGoogleMapsPlacemarks::PREFIX .'map-address" type="text" value="'. $this->mapAddress .'" class="regular-text" />';
			
			if( $this->mapAddress && !$this->bgmp->validateCoordinates( $this->mapAddress ) && $this->mapLatitude && $this->mapLongitude )
				echo ' <em>('. __( 'Geocoded to:', BasicGoogleMapsPlacemarks::I18N_DOMAIN ) .' '. $this->mapLatitude .', '. $this->mapLongitude .')</em>';
				
			elseif( $this->mapAddress && ( !$this->mapLatitude || !$this->mapLongitude ) )
				echo " <em>". __( "(Error geocoding address. Please make sure it's correct and try again.)", BasicGoogleMapsPlacemarks::I18N_DOMAIN ) ."</em>";
				
			echo '<p>'. __( 'You can type in anything that you would type into a Google Maps search field, from a full address to an intersection, landmark, city, zip code or latitude/longitude coordinates.', BasicGoogleMapsPlacemarks::I18N_DOMAIN ) .'</p>';
		}
		
		/**
		 * Adds the zoom field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapZoomCallback()
		{
			echo '<input id="'. BasicGoogleMapsPlacemarks::PREFIX .'map-zoom" name="'. BasicGoogleMapsPlacemarks::PREFIX .'map-zoom" type="text" value="'. $this->mapZoom .'" class="small-text" /> ';
			printf( __( '%d (farthest) to %d (closest)', BasicGoogleMapsPlacemarks::I18N_DOMAIN ), BasicGoogleMapsPlacemarks::ZOOM_MIN, BasicGoogleMapsPlacemarks::ZOOM_MAX );
		}
		
		/**
		 * Adds the map type field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapTypeCallback()
		{
			echo '<select id="'. BasicGoogleMapsPlacemarks::PREFIX .'map-type" name="'. BasicGoogleMapsPlacemarks::PREFIX .'map-type">';
			
			foreach( $this->mapTypes as $code => $label )
				echo '<option value="'. $code .'" '. ( $this->mapType == $code ? 'selected="selected"' : '' ) .'>'. $label .'</option>';
			
			echo '</select>';
		}
		
		/**
		 * Adds the map type control field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapTypeControlCallback()
		{
			echo '<select id="'. BasicGoogleMapsPlacemarks::PREFIX .'map-type-control" name="'. BasicGoogleMapsPlacemarks::PREFIX .'map-type-control">
					<option value="off" '. ( $this->mapTypeControl == 'off' ? 'selected="selected"' : '' ) .'>'. __( 'Off', BasicGoogleMapsPlacemarks::I18N_DOMAIN ) .'</option>
					<option value="DEFAULT" '. ( $this->mapTypeControl == 'DEFAULT' ? 'selected="selected"' : '' ) .'>'. __( 'Automatic', BasicGoogleMapsPlacemarks::I18N_DOMAIN ) .'</option>
					<option value="HORIZONTAL_BAR" '. ( $this->mapTypeControl == 'HORIZONTAL_BAR' ? 'selected="selected"' : '' ) .'>'. __( 'Horizontal Bar', BasicGoogleMapsPlacemarks::I18N_DOMAIN ) .'</option>
					<option value="DROPDOWN_MENU" '. ( $this->mapTypeControl == 'DROPDOWN_MENU' ? 'selected="selected"' : '' ) .'>'. __( 'Dropdown Menu', BasicGoogleMapsPlacemarks::I18N_DOMAIN ) .'</option>
				</select>';
			
			_e( ' "Automatic" will automatically switch to the appropriate control based on the window size and other factors.', BasicGoogleMapsPlacemarks::I18N_DOMAIN );
		}
		
		/**
		 * Adds the map navigation controll field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapNavigationControlCallback()
		{
			echo '<select id="'. BasicGoogleMapsPlacemarks::PREFIX .'map-navigation-control" name="'. BasicGoogleMapsPlacemarks::PREFIX .'map-navigation-control">
					<option value="off" '. ( $this->mapNavigationControl == 'DEFAULT' ? 'selected="selected"' : '' ) .'>'. __( 'Off', BasicGoogleMapsPlacemarks::I18N_DOMAIN ) .'</option>
					<option value="DEFAULT" '. ( $this->mapNavigationControl == 'DEFAULT' ? 'selected="selected"' : '' ) .'>'. __( 'Automatic', BasicGoogleMapsPlacemarks::I18N_DOMAIN ) .'</option>
					<option value="SMALL" '. ( $this->mapNavigationControl == 'SMALL' ? 'selected="selected"' : '' ) .'>'. __( 'Small', BasicGoogleMapsPlacemarks::I18N_DOMAIN ) .'</option>
					<option value="ANDROID" '. ( $this->mapNavigationControl == 'ANDROID' ? 'selected="selected"' : '' ) .'>'. __( 'Android', BasicGoogleMapsPlacemarks::I18N_DOMAIN ) .'</option>
					<option value="ZOOM_PAN" '. ( $this->mapNavigationControl == 'ZOOM_PAN' ? 'selected="selected"' : '' ) .'>'. __( 'Zoom/Pan', BasicGoogleMapsPlacemarks::I18N_DOMAIN ) .'</option>
				</select>';
				
			_e( ' "Automatic" will automatically switch to the appropriate control based on the window size and other factors.', BasicGoogleMapsPlacemarks::I18N_DOMAIN );
		}
		
		/**
		 * Adds the info-window-width field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapInfoWindowMaxWidthCallback()
		{
			echo '<input id="'. BasicGoogleMapsPlacemarks::PREFIX .'map-info-window-width" name="'. BasicGoogleMapsPlacemarks::PREFIX .'map-info-window-width" type="text" value="'. $this->mapInfoWindowMaxWidth .'" class="small-text" /> ';
			_e( 'pixels', BasicGoogleMapsPlacemarks::I18N_DOMAIN );
		}
	} // end BGMPSettings
}

?>
<?php
/*
Plugin Name: Basic Google Maps Placemarks
Description: Adds a custom post type for placemarks and builds an embedded Google Map with them
Version: 1.1.1
Author: Ian Dunn
Author URI: http://iandunn.name
*/

/*  
 * Copyright 2011 Ian Dunn (email : ian@iandunn.name)
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as 
 * published by the Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if( basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__) )
	die("Access denied.");

define('BGMP_NAME', 'Basic Google Maps Placemarks');
define('BGMP_REQUIRED_PHP_VERSON', '5');

if( !class_exists('BasicGoogleMapsPlacemarks') )
{
	/**
	 * A Wordpress plugin that adds a custom post type for placemarks and builds a Google Map with them
	 * Requires PHP5+ because of various json_encode(), OOP features, pass by reference, etc
	 * Requires Wordpress 2.9 because of add_theme_support()
	 *
	 * @package BasicGoogleMapsPlacemarks
	 * @author Ian Dunn <ian@iandunn.name>
	 */
	class BasicGoogleMapsPlacemarks
	{
		// Declare variables and constants
		protected $settings, $options, $updatedOptions, $userMessageCount, $environmentOK, $mapShortcodeCalled;
		const BGMP_VERSION			= '1.1.1';
		const REQUIRED_WP_VERSION	= '2.9';
		const PREFIX				= 'bgmp_';
		const DEBUG_MODE			= false;
		// create constant for post type and switch to using it everywhere you have it manually typed in
		
		/**
		 * Constructor
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function __construct()
		{
			// Register action for error messages and updates, then check the environment
			add_action( 'admin_notices', array($this, 'printMessages') );
			$this->environmentOK = $this->checkEnvironment();
			if( !$this->environmentOK )
				return;
			
			// Initialize variables
			$defaultOptions								= array( 'updates' => array(), 'errors' => array() );
			$this->options								= array_merge( get_option( self::PREFIX . 'options', array() ), $defaultOptions );
			$this->updatedOptions						= false;
			$this->userMessageCount						= array( 'updates' => 0, 'errors' => 0 );
			$this->mapShortcodeCalled					= false;
			$this->settings['map-width']				= get_option( self::PREFIX . 'map-width' );
			$this->settings['map-height']				= get_option( self::PREFIX . 'map-height' );
			$this->settings['map-address']				= get_option( self::PREFIX . 'map-address' );
			$this->settings['map-latitude']				= get_option( self::PREFIX . 'map-latitude' );
			$this->settings['map-longitude']			= get_option( self::PREFIX . 'map-longitude' );
			$this->settings['map-zoom']					= get_option( self::PREFIX . 'map-zoom' );
			$this->settings['map-info-window-width']	= get_option( self::PREFIX . 'map-info-window-width' );
			$this->settings['map-info-window-height']	= get_option( self::PREFIX . 'map-info-window-height' );
			$this->updateMapCoordinates();
			
			// Register remaining actions, filters and shortcodes
			add_action( 'admin_init', 										array($this, 'addSettings') );
			add_action( 'init',												array($this, 'createPostType') );
			add_action( 'admin_init',										array($this, 'registerCustomFields') );
			add_action( 'save_post',										array($this, 'saveCustomFields') );
			add_action( 'init', 											array($this, 'loadStyle') );
			add_action( 'wp_footer',										array($this, 'loadScripts' ) );
			add_action( 'wp_ajax_bgmp_get_map_options',						array($this, 'getMapOptions' ) );
			add_action( 'wp_ajax_nopriv_bgmp_get_map_options',				array($this, 'getMapOptions' ) );
			add_action( 'wp_ajax_bgmp_get_placemarks',						array($this, 'getPlacemarks' ) );
			add_action( 'wp_ajax_nopriv_bgmp_get_placemarks',				array($this, 'getPlacemarks' ) );
			add_action( 'wp_head',											array($this, 'outputHead' ) );
			add_filter( 'plugin_action_links_'. plugin_basename(__FILE__),	array($this, 'addSettingsLink') );
			add_shortcode( 'bgmp-map',										array($this, 'mapShortcode') );
			add_shortcode( 'bgmp-list',										array($this, 'listShortcode') );
			register_activation_hook( __FILE__,								array($this, 'activate') );
			
			if( is_admin() )
			{
				add_theme_support( 'post-thumbnails' );	// does this work when called from a plugin? does it interfere with the theme calling it?
			}
			// else - registering hooks instead here inside of checking is_admin() in callbacks?
			// add theme support rquries 2.9
		}
		
		/**
		 * Checks whether the system requirements are met
		 * @author Ian Dunn <ian@iandunn.name>
		 * @return bool True if system requirements are met, false if not
		 */
		protected function checkEnvironment()
		{
			global $wp_version;
			$environmentOK = true;
			
			// Check Wordpress version
			if( version_compare($wp_version, self::REQUIRED_WP_VERSION, "<") )
			{
				$this->enqueueMessage(BGMP_NAME . ' requires <strong>Wordpress '. self::REQUIRED_WP_VERSION .'</strong> or newer in order to work. Please upgrade if you would like to use this plugin.', 'error');
				$environmentOK = false;
			}
			
			return $environmentOK;
		}
		
		/**
		 * Runs on plugin activation to prepare system for plugin usage
		 * @author Ian Dunn <ian@iandunn.name>
		 * @return bool True if system requirements are met, false if not
		 */
		public function activate()
		{
			// Save default settings
			if( !get_option( self::PREFIX . 'map-width' ) )
				add_option( self::PREFIX . 'map-width', 600 );
			if( !get_option( self::PREFIX . 'map-height' ) )
				add_option( self::PREFIX . 'map-height', 400 );
			if( !get_option( self::PREFIX . 'map-address' ) )
				add_option( self::PREFIX . 'map-address', 'Seattle' );
			if( !get_option( self::PREFIX . 'map-latitude' ) )
				add_option( self::PREFIX . 'map-latitude', 47.6062095 );
			if( !get_option( self::PREFIX . 'map-longitude' ) )
				add_option( self::PREFIX . 'map-longitude', -122.3320708 );
			if( !get_option( self::PREFIX . 'map-zoom' ) )
				add_option( self::PREFIX . 'map-zoom', 7 );
			if( !get_option( self::PREFIX . 'map-info-window-width' ) )
				add_option( self::PREFIX . 'map-info-window-width', 300 );
			if( !get_option( self::PREFIX . 'map-info-window-height' ) )
				add_option( self::PREFIX . 'map-info-window-height', 250 );
				
			// Upgrade 1.0 placemark data
			$posts = get_posts( array( 'numberposts' => -1, 'post_type' => 'bgmp', 'post_status' => 'publish' ) );
			if( $posts )
			{
				foreach($posts as $p)
				{
					$address	= get_post_meta( $p->ID, self::PREFIX . 'address', true );
					$latitude	= get_post_meta( $p->ID, self::PREFIX . 'latitude', true );
					$longitude	= get_post_meta( $p->ID, self::PREFIX . 'longitude', true );
					
					if( empty($address) && !empty($latitude) && !empty($longitude) )
					{
						$address = $this->reverseGeocode($latitude, $longitude);
						if($address)
							update_post_meta( $p->ID, self::PREFIX . 'address', $address );
					}
				}
			}
		}
		
		/**
		 * 
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function outputHead()
		{
			// only run on page where map shortcode is called?
			
			require_once( dirname(__FILE__) . '/views/front-end-head.php' );
		}
				
		/**
		 * Adds our custom settings to the admin Settings pages
		 * We intentionally don't register the map-latitude and map-longitude settings because they're set by updateMapCoordinates()
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function addSettings()
		{
			add_settings_section(self::PREFIX . 'map-settings', 'Basic Google Maps Placemarks', array($this, 'settingsSectionCallback'), 'writing');
			
			add_settings_field(self::PREFIX . 'map-width', 'Map Width', array($this, 'mapWidthCallback'), 'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-height', 'Map Height', array($this, 'mapHeightCallback'), 'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-address', 'Map Center Address', array($this, 'mapAddressCallback'), 'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-latitude', 'Map Center Latitude', array($this, 'mapLatitudeCallback'), 'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-longitude', 'Map Center Longitude', array($this, 'mapLongitudeCallback'), 'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-zoom', 'Zoom', array($this, 'mapZoomCallback'), 'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-info-window-width', 'Info Window Width', array($this, 'mapInfoWindowWidthCallback'), 'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-info-window-height', 'Info Window Height', array($this, 'mapInfoWindowHeightCallback'), 'writing', self::PREFIX . 'map-settings');
			
			register_setting('writing', self::PREFIX . 'map-width');
			register_setting('writing', self::PREFIX . 'map-height');
			register_setting('writing', self::PREFIX . 'map-address');
			register_setting('writing', self::PREFIX . 'map-zoom');
			register_setting('writing', self::PREFIX . 'map-info-window-width');
			register_setting('writing', self::PREFIX . 'map-info-window-height');
			
			// need to add labels to the names so they can click on name?
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
					$coordinates = $this->geocode( $_POST[ self::PREFIX . 'map-address'] );
				
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
			echo '<input id="'. self::PREFIX .'map-width" name="'. self::PREFIX .'map-width" type="text" value="'. $this->settings['map-width'] .'" class="code" /> pixels';
		}
		
		/**
		 * Adds the map-height field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapHeightCallback()
		{
			echo '<input id="'. self::PREFIX .'map-height" name="'. self::PREFIX .'map-height" type="text" value="'. $this->settings['map-height'] .'" class="code" /> pixels';
		}
		
		/**
		 * Adds the address field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapAddressCallback()
		{
			echo '<input id="'. self::PREFIX .'map-address" name="'. self::PREFIX .'map-address" type="text" value="'. $this->settings['map-address'] .'" class="code" />';
		}
		
		/**
		 * Adds the latitude field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapLatitudeCallback()
		{
			echo '<input id="'. self::PREFIX .'map-latitude" name="'. self::PREFIX .'map-latitude" type="text" value="'. $this->settings['map-latitude'] .'" class="code" readonly="readonly" />';
		}
		
		/**
		 * Adds the longitude field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapLongitudeCallback()
		{
			echo '<input id="'. self::PREFIX .'map-longitude" name="'. self::PREFIX .'map-longitude" type="text" value="'. $this->settings['map-longitude'] .'" class="code" readonly="readonly" />';
		}
		
		/**
		 * Adds the zoom field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapZoomCallback()
		{
			echo '<input id="'. self::PREFIX .'map-zoom" name="'. self::PREFIX .'map-zoom" type="text" value="'. $this->settings['map-zoom'] .'" class="code" />';
		}
		
		/**
		 * Adds the info-window-width field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapInfoWindowWidthCallback()
		{
			echo '<input id="'. self::PREFIX .'map-info-window-width" name="'. self::PREFIX .'map-info-window-width" type="text" value="'. $this->settings['map-info-window-width'] .'" class="code" /> pixels';
		}
		
		/**
		 * Adds the info-window-height field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapInfoWindowHeightCallback()
		{
			echo '<input id="'. self::PREFIX .'map-info-window-height" name="'. self::PREFIX .'map-info-window-height" type="text" value="'. $this->settings['map-info-window-height'] .'" class="code" /> pixels';
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
		 * Registers the custom post type
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function createPostType()
		{
			if( !post_type_exists('bgmp') )
			{
				$labels = array
				(
					'name' => __( 'Placemarks' ),
					'singular_name' => __( 'Placemark' ),
					'add_new' => __( 'Add New' ),
					'add_new_item' => __( 'Add New Placemark' ),
					'edit' => __( 'Edit' ),
					'edit_item' => __( 'Edit Placemark' ),
					'new_item' => __( 'New Placemark' ),
					'view' => __( 'View Placemark' ),
					'view_item' => __( 'View Placemark' ),
					'search_items' => __( 'Search Placemarks' ),
					'not_found' => __( 'No Placemarks found' ),
					'not_found_in_trash' => __( 'No Placemarks found in Trash' ),
					'parent' => __( 'Parent Placemark' ),
				);
				
				register_post_type('bgmp', array
				(
					'labels' => $labels,
					'singular_label' => __('Placemarks'),
					'public' => true,
					'menu_position' => 20,
					'hierarchical' => false,
					'capability_type' => 'post',
					'rewrite' => array( 'slug' => 'placemarks', 'with_front' => false ),
					'query_var' => true,
					'supports' => array('title', 'editor', 'author', 'thumbnail')
				) );
			}
		}
		
		/**
		 * Registers extra fields for the custom post type
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function registerCustomFields()
		{
			add_meta_box( self::PREFIX . 'placemark-address', 'Placemark Address', array($this, 'markupCustomFields'), 'bgmp', 'normal', 'high' );
		}
		
		/**
		 * Outputs the markup for the custom fields
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function markupCustomFields()
		{
			global $post;
		
			$address = get_post_meta($post->ID, self::PREFIX . 'address', true);
			$latitude = get_post_meta($post->ID, self::PREFIX . 'latitude', true);
			$longitude = get_post_meta($post->ID, self::PREFIX . 'longitude', true);
			
			require_once( dirname(__FILE__) . '/views/add-edit.php' );
		}
		
		/**
		 * Saves values of the the custom post type's extra fields
		 * @param
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function saveCustomFields($postID)
		{
			global $post;
			
			if( $post && $post->post_type == 'bgmp' && current_user_can( 'edit_posts' ) )
			{
				if( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || $post->post_status == 'auto-draft' )
					return;
				
				update_post_meta( $post->ID, self::PREFIX . 'address', $_POST[ self::PREFIX . 'address'] );
				$coordinates = $this->geocode( $_POST[ self::PREFIX . 'address'] );
				
				if( $coordinates )
				{
					update_post_meta( $post->ID, self::PREFIX . 'latitude', $coordinates['latitude'] );
					update_post_meta( $post->ID, self::PREFIX . 'longitude', $coordinates['longitude'] );
				}
				else
				{
					// add error message for user
					
					update_post_meta( $post->ID, self::PREFIX . 'latitude', '' );
					update_post_meta( $post->ID, self::PREFIX . 'longitude', '' );
				}
			}
		}
		
		/**
		 * 
		 * google's api has daily limit. could cause problems, but probably won't ever reach it. based on IP address, right?
		 * @param
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		protected function geocode($address)
		{
			$geocodeResponse = wp_remote_get( 'http://maps.googleapis.com/maps/api/geocode/json?address='. str_replace( ' ', '+', $address ) .'&sensor=false' );
			$coordinates = json_decode( $geocodeResponse['body'] );
				
			if( is_wp_error($geocodeResponse) || empty($coordinates->results) )
				return false;
			else
				return array( 'latitude' => $coordinates->results[0]->geometry->location->lat, 'longitude' => $coordinates->results[0]->geometry->location->lng );
		}
		
		/**
		 * 
		 * google's api has daily limit. could cause problems, but probably won't ever reach it. based on IP address, right?
		 * @param
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		protected function reverseGeocode($latitude, $longitude)
		{
			$geocodeResponse = wp_remote_get( 'http://maps.googleapis.com/maps/api/geocode/json?latlng='. $latitude .','. $longitude .'&sensor=false' );
			$address = json_decode( $geocodeResponse['body'] );
			
			if( is_wp_error($geocodeResponse) || empty($address->results) )
				return false;
			else
				return $address->results[0]->formatted_address;
		}
			
		/**
		 * Defines the [bgmp-map] shortcode
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $attributes Array of parameters automatically passed in by Wordpress
		 * return string The output of the shortcode
		 */
		public function mapShortcode($attributes) 
		{
			$this->mapShortcodeCalled = true;
			
			$output = sprintf('
				<div id="%smap-canvas">
					<p>Loading map...</p>
					<p><img src="%s" alt="Loading" /></p>
				</div>',
				self::PREFIX,
				plugins_url( 'images/loading.gif', __FILE__ )
			);
			
			return $output;
		}		
		
		/**
		 * Defines the [bgmp-list] shortcode
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $attributes Array of parameters automatically passed in by Wordpress
		 * return string The output of the shortcode
		 */
		public function listShortcode($attributes) 
		{
			$posts = get_posts( array( 'numberposts' => -1, 'post_type' => 'bgmp', 'post_status' => 'publish' ) );
			
			if( $posts )
			{
				$output = '<ul id="'. self::PREFIX .'list">';
				
				foreach( $posts as $p )
				{
					$address = get_post_meta($p->ID, self::PREFIX . 'address', true);
						
					$output .= sprintf('
						<li>
							<h3>%s</h3>
							<div>%s</div>
							<p><a href="%s">%s</a></p>
						</li>',
						$p->post_title,
						nl2br($p->post_content),
						'http://google.com/maps?q='. $address,
						$address
					);
					
					// make lat/long into address or city/zip
				}
				
				$output .= '</ul>';
				
				return $output;
			}
			else
				return "There aren't currently any placemarks in the system";
		}
		
		/**
		 * Load CSS on front and back end
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function loadStyle()
		{
			// setup to only call this on pages where teh shortcode was called? can't use same method as js b/c <style> has to be inside <head>
			
			wp_register_style(
				self::PREFIX .'style',
				plugins_url( 'style.css', __FILE__ ),
				false,
				self::BGMP_VERSION,
				false
			);
			wp_enqueue_style( self::PREFIX . 'style' );
		}
		
		/**
		 * Loads Javascript files on map shortcode pages
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function loadScripts()
		{
			if( $this->mapShortcodeCalled )
			{
				echo "<!-- BEGIN Basic Google Maps Placemarks scripts -->\n";
				
				wp_register_script(
					'googleMapsAPI',
					'http'. ( is_ssl() ? 's' : '' ) .'://maps.google.com/maps/api/js?sensor=false',
					false,
					false,
					true
				);
				wp_print_scripts('googleMapsAPI');
				
				echo sprintf('
					<form>
						<p>
							<input id="%s" type="hidden" value="%s" />
							<input id="%s" type="hidden" value="%s" />
						</p>
					</form>',
					self::PREFIX .'postURL',
					admin_url('admin-ajax.php'),
					self::PREFIX . 'nonce',
					wp_create_nonce( self::PREFIX . 'nonce')							
				);
				
				wp_register_script(
					'bgmp',
					plugins_url( 'functions.js', __FILE__ ),
					array('googleMapsAPI', 'jquery'),
					self::BGMP_VERSION,
					true
				);
				wp_print_scripts('bgmp');
				
				echo "<!-- END Basic Google Maps Placemarks scripts -->\n";
			}
		}
		
		/**
		 * Outputs GET headers for JSON requests
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		protected function getHeaders()
		{
			header('Cache-Control: no-cache, must-revalidate');
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
			header('Content-Type: application/json; charset=utf8');
			header('Content-Type: application/json');
			header($_SERVER["SERVER_PROTOCOL"]." 200 OK");
		}
		
		/**
		 * 
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function getMapOptions()
		{
			check_ajax_referer( self::PREFIX . 'nonce', 'nonce' );
	
			$options = array(
				'width'				=> $this->settings['map-width'],
				'height'			=> $this->settings['map-height'],
				'latitude'			=> $this->settings['map-latitude'],
				'longitude'			=> $this->settings['map-longitude'],
				'zoom'				=> $this->settings['map-zoom'],
				'infoWindowWidth'	=> $this->settings['map-info-window-width'],
				'infoWindowHeight'	=> $this->settings['map-info-window-height']
			);
		
			$this->getHeaders();
			die( json_encode($options) );
		}
		
		/**
		 * 
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function getPlacemarks()
		{			
			// note that json_encode requires php5
			check_ajax_referer( self::PREFIX . 'nonce', 'nonce' );
			
			$placemarks = array();
			$posts = get_posts( array( 'numberposts' => -1, 'post_type' => 'bgmp', 'post_status' => 'publish' ) );
			
			if( $posts )
			{
				foreach($posts as $p)
				{
					$icon = wp_get_attachment_image_src( get_post_thumbnail_id($p->ID) );
 
					$placemarks[] = array(
						'title'		=> $p->post_title,
						'latitude'	=> get_post_meta( $p->ID, self::PREFIX . 'latitude', true ),
						'longitude'	=> get_post_meta( $p->ID, self::PREFIX . 'longitude', true ),
						'details'	=> nl2br($p->post_content),
						'icon'		=> is_array($icon) ? $icon[0] : plugins_url( 'images/default-marker.png', __FILE__ )
					);
				}
			}
			
			$this->getHeaders();
			die( json_encode($placemarks) );
		}
		
		/**
		 * Displays updates and errors
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function printMessages()
		{
			foreach( array('updates', 'errors') as $type )
			{
				if( $this->options[$type] && ( self::DEBUG_MODE || $this->userMessageCount[$type] ) )
				{
					echo '<div id="message" class="'. ( $type == 'updates' ? 'updated' : 'error' ) .'">';
					foreach($this->options[$type] as $message)
						if( $message['mode'] == 'user' || self::DEBUG_MODE )
							echo '<p>'. $message['message'] .'</p>';
					echo '</div>';
					
					$this->options[$type] = array();
					$this->updatedOptions = true;
					$this->userMessageCount[$type] = 0;
				}
			}
		}
		
		/**
		 * Queues up a message to be displayed to the user
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param string $message The text to show the user
		 * @param string $type 'update' for a success or notification message, or 'error' for an error message
		 * @param string $mode 'user' if it's intended for the user, or 'debug' if it's intended for the developer
		 */
		protected function enqueueMessage($message, $type = 'update', $mode = 'user')
		{
			array_push($this->options[$type .'s'], array(
				'message' => $message,
				'type' => $type,
				'mode' => $mode
			) );
			
			if($mode == 'user')
				$this->userMessageCount[$type . 's']++;
			
			$this->updatedOptions = true;
		}
		
		/**
		 * Destructor
		 * Writes options to the database
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function __destruct()
		{
			if($this->updatedOptions)
				update_option(self::PREFIX . 'options', $this->options);
		}
	} // end BasicGoogleMapsPlacemarks
}

/**
 * Prints an error that the required PHP version wasn't met.
 * This has to be defined outside the class because the class can't be called if the required PHP version isn't installed.
 * Writes options to the database
 * @author Ian Dunn <ian@iandunn.name>
 */
function BGMP_phpOld()
{
	echo '<div id="message" class="error"><p>'. BGMP_NAME .' requires <strong>PHP '. BGMP_REQUIRED_PHP_VERSON .'</strong> in order to work. Please upgrade.</p></div>';
}

// Create an instance
if( version_compare(PHP_VERSION, BGMP_REQUIRED_PHP_VERSON, '>=') )
{
	if( class_exists('BasicGoogleMapsPlacemarks') )
		$bgmp = new BasicGoogleMapsPlacemarks();
}
else
	add_action('admin_notices', 'BGMP_phpOld');

?>
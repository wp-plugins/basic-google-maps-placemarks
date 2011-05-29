<?php
/*
Plugin Name: Basic Google Maps Placemarks
Description: Adds a custom post type for placemarks and builds an embedded Google Map with them
Version: 1.0
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
		protected $settings, $options, $updatedOptions, $userMessageCount, $environmentOK;
		const REQUIRED_WP_VERSION	= '2.9';
		const PREFIX				= 'bgmp_';
		const DEBUG_MODE			= false;
		
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
			$this->settings['map-width']				= get_option( self::PREFIX . 'map-width', 600 );
			$this->settings['map-height']				= get_option( self::PREFIX . 'map-height', 400 );
			$this->settings['map-latitude']				= get_option( self::PREFIX . 'map-latitude', 47.600521 );
			$this->settings['map-longitude']			= get_option( self::PREFIX . 'map-longitude', -122.333252 );
			$this->settings['map-zoom']					= get_option( self::PREFIX . 'map-zoom', 7 );
			$this->settings['map-info-window-width']	= get_option( self::PREFIX . 'map-info-window-width', 300 );
			$this->settings['map-info-window-height']	= get_option( self::PREFIX . 'map-info-window-height', 250 );
						
			// Register remaining actions, filters and shortcodes
			add_action( 'admin_init', 										array($this, 'addSettings') );
			add_action( 'init',												array($this, 'createPostType') );
			add_action( 'admin_init',										array($this, 'registerCustomFields') );
			add_action( 'save_post',										array($this, 'saveCustomFields') );		// should be save_post instead of post_updated ?
			add_action( 'init', 											array($this, 'loadJavaScript'));
			add_action( 'wp_ajax_bgmp_get_map_options',						array($this, 'getMapOptions' ) );
			add_action( 'wp_ajax_nopriv_bgmp_get_map_options',				array($this, 'getMapOptions' ) );
			add_action( 'wp_ajax_bgmp_get_placemarks',						array($this, 'getPlacemarks' ) );
			add_action( 'wp_ajax_nopriv_bgmp_get_placemarks',				array($this, 'getPlacemarks' ) );
			add_action( 'wp_head',											array($this, 'outputHead' ) );
			add_filter( 'plugin_action_links_'. plugin_basename(__FILE__),	array($this, 'addSettingsLink') );
			add_shortcode( 'bgmp-map',										array($this, 'mapShortcode') );
			add_shortcode( 'bgmp-list',										array($this, 'listShortcode') );
			
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
		 * 
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function outputHead()
		{
			// only run on page where map shortcode is called?
			
			if( !is_admin() )
			{
				require_once( dirname(__FILE__) . '/front-end-head.php' );
			}
		}
		
		/**
		 * Adds our custom settings to the admin Settings pages
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function addSettings()
		{
			// give this its own page eventually, just don't want to deal w/ it right now
			
			add_settings_section(self::PREFIX . 'map-settings', 'Basic Google Maps Placemarks', array($this, 'settingsSectionCallback'), 'writing');
			
			add_settings_field(self::PREFIX . 'map-width', 'Map Width', array($this, 'mapWidthCallback'), 'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-height', 'Map Height', array($this, 'mapHeightCallback'), 'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-latitude', 'Map Center Latitude', array($this, 'mapLatitudeCallback'), 'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-longitude', 'Map Center Longitude', array($this, 'mapLongitudeCallback'), 'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-zoom', 'Zoom', array($this, 'mapZoomCallback'), 'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-info-window-width', 'Info Window Width', array($this, 'mapInfoWindowWidthCallback'), 'writing', self::PREFIX . 'map-settings');
			add_settings_field(self::PREFIX . 'map-info-window-height', 'Info Window Height', array($this, 'mapInfoWindowHeightCallback'), 'writing', self::PREFIX . 'map-settings');
			
			register_setting('writing', self::PREFIX . 'map-width');
			register_setting('writing', self::PREFIX . 'map-height');
			register_setting('writing', self::PREFIX . 'map-latitude');
			register_setting('writing', self::PREFIX . 'map-longitude');
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
			echo '<p>Enter the latitude, longitude and zoom level for the map below. The coordinates will determine where the map is centered. You can use <a href="http://www.gpsvisualizer.com/geocode">GPS Visualizer\'s Quick Geocoder</a> to translate an address into coordinates.</p>';
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
		 * Adds the latitude field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapLatitudeCallback()
		{
			echo '<input id="'. self::PREFIX .'map-latitude" name="'. self::PREFIX .'map-latitude" type="text" value="'. $this->settings['map-latitude'] .'" class="code" />';
		}
		
		/**
		 * Adds the longitude field to the Settings page
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function mapLongitudeCallback()
		{
			echo '<input id="'. self::PREFIX .'map-longitude" name="'. self::PREFIX .'map-longitude" type="text" value="'. $this->settings['map-longitude'] .'" class="code" />';
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
			add_meta_box( self::PREFIX . 'placemark-coordinates', 'Placemark Coordinates', array($this, 'markupCustomFields'), 'bgmp', 'normal', 'high' );
		}
		
		/**
		 * Outputs the markup for the custom fields
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function markupCustomFields()
		{
			global $post;
		
			$latitude = get_post_meta($post->ID, self::PREFIX . 'latitude', true);
			$longitude = get_post_meta($post->ID, self::PREFIX . 'longitude', true);
			
			require_once( dirname(__FILE__) . '/add-edit.php' );
		}
		
		/**
		 * Saves values of the the custom post type's extra fields
		 * @param
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function saveCustomFields($postID)
		{
			global $post;
			
			if($post->post_type == 'bgmp')
			{
				if( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || $post->post_status == 'auto-draft' ) 		/// try this to fix empty fields problem?. doesn't seem to fix. probably leave on after problem fixed anyway, though
					return;
					
				update_post_meta( $post->ID, self::PREFIX . 'latitude', $_POST[ self::PREFIX . 'latitude'] );
				update_post_meta( $post->ID, self::PREFIX . 'longitude', $_POST[ self::PREFIX . 'longitude'] );
			}
		}
		
		/**
		 * Defines the [bgmp-map] shortcode
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $attributes Array of parameters automatically passed in by Wordpress
		 * return string The output of the shortcode
		 */
		public function mapShortcode($attributes) 
		{
			$output = sprintf('
				<div id="bgmp-map-canvas">
					<p>Loading map...</p>
					<p><img src="%s" alt="Loading" /></p>
				</div>',
				plugins_url( 'loading.gif', __FILE__ )
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
			$posts = get_posts( array(
				'numberposts'	=> -1,
				'post_type'		=> 'bgmp',
				'post_status'	=> 'publish'
			) );	// order by zip code or something meaningful later on?
			
			// if this doesn't make it into the initial release, then make sure it gets documented in the readme/faq for 1.1
			
			if( $posts )
			{
				$output = '<ul id="'. self::PREFIX .'list">';
				
				foreach( $posts as $p )
				{
					$latitude = get_post_meta($p->ID, self::PREFIX . 'latitude', true);
					$longitude = get_post_meta($p->ID, self::PREFIX . 'longitude', true);
						
					$output .= sprintf('
						<li>
							<h3>%s</h3>
							<div>%s</div>
							<p>Coordinates: <a href="%s">%s,%s</a></p>
						</li>',
						$p->post_title,
						nl2br($p->post_content),
						'http://google.com/maps?q='. $latitude .','. $longitude,
						$latitude,
						$longitude
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
		 * Loads JavaScript files
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function loadJavaScript()
		{
			// setup to only call this on pages where teh shortcode was called?
			// maybe insead of shortcode only setup optoin to select a page, and auto insert into that page here? b/c can check is_page(). more flexible for user to have shortcode though
			
			if( !is_admin() )
			{
				wp_register_script(
					'googleMapsAPI',
					'http://maps.google.com/maps/api/js?sensor=false',
					false,
					false,
					true
				);
				wp_enqueue_script('googleMapsAPI');

				wp_register_script(
					'bgmp',
					WP_PLUGIN_URL . '/basic-google-maps-placemarks/functions.js',
					array('googleMapsAPI', 'jquery'),
					false,
					true
				);
				wp_enqueue_script('bgmp');
				
				wp_localize_script(
					'bgmp',
					'bgmp',
					array(
						'postURL' => admin_url('admin-ajax.php'),
						'previousInfoWindow' => '',
						'nonce' => wp_create_nonce( self::PREFIX . 'nonce')
					) 
				);
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
			//header('Content-Type: application/json; charset=utf8');		// probably put this back after fix ie error
			header('Content-Type: application/json');
			header($_SERVER["SERVER_PROTOCOL"]." 200 OK");
		}
		
		/**
		 * 
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function getMapOptions()
		{			
			// note that json_encode requires php5
			check_ajax_referer( self::PREFIX . 'nonce', 'nonce' );
	
			$options = array(
				'latitude'			=> $this->settings['map-latitude'],
				'longitude'			=> $this->settings['map-longitude'],
				'zoom'				=> $this->settings['map-zoom']
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
			$posts = get_posts( array( 'numberposts' => -1, 'post_type' => 'bgmp' ) );
			
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
						'icon'		=> $icon[0]
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
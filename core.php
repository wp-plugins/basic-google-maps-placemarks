<?php

if( $_SERVER['SCRIPT_FILENAME'] == __FILE__ )
	die("Access denied.");

if( !class_exists('BasicGoogleMapsPlacemarks') )
{
	/**
	 * A Wordpress plugin that adds a custom post type for placemarks and builds a Google Map with them
	 * Requires PHP5+ because of various OOP features, json_encode(), pass by reference, etc
	 * Requires Wordpress 3.0 because of custom post type support
	 *
	 * @package BasicGoogleMapsPlacemarks
	 * @author Ian Dunn <ian@iandunn.name>
	 * @link http://wordpress.org/extend/plugins/basic-google-maps-placemarks/
	 */
	class BasicGoogleMapsPlacemarks
	{
		// Declare variables and constants
		protected $settings, $options, $updatedOptions, $userMessageCount, $environmentOK, $mapShortcodeCalled;
		const BGMP_VERSION			= '1.2.1';
		const PREFIX				= 'bgmp_';
		const POST_TYPE				= 'bgmp';
		const DEBUG_MODE			= false;
		
		/**
		 * Constructor
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function __construct()
		{
			require_once( dirname(__FILE__) . '/settings.php');
			
			// Initialize variables
			$defaultOptions				= array( 'updates' => array(), 'errors' => array() );
			$this->options				= array_merge( $defaultOptions, get_option( self::PREFIX . 'options', array() ) );
			$this->userMessageCount		= array( 'updates' => count( $this->options['updates'] ), 'errors' => count( $this->options['errors'] )	);
			$this->updatedOptions		= false;
			$this->mapShortcodeCalled	= false;
			$this->settings				= new BGMPSettings( $this );
			
			// Register actions, filters and shortcodes
			add_action( 'admin_notices',						array( $this, 'printMessages') );
			add_action( 'init',									array( $this, 'createPostType') );
			add_action( 'init', 								array( $this, 'addFeaturedImageSupport' ) );
			add_action( 'admin_init',							array( $this, 'registerCustomFields') );
			add_action( 'save_post',							array( $this, 'saveCustomFields') );
			add_action( 'wp_head',								array( $this, 'outputHead' ) );
			add_action( 'wp_footer',							array( $this, 'outputFooter' ) );
			add_action( 'wp_ajax_bgmp_get_map_options',			array( $this, 'getMapOptions' ) );
			add_action( 'wp_ajax_nopriv_bgmp_get_map_options',	array( $this, 'getMapOptions' ) );
			add_action( 'wp_ajax_bgmp_get_placemarks',			array( $this, 'getPlacemarks' ) );
			add_action( 'wp_ajax_nopriv_bgmp_get_placemarks',	array( $this, 'getPlacemarks' ) );
			add_action( 'wpmu_new_blog', 						array( $this, 'activateNewSite' ) );
			add_action( 'shutdown',								array( $this, 'shutdown' ) );
			add_filter( 'the_posts', 							array( $this, 'loadResources'), 11 );
			add_shortcode( 'bgmp-map',							array( $this, 'mapShortcode') );
			add_shortcode( 'bgmp-list',							array( $this, 'listShortcode') );
			
			register_activation_hook( dirname(__FILE__) . '/basic-google-maps-placemarks.php', array( $this, 'networkActivate') );
		}
		
		/**
		 * Handles extra activation tasks for MultiSite installations
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function networkActivate()
		{
			global $wpdb;
			
			if( function_exists('is_multisite') && is_multisite() )
			{
				// Enable image uploads so the 'Set Featured Image' meta box will be available
				$mediaButtons = get_site_option( 'mu_media_buttons' );
				
				if( !array_key_exists( 'image', $mediaButtons ) || !$mediaButtons['image'] )
				{
					$mediaButtons['image'] = 1;
					update_site_option( 'mu_media_buttons', $mediaButtons );
				}
				
				// Activate the plugin across the network if requested
				if( array_key_exists( 'networkwide', $_GET ) && ( $_GET['networkwide'] == 1) )
				{
					$blogs = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
					
					foreach( $blogs as $b ) 
					{
						switch_to_blog( $b );
						$this->singleActivate();
					}
					
					restore_current_blog();
				}
				else
					$this->singleActivate();
			}
			else
				$this->singleActivate();
		}
		
		/**
		 * Prepares a single blog to use the plugin
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		protected function singleActivate()
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
				add_option( self::PREFIX . 'map-info-window-width', 500 );
				
			// Upgrade 1.0 placemark data
			$posts = get_posts( array( 'numberposts' => -1, 'post_type' => self::POST_TYPE ) );
			if( $posts )
			{
				foreach( $posts as $p )
				{
					$address	= get_post_meta( $p->ID, self::PREFIX . 'address', true );
					$latitude	= get_post_meta( $p->ID, self::PREFIX . 'latitude', true );
					$longitude	= get_post_meta( $p->ID, self::PREFIX . 'longitude', true );
					
					if( empty($address) && !empty($latitude) && !empty($longitude) )
					{
						$address = $this->reverseGeocode( $latitude, $longitude );
						if( $address )
							update_post_meta( $p->ID, self::PREFIX . 'address', $address );
					}
				}
			}
		}
		
		/**
		 * Runs activation code on a new WPMS site when it's created
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param int $blogID
		 */
		public function activateNewSite( $blogID )
		{
			switch_to_blog( $blogID );
			$this->singleActivate();
			restore_current_blog();
		}
		
		/**
		 * Adds featured image support
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function addFeaturedImageSupport()
		{
			if( is_admin() )
			{
				// We enabled image media buttons for MultiSite on activation, but the admin may have turned it back off
				if( function_exists('is_multisite') && is_multisite() )
				{
					$mediaButtons = get_site_option( 'mu_media_buttons' );
					
					if( !array_key_exists( 'image', $mediaButtons ) || !$mediaButtons['image'] )
					{
						$this->enqueueMessage( sprintf(
							"%s requires the Images media button setting to be enabled in order to use custom icons on markers, but it's currently turned off. If you'd like to use custom icons you can enable it on the <a href=\"%ssettings.php\">Network Settings</a> page, in the Upload Settings section.",
							BGMP_NAME,
							network_admin_url()
						), 'error' );
					}
				}
				
				add_theme_support( 'post-thumbnails' );
			}
		}
		
		/**
		 * Checks the current post(s) to see if they contain the map shortcode
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $posts
		 * @return bool
		 */
		function mapShortcodeCalled( $posts )
		{
			$this->mapShortcodeCalled = apply_filters( self::PREFIX .'mapShortcodeCalled', $this->mapShortcodeCalled );
			if( $this->mapShortcodeCalled )
				return true;
				
			foreach( $posts as $p )
			{
				preg_match( '/'. get_shortcode_regex() .'/s', $p->post_content, $matches );
				if( is_array($matches) && array_key_exists(2, $matches) && $matches[2] == 'bgmp-map' )
					return true;
			}
			
			return false;
		}
		
		/**
		 * Load CSS and JavaScript files
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function loadResources( $posts )
		{
			// @todo - maybe find an action that gets run at the same time. would be better to hook there than to a filter. update faq for do_shortcode if do
			
			wp_register_script(
				'googleMapsAPI',
				'http'. ( is_ssl() ? 's' : '' ) .'://maps.google.com/maps/api/js?sensor=false',
				false,
				false,
				true
			);
			
			wp_register_script(
				'bgmp',
				plugins_url( 'functions.js', __FILE__ ),
				array( 'googleMapsAPI', 'jquery' ),
				self::BGMP_VERSION,
				true
			);
			
			wp_register_style(
				self::PREFIX .'style',
				plugins_url( 'style.css', __FILE__ ),
				false,
				self::BGMP_VERSION,
				false
			);
			
			if( $posts )
			{
				$this->mapShortcodeCalled = $this->mapShortcodeCalled( $posts );
				
				if( !is_admin() && $this->mapShortcodeCalled )
				{
					wp_enqueue_script('googleMapsAPI');
					wp_enqueue_script('bgmp');
				}
				
				if( is_admin() || $this->mapShortcodeCalled )
					wp_enqueue_style( self::PREFIX . 'style' );
			}
			
			return $posts;
		}
		
		/**
		 * Outputs elements in the <head> section of the front-end
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function outputHead()
		{
			if( $this->mapShortcodeCalled )
				require_once( dirname(__FILE__) . '/views/front-end-head.php' );
		}
		
		/**
		 * Outputs some initial values for the JavaScript file to use
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function outputFooter()
		{
			if( $this->mapShortcodeCalled )
				require_once( dirname(__FILE__) . '/views/front-end-footer.php' );
		}
		
		/**
		 * Registers the custom post type
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function createPostType()
		{
			if( !post_type_exists( self::POST_TYPE ) )
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
				
				register_post_type(
					self::POST_TYPE,
					array
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
					)
				);
			}
		}
		
		/**
		 * Registers extra fields for the custom post type
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function registerCustomFields()
		{
			add_meta_box( self::PREFIX . 'placemark-address', 'Placemark Address', array($this, 'markupCustomFields'), self::POST_TYPE, 'normal', 'high' );
		}
		
		/**
		 * Outputs the markup for the custom fields
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function markupCustomFields()
		{
			global $post;
		
			$address	= get_post_meta( $post->ID, self::PREFIX . 'address', true );
			$latitude	= get_post_meta( $post->ID, self::PREFIX . 'latitude', true );
			$longitude	= get_post_meta( $post->ID, self::PREFIX . 'longitude', true );
			
			require_once( dirname(__FILE__) . '/views/add-edit.php' );
		}
		
		/**
		 * Saves values of the the custom post type's extra fields
		 * @param
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function saveCustomFields( $postID )
		{
			global $post;
			
			if(	$post && $post->post_type == self::POST_TYPE && current_user_can( 'edit_posts' ) && $_GET['action'] != 'trash' && $_GET['action'] != 'untrash' )
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
					$this->enqueueMessage('That address couldn\'t be geocoded, please make sure that it\'s correct.', 'error' );
					
					update_post_meta( $post->ID, self::PREFIX . 'latitude', '' );
					update_post_meta( $post->ID, self::PREFIX . 'longitude', '' );
				}
			}
		}
		
		/**
		 * Geocodes an address
		 * Google's API has a daily request limit, but this is only called when a post is published, so shouldn't ever be a problem.
		 * @param
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function geocode( $address )
		{
			$geocodeResponse = wp_remote_get( 'http://maps.googleapis.com/maps/api/geocode/json?address='. str_replace( ' ', '+', $address ) .'&sensor=false' );
			$coordinates = json_decode( $geocodeResponse['body'] );
				
			if( is_wp_error($geocodeResponse) || empty($coordinates->results) )
				return false;
			else
				return array( 'latitude' => $coordinates->results[0]->geometry->location->lat, 'longitude' => $coordinates->results[0]->geometry->location->lng );
		}
		
		/**
		 * Reverse-geocodes a set of coordinates
		 * Google's API has a daily request limit, but this is only called when a post is published, so shouldn't ever be a problem.
		 * @param string $latitude
		 * @param string $longitude
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		protected function reverseGeocode( $latitude, $longitude )
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
		public function mapShortcode( $attributes ) 
		{
			if( !wp_script_is( 'googleMapsAPI', 'queue' ) || !wp_script_is( 'bgmp', 'queue' ) || !wp_style_is( self::PREFIX .'style', 'queue' ) )
				return '<p class="error">'. BGMP_NAME .' error: JavaScript and/or CSS files aren\'t loaded. If you\'re using do_shortcode() you need to add a filter to your theme first. See <a href="http://wordpress.org/extend/plugins/basic-google-maps-placemarks/faq/">the FAQ</a> for details.</p>';
			
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
		public function listShortcode( $attributes ) 
		{
			$posts = get_posts( array( 'numberposts' => -1, 'post_type' => self::POST_TYPE, 'post_status' => 'publish' ) );
			
			if( $posts )
			{
				$output = '<ul id="'. self::PREFIX .'list">';
				
				foreach( $posts as $p )
				{
					$address = get_post_meta( $p->ID, self::PREFIX . 'address', true );
						
					$output .= sprintf('
						<li>
							<h3>%s</h3>
							<div>%s</div>
							<p><a href="%s">%s</a></p>
						</li>',
						$p->post_title,
						nl2br( $p->post_content ),
						'http://google.com/maps?q='. $address,
						$address
					);
				}
				
				$output .= '</ul>';
				
				return $output;
			}
			else
				return "There aren't currently any placemarks in the system";
		}
		
		/**
		 * 
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function getMapOptions()
		{
			check_ajax_referer( self::PREFIX . 'nonce', 'nonce' );
	
			$options = array(
				'mapWidth'				=> $this->settings->mapWidth,
				'mapHeight'				=> $this->settings->mapHeight,
				'latitude'				=> $this->settings->mapLatitude,
				'longitude'				=> $this->settings->mapLongitude,
				'zoom'					=> $this->settings->mapZoom,
				'infoWindowMaxWidth'	=> $this->settings->mapInfoWindowMaxWidth
			);
		
			$this->getHeaders();
			die( json_encode($options) );
		}
		
		/**
		 * Gets the published placemarks from the database, formats and outputs them.
		 * Called via AJAX. json_encode() requires PHP 5.
		 * @author Ian Dunn <ian@iandunn.name>
		 * @return string JSON formatted string of the placemarks
		 */
		public function getPlacemarks()
		{
			check_ajax_referer( self::PREFIX . 'nonce', 'nonce' );
			
			$placemarks = array();
			$posts = get_posts( array( 'numberposts' => -1, 'post_type' => self::POST_TYPE, 'post_status' => 'publish' ) );
			
			if( $posts )
			{
				foreach( $posts as $p )
				{
					$icon = wp_get_attachment_image_src( get_post_thumbnail_id($p->ID) );
 
					$placemarks[] = array(
						'title'		=> $p->post_title,
						'latitude'	=> get_post_meta( $p->ID, self::PREFIX . 'latitude', true ),
						'longitude'	=> get_post_meta( $p->ID, self::PREFIX . 'longitude', true ),
						'details'	=> nl2br( $p->post_content ),
						'icon'		=> is_array($icon) ? $icon[0] : plugins_url( 'images/default-marker.png', __FILE__ )
					);
				}
			}
			
			$this->getHeaders();
			die( json_encode($placemarks) );
		}
		
		/**
		 * Outputs GET headers for JSON requests
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		protected function getHeaders()
		{
			header( 'Cache-Control: no-cache, must-revalidate' );
			header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' );
			header( 'Content-Type: application/json; charset=utf8' );
			header( 'Content-Type: application/json' );
			header( $_SERVER["SERVER_PROTOCOL"]." 200 OK" );
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
					foreach( $this->options[$type] as $message )
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
		public function enqueueMessage( $message, $type = 'update', $mode = 'user' )
		{
			array_push( $this->options[$type .'s'], array(
				'message' => $message,
				'type' => $type,
				'mode' => $mode
			) );
			
			if( $mode == 'user' )
				$this->userMessageCount[$type . 's']++;
			
			$this->updatedOptions = true;
		}
		
		/**
		 * Stops execution and prints the input. Used for debugging.
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param mixed $data
		 * @param string $output 'message' will be sent to an admin notice; 'die' will be output inside wp_die(); 'return' will be returned;
		 * @param string $message Optionally message to output before description
		 */
		protected function describe( $data, $output = 'return', $message = '' )
		{
			$type = gettype( $data );

			switch( $type)
			{
				case 'array':
				case 'object':
					$length = count( $data );
					$data = print_r( $data, true );
					break;
				
				case 'string';
					$length = strlen( $data );
					break;
				
				default:
					$length = count( $data );
					
					ob_start();
					var_dump( $data );
					$data = ob_get_contents();
					ob_end_clean();
					
					$data = print_r( $data, true );
					
					break;
			}
			
			$description = sprintf('
				<p>
					%s
					Type: %s<br />
					Length: %s<br />
					Content: <br /><blockquote><pre>%s</pre></blockquote>
				</p>',
				( $message ? 'Message: '. $message .'<br />' : '' ),
				$type,
				$length,
				$data
			);
			
			if( $output == 'message' )
				$this->enqueueMessage( $description, 'error' );
			elseif( $output == 'die' )
				wp_die( $description );
			else
				return $description;
		}
		
		/**
		 * Writes options to the database
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function shutdown()
		{
			if( is_admin() )
				if( $this->updatedOptions )
					update_option( self::PREFIX . 'options', $this->options );
		}
	} // end BasicGoogleMapsPlacemarks
}

?>
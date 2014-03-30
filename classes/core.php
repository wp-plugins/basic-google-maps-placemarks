<?php

if ( ! class_exists( 'BasicGoogleMapsPlacemarks' ) ) {
	class BasicGoogleMapsPlacemarks {
		protected $settings, $options, $updatedOptions, $mapShortcodeCalled, $mapShortcodeCategories;
		const VERSION    = '1.10.2';
		const POST_TYPE  = 'bgmp';
		const TAXONOMY   = 'bgmp-category';
		const ZOOM_MIN   = 0;
		const ZOOM_MAX   = 21;
		const DEBUG_MODE = false;

		/**
		 * Constructor
		 */
		public function __construct() {
			add_action( 'init',                     array( $this, 'init' ), 8 );                        // lower priority so that variables defined here will be available to BGMPSettings class and other init callbacks
			add_action( 'init',                     array( $this, 'upgrade' ) );
			add_action( 'init',                     array( $this, 'createPostType' ) );
			add_action( 'init',                     array( $this, 'createCategoryTaxonomy' ) );
			add_action( 'after_setup_theme',        array( $this, 'addFeaturedImageSupport' ), 11 );    // @todo add note explaining why higher priority
			add_action( 'admin_init',               array( $this, 'addMetaBoxes' ) );
			add_action( 'wp',                       array( $this, 'loadResources' ), 11 );              // @todo - should be wp_enqueue_scripts instead?	// @todo add note explaining why higher priority
			add_action( 'admin_enqueue_scripts',    array( $this, 'loadResources' ), 11 );
			add_action( 'wp_head',                  array( $this, 'outputHead' ) );
			add_action( 'save_post',                array( $this, 'saveCustomFields' ) );
			add_action( 'wpmu_new_blog',            array( $this, 'activateNewSite' ) );
			add_action( 'shutdown',                 array( $this, 'shutdown' ) );

			add_filter( 'parse_query',              array( $this, 'sortAdminView' ) );

			add_shortcode( 'bgmp-map',              array( $this, 'mapShortcode' ) );
			add_shortcode( 'bgmp-list',             array( $this, 'listShortcode' ) );

			register_activation_hook( dirname( dirname( __FILE__ ) ) . '/basic-google-maps-placemarks.php', array( $this, 'networkActivate' ) );

			require_once( dirname( __FILE__ ) . '/settings.php' );
			$this->settings = new BGMPSettings();
		}

		/**
		 * Performs various initialization functions
		 */
		public function init() {
			$defaultOptions = array( 'updates' => array(), 'errors' => array(), 'dbVersion' => '0' );
			$this->options  = array_merge( $defaultOptions, get_option( 'bgmp_options', array() ) );

			if ( ! is_array( $this->options ) ) {
				$this->options = $defaultOptions;
			}

			if ( ! is_array( $this->options['updates'] ) ) {
				$this->options['updates'] = array();
			}

			if ( ! is_array( $this->options['errors'] ) ) {
				$this->options['errors'] = array();
			}

			$this->userMessageCount       = array( 'updates' => count( $this->options['updates'] ), 'errors' => count( $this->options['errors'] ) );
			$this->updatedOptions         = false;
			$this->mapShortcodeCalled     = false;
			$this->mapShortcodeCategories = null;
		}

		/**
		 * Getter method for instance of the BGMPSettings class, used for unit testing
		 */
		public function &getSettings() {
			return $this->settings;
		}

		/**
		 * Handles extra activation tasks for MultiSite installations
		 *
		 * @param bool $networkWide True if the activation was network-wide
		 */
		public function networkActivate( $networkWide ) {
			global $wpdb, $wp_version;

			if ( function_exists( 'is_multisite' ) && is_multisite() ) {
				// Enable image uploads so the 'Set Featured Image' meta box will be available
				$mediaButtons = get_site_option( 'mu_media_buttons' );

				if ( version_compare( $wp_version, '3.3', "<=" ) && ( ! array_key_exists( 'image', $mediaButtons ) || ! $mediaButtons['image'] ) ) {
					$mediaButtons['image'] = 1;
					update_site_option( 'mu_media_buttons', $mediaButtons );

					/*
					@todo enqueueMessage() needs $this->options to be set, but as of v1.8 that doesn't happen until the init hook, which is after activation. It doesn't really matter anymore, though, because mu_media_buttons was removed in 3.3. http://core.trac.wordpress.org/ticket/17578 
					add_notice( sprintf(
						__( '%s has enabled uploading images network-wide so that placemark icons can be set.', 'bgmp' ),		// @todo - give more specific message, test. enqueue for network admin but not regular admins
						BGMP_NAME
					) );
					*/
				}
			}
		}

		/**
		 * Checks if the plugin was recently updated and upgrades if necessary
		 */
		public function upgrade() {
			if ( version_compare( $this->options['dbVersion'], self::VERSION, '==' ) ) {
				return;
			}

			if ( version_compare( $this->options['dbVersion'], '1.1', '<' ) ) {
				// Populate new Address field from existing coordinate fields
				$posts = get_posts( array( 'numberposts' => - 1, 'post_type' => self::POST_TYPE ) );
				if ( $posts ) {
					foreach ( $posts as $p ) {
						$address   = get_post_meta( $p->ID, 'bgmp_address', true );
						$latitude  = get_post_meta( $p->ID, 'bgmp_latitude', true );
						$longitude = get_post_meta( $p->ID, 'bgmp_longitude', true );

						if ( empty( $address ) && ! empty( $latitude ) && ! empty( $longitude ) ) {
							$address = $this->reverseGeocode( $latitude, $longitude );
							if ( $address )
								update_post_meta( $p->ID, 'bgmp_address', $address );
						}
					}
				}
			}

			$this->options['dbVersion'] = self::VERSION;
			$this->updatedOptions       = true;

			// Clear WP Super Cache and W3 Total Cache
			if ( function_exists( 'wp_cache_clear_cache' ) )
				wp_cache_clear_cache();

			if ( class_exists( 'W3_Plugin_TotalCacheAdmin' ) ) {
				$w3TotalCache =& w3_instance( 'W3_Plugin_TotalCacheAdmin' );

				if ( method_exists( $w3TotalCache, 'flush_all' ) )
					$w3TotalCache->flush_all();
			}
		}

		/**
		 * Adds featured image support
		 */
		public function addFeaturedImageSupport() {
			global $wp_version;

			// We enabled image media buttons for MultiSite on activation, but the admin may have turned it back off
			if ( version_compare( $wp_version, '3.3', "<=" ) && is_admin() && function_exists( 'is_multisite' ) && is_multisite() ) {
				// @todo this isn't DRY, similar code in networkActivate()

				$mediaButtons = get_site_option( 'mu_media_buttons' );

				if ( ! array_key_exists( 'image', $mediaButtons ) || ! $mediaButtons['image'] ) {
					add_notice( sprintf(
						__( "%s requires the Images media button setting to be enabled in order to use custom icons on markers, but it's currently turned off. If you'd like to use custom icons you can enable it on the <a href=\"%s\">Network Settings</a> page, in the Upload Settings section.", 'bgmp' ),
						BGMP_NAME,
						network_admin_url() . 'settings.php'
					), 'error' );
				}
			}

			$supportedTypes = get_theme_support( 'post-thumbnails' );

			if ( $supportedTypes === false )
				add_theme_support( 'post-thumbnails', array( self::POST_TYPE ) );
			elseif ( is_array( $supportedTypes ) ) {
				$supportedTypes[0][] = self::POST_TYPE;
				add_theme_support( 'post-thumbnails', $supportedTypes[0] );
			}
		}

		/**
		 * Gets all of the shortcodes in the current post
		 *
		 * @param string $content
		 * @return mixed false | array
		 */
		protected function getShortcodes( $content ) {
			$matches = array();

			preg_match_all( '/' . get_shortcode_regex() . '/s', $content, $matches );
			if ( ! is_array( $matches ) || ! array_key_exists( 2, $matches ) )
				return false;

			return $matches;
		}

		/**
		 * Validates and cleans the map shortcode arguments
		 *
		 * @param array
		 * @return array
		 */
		protected function cleanMapShortcodeArguments( $arguments ) {
			// @todo - not doing this in settings yet, but should. want to make sure it's DRY when you do. 
			// @todo - Any errors generated in there would stack up until admin loads page, then they'll all be displayed, include  ones from geocode() etc. that's not great solution, but is there better way?
			// maybe call getMapShortcodeArguments() when saving post so they get immediate feedback about any errors in shortcode
			// do something similar for list shortcode arguments?

			global $post;

			if ( ! is_array( $arguments ) )
				return array();


			// Placemark
			if ( isset( $arguments['placemark'] ) ) {
				$pass       = true;
				$originalID = $arguments['placemark'];

				// Check for valid placemark ID
				if ( ! is_numeric( $arguments['placemark'] ) )
					$pass = false;

				$arguments['placemark'] = (int) $arguments['placemark'];

				if ( $arguments['placemark'] <= 0 )
					$pass = false;

				$placemark = get_post( $arguments['placemark'] );
				if ( ! $placemark )
					$pass = false;

				if ( ! $pass ) {
					$error = sprintf(
						__( '%s shortcode error: %s is not a valid placemark ID.', 'bgmp' ),
						BGMP_NAME,
						is_scalar( $originalID ) ? (string) $originalID : gettype( $originalID )
					);
				}

				// Check for valid coordinates
				if ( $pass ) {
					$latitude    = get_post_meta( $arguments['placemark'], 'bgmp_latitude', true );
					$longitude   = get_post_meta( $arguments['placemark'], 'bgmp_longitude', true );
					$coordinates = $this->validateCoordinates( $latitude . ',' . $longitude );

					if ( $coordinates === false ) {
						$pass  = false;
						$error = sprintf(
							__( '%s shortcode error: %s does not have a valid address.', 'bgmp' ),
							BGMP_NAME,
							(string) $originalID
						);
					}
				}


				// Remove the option if it isn't a valid placemark
				if ( ! $pass ) {
					add_notice( $error, 'error' );
					unset( $arguments['placemark'] );
				}
			}


			// Categories
			if ( isset( $arguments['categories'] ) ) {
				if ( is_string( $arguments['categories'] ) ) {
					$arguments['categories'] = explode( ',', $arguments['categories'] );
				} elseif ( ! is_array( $arguments['categories'] ) || empty( $arguments['categories'] ) ) {
					unset( $arguments['categories'] );
				}

				if ( isset( $arguments['categories'] ) && ! empty( $arguments['categories'] ) ) {
					foreach ( $arguments['categories'] as $index => $term ) {
						if ( ! term_exists( $term, self::TAXONOMY ) ) {
							unset( $arguments['categories'][$index] ); // Note - This will leave holes in the key sequence, but it doesn't look like that's a problem with the way we're using it.
							add_notice( sprintf(
								__( '%s shortcode error: %s is not a valid category.', 'bgmp' ),
								BGMP_NAME,
								$term
							), 'error' );
						}
					}
				}
			}

			// Rename width and height keys to match internal ones. Using different ones in shortcode to make it easier for user.
			if ( isset( $arguments['width'] ) ) {
				if ( is_numeric( $arguments['width'] ) && $arguments['width'] > 0 ) {
					$arguments['mapWidth'] = $arguments['width'];
				} else {
					add_notice( sprintf(
						__( '%s shortcode error: %s is not a valid width.', 'bgmp' ),
						BGMP_NAME,
						$arguments['width']
					), 'error' );
				}

				unset( $arguments['width'] );
			}

			if ( isset( $arguments['height'] ) && $arguments['height'] > 0 ) {
				if ( is_numeric( $arguments['height'] ) ) {
					$arguments['mapHeight'] = $arguments['height'];
				} else {
					add_notice( sprintf(
						__( '%s shortcode error: %s is not a valid height.', 'bgmp' ),
						BGMP_NAME,
						$arguments['height']
					), 'error' );
				}

				unset( $arguments['height'] );
			}


			// Center
			if ( isset( $arguments['center'] ) ) {
				// Note: Google's API has a daily request limit, which could be a problem when geocoding map shortcode center address each time page loads. Users could get around that by using a caching plugin, though.

				$coordinates = $this->geocode( $arguments['center'] );
				if ( $coordinates ) {
					$arguments = array_merge( $arguments, $coordinates );
				}

				unset( $arguments['center'] );
			}


			// Zoom
			if ( isset( $arguments['zoom'] ) ) {
				if ( ! is_numeric( $arguments['zoom'] ) || $arguments['zoom'] < self::ZOOM_MIN || $arguments['zoom'] > self::ZOOM_MAX ) {
					add_notice( sprintf(
						__( '%s shortcode error: %s is not a valid zoom level.', 'bgmp' ),
						BGMP_NAME,
						$arguments['zoom']
					), 'error' );

					unset( $arguments['zoom'] );
				}
			}


			// Type
			if ( isset( $arguments['type'] ) ) {
				$arguments['type'] = strtoupper( $arguments['type'] );

				if ( ! array_key_exists( $arguments['type'], $this->settings->mapTypes ) ) {
					add_notice( sprintf(
						__( '%s shortcode error: %s is not a valid map type.', 'bgmp' ),
						BGMP_NAME,
						$arguments['type']
					), 'error' );

					unset( $arguments['type'] );
				}
			}


			return apply_filters( 'bgmp_clean-map-shortcode-arguments-return', $arguments );
		}

		/**
		 * Checks the current post to see if they contain the map shortcode
		 *
		 * @link   http://wordpress.org/support/topic/plugin-basic-google-maps-placemarks-can-i-use-the-shortcode-on-any-php-without-assign-it-in-functionphp
		 * @return bool
		 */
		protected function mapShortcodeCalled() {
			global $post;

			$this->mapShortcodeCalled = apply_filters( 'bgmp_mapShortcodeCalled', $this->mapShortcodeCalled ); // @todo - deprecated b/c not consistent w/ shortcode naming scheme. need a way to notify people
			$this->mapShortcodeCalled = apply_filters( 'bgmp_map-shortcode-called', $this->mapShortcodeCalled );

			if ( $this->mapShortcodeCalled ) {
				return true;
			}

			if ( ! $post ) { // note: this needs to run after the above code, so that templates can call do_shortcode(...) from templates that don't have $post, like 404.php. See link in phpDoc @link for background.
				return false;
			}

			$shortcodes = $this->getShortcodes( $post->post_content ); // note: don't use setup_postdata/get_the_content() in this instance -- http://lists.automattic.com/pipermail/wp-hackers/2013-January/045053.html

			for ( $i = 0; $i < count( $shortcodes[2] ); $i ++ ) {
				if ( $shortcodes[2][$i] == 'bgmp-map' )
					return true;
			}

			return false;
		}

		/**
		 * Load CSS and JavaScript files
		 */
		public function loadResources() {
			$googleMapsLanguage = apply_filters( 'bgmp_map-language', '' );
			if ( $googleMapsLanguage ) {
				$googleMapsLanguage = '&language=' . $googleMapsLanguage;
			}

			wp_register_script(
				'googleMapsAPI',
				'http' . ( is_ssl() ? 's' : '' ) . '://maps.google.com/maps/api/js?sensor=false' . $googleMapsLanguage,
				array(),
				false,
				true
			);

			wp_register_script(
				'markerClusterer',
				plugins_url( 'includes/marker-clusterer/markerclusterer_packed.js', dirname( __FILE__ ) ),
				array(),
				'1.0',
				true
			);

			wp_register_script(
				'bgmp',
				plugins_url( 'javascript/functions.js', dirname( __FILE__ ) ),
				array( 'googleMapsAPI', 'jquery' ),
				self::VERSION,
				true
			);

			wp_register_style(
				'bgmp_style',
				plugins_url( 'css/style.css', dirname( __FILE__ ) ),
				false,
				self::VERSION
			);

			$this->mapShortcodeCalled = $this->mapShortcodeCalled();

			// Load front-end resources
			if ( ! is_admin() && $this->mapShortcodeCalled ) {
				wp_enqueue_script( 'googleMapsAPI' );

				if ( $this->settings->markerClustering ) {
					wp_enqueue_script( 'markerClusterer' );
				}

				wp_enqueue_script( 'bgmp' );
			}

			if ( $this->mapShortcodeCalled ) {
				wp_enqueue_style( 'bgmp_style' );
			}


			// Load meta box resources for settings page
			if ( isset( $_GET['page'] ) && $_GET['page'] == 'bgmp_settings' ) { // @todo better way than $_GET ?
				wp_enqueue_style( 'bgmp_style' );
				wp_enqueue_script( 'dashboard' );
			}
		}

		/**
		 * Outputs elements in the <head> section of the front-end
		 */
		public function outputHead() {
			if ( $this->mapShortcodeCalled ) {
				do_action( 'bgmp_head-before' );
				echo $this->render_template( 'core/front-end-head.php' );
				do_action( 'bgmp_head-after' );
			}
		}

		/**
		 * Registers the custom post type
		 */
		public function createPostType() {
			if ( ! post_type_exists( self::POST_TYPE ) ) {
				$labels = array(
					'name'               => __( 'Placemarks', 'bgmp' ),
					'singular_name'      => __( 'Placemark', 'bgmp' ),
					'add_new'            => __( 'Add New', 'bgmp' ),
					'add_new_item'       => __( 'Add New Placemark', 'bgmp' ),
					'edit'               => __( 'Edit', 'bgmp' ),
					'edit_item'          => __( 'Edit Placemark', 'bgmp' ),
					'new_item'           => __( 'New Placemark', 'bgmp' ),
					'view'               => __( 'View Placemark', 'bgmp' ),
					'view_item'          => __( 'View Placemark', 'bgmp' ),
					'search_items'       => __( 'Search Placemarks', 'bgmp' ),
					'not_found'          => __( 'No Placemarks found', 'bgmp' ),
					'not_found_in_trash' => __( 'No Placemarks found in Trash', 'bgmp' ),
					'parent'             => __( 'Parent Placemark', 'bgmp' )
				);

				$postTypeParams = array(
					'labels'          => $labels,
					'singular_label'  => __( 'Placemarks', 'bgmp' ),
					'public'          => true,
					'menu_position'   => 20,
					'hierarchical'    => false,
					'capability_type' => 'post',
					'rewrite'         => array( 'slug' => 'placemarks', 'with_front' => false ),
					'query_var'       => true,
					'supports'        => array( 'title', 'editor', 'author', 'thumbnail', 'comments', 'revisions' )
				);

				register_post_type(
					self::POST_TYPE,
					apply_filters( 'bgmp_post-type-params', $postTypeParams )
				);
			}
		}

		/**
		 * Registers the category taxonomy
		 */
		public function createCategoryTaxonomy() {
			if ( ! taxonomy_exists( self::TAXONOMY ) ) {
				$taxonomyParams = array(
					'label'                 => __( 'Category', 'bgmp' ),
					'labels'                => array( 'name' => __( 'Categories', 'bgmp' ), 'singular_name' => __( 'Category', 'bgmp' ) ),
					'hierarchical'          => true,
					'rewrite'               => array( 'slug' => self::TAXONOMY ),
					'update_count_callback' => '_update_post_term_count'
				);

				register_taxonomy(
					self::TAXONOMY,
					self::POST_TYPE,
					apply_filters( 'bgmp_category-taxonomy-params', $taxonomyParams )
				);
			}
		}

		/**
		 * Sorts the posts by the title in the admin view posts screen
		 */
		function sortAdminView( $query ) {
			global $pagenow;

			if ( is_admin() && $pagenow == 'edit.php' && array_key_exists( 'post_type', $_GET ) && $_GET['post_type'] == self::POST_TYPE ) {
				$query->query_vars['order']   = apply_filters( 'bgmp_admin-sort-order', 'ASC' );
				$query->query_vars['orderby'] = apply_filters( 'bgmp_admin-sort-orderby', 'title' );

				// @todo - should just have a filter on $query, or don't even need one at all, since they can filter $query directly?
			}
		}

		/**
		 * Adds meta boxes for the custom post type
		 */
		public function addMetaBoxes() {
			add_meta_box(
				'bgmp_placemark-address',
				__( 'Placemark Address', 'bgmp' ),
				array( $this, 'markupAddressFields' ),
				self::POST_TYPE,
				'normal',
				'high'
			);

			add_meta_box(
				'bgmp_placemark-zIndex',
				__( 'Stacking Order', 'bgmp' ),
				array( $this, 'markupZIndexField' ),
				self::POST_TYPE,
				'side',
				'low'
			);
		}

		/**
		 * Outputs the markup for the address fields
		 */
		public function markupAddressFields() {
			global $post;

			$variables = array(
				'address'            => get_post_meta( $post->ID, 'bgmp_address', true ),
				'latitude'           => get_post_meta( $post->ID, 'bgmp_latitude', true ),
				'longitude'          => get_post_meta( $post->ID, 'bgmp_longitude', true ),
			);

			$variables['showGeocodeResults'] = $variables['address'] && ! self::validateCoordinates( $variables['address'] ) && $variables['latitude'] && $variables['longitude'] ? true : false;
			$variables['showGeocodeError']   = $variables['address'] && ( ! $variables['latitude'] || ! $variables['longitude'] ) ? true : false;

			echo $this->render_template( 'core/meta-address.php', $variables );
		}

		/**
		 * Outputs the markup for the stacking order field
		 */
		public function markupZIndexField() {
			global $post;

			$zIndex = get_post_meta( $post->ID, 'bgmp_zIndex', true );
			if ( filter_var( $zIndex, FILTER_VALIDATE_INT ) === FALSE ) {
				$zIndex = 0;
			}

			echo $this->render_template( 'core/meta-z-index.php', array( 'zIndex' => $zIndex ) );
		}

		/**
		 * Saves values of the the custom post type's extra fields
		 *
		 * @param int $postID
		 */
		public function saveCustomFields( $postID ) {
			global $post;
			$coordinates    = false;
			$ignoredActions = array( 'trash', 'untrash', 'restore' );

			// Check preconditions
			if ( isset( $_GET['action'] ) && in_array( $_GET['action'], $ignoredActions ) ) {
				return;
			}

			if ( ! $post || $post->post_type != self::POST_TYPE || ! current_user_can( 'edit_posts' ) ) {
				return;
			}

			if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || $post->post_status == 'auto-draft' ) {
				return;
			}


			// Save address
			if ( isset( $_POST['bgmp_address'] ) ) {
				update_post_meta( $post->ID, 'bgmp_address', $_POST['bgmp_address'] );

				if ( $_POST['bgmp_address'] )
					$coordinates = $this->geocode( $_POST['bgmp_address'] );
			}

			if ( $coordinates ) {
				update_post_meta( $post->ID, 'bgmp_latitude', $coordinates['latitude'] );
				update_post_meta( $post->ID, 'bgmp_longitude', $coordinates['longitude'] );
			} else {
				update_post_meta( $post->ID, 'bgmp_latitude', '' );
				update_post_meta( $post->ID, 'bgmp_longitude', '' );
			}

			// Save z-index
			if ( isset( $_POST['bgmp_zIndex'] ) ) {
				if ( filter_var( $_POST['bgmp_zIndex'], FILTER_VALIDATE_INT ) === FALSE ) {
					update_post_meta( $post->ID, 'bgmp_zIndex', 0 );
					add_notice( __( 'The stacking order has to be an integer.', 'bgmp' ), 'error' );
				} else {
					update_post_meta( $post->ID, 'bgmp_zIndex', $_POST['bgmp_zIndex'] );
				}
			}
		}

		/**
		 * Geocodes an address
		 *
		 * @param string $address
		 * @return mixed
		 */
		public function geocode( $address ) {
			// @todo - this should be static, or better yet, broken out into an Address class

			// Bypass geocoding if already have valid coordinates
			$coordinates = self::validateCoordinates( $address );
			if ( is_array( $coordinates ) ) {
				return $coordinates;
			}

			// Geocode address and handle errors
			$geocodeResponse = wp_remote_get( 'http://maps.googleapis.com/maps/api/geocode/json?address=' . str_replace( ' ', '+', $address ) . '&sensor=false' );
			// @todo - esc_url() on address?

			if ( is_wp_error( $geocodeResponse ) ) {
				add_notice( sprintf(
					__( '%s geocode error: %s', 'bgmp' ),
					BGMP_NAME,
					implode( '<br />', $geocodeResponse->get_error_messages() )
				), 'error' );

				return false;
			}

			// Check response code
			if ( ! isset( $geocodeResponse['response']['code'] ) || ! isset( $geocodeResponse['response']['message'] ) ) {
				add_notice( sprintf(
					__( '%s geocode error: Response code not present', 'bgmp' ),
					BGMP_NAME
				), 'error' );

				return false;
			} elseif ( $geocodeResponse['response']['code'] != 200 ) {
				/*
					@todo - strip content inside <style> tag. regex inappropriate, but DOMDocument doesn't exist on dev server, but does on most?. would have to wrap this in an if( class_exists() )...
					
					$responseHTML = new DOMDocument();
					$responseHTML->loadHTML( $geocodeResponse[ 'body' ] );
					// nordmalize it b/c doesn't have <body> tag inside?
					$this->describe( $responseHTML->saveHTML() );
				*/

				add_notice( sprintf(
					__( '<p>%s geocode error: %d %s</p> <p>Response: %s</p>', 'bgmp' ),
					BGMP_NAME,
					$geocodeResponse['response']['code'],
					$geocodeResponse['response']['message'],
					strip_tags( $geocodeResponse['body'] )
				), 'error' );

				return false;
			}

			// Decode response and handle errors
			$coordinates = json_decode( $geocodeResponse['body'] );

			if ( function_exists( 'json_last_error' ) && json_last_error() != JSON_ERROR_NONE ) {
				// @todo - Once PHP 5.3+ is more widely adopted, remove the function_exists() check here and just bump the PHP requirement to 5.3

				add_notice( sprintf( __( '%s geocode error: Response was not formatted in JSON.', 'bgmp' ), BGMP_NAME ), 'error' );
				return false;
			}

			if ( isset( $coordinates->status ) && $coordinates->status == 'REQUEST_DENIED' ) {
				add_notice( sprintf( __( '%s geocode error: Request Denied.', 'bgmp' ), BGMP_NAME ), 'error' );
				return false;
			}

			if ( ! isset( $coordinates->results ) || empty( $coordinates->results ) ) {
				add_notice( __( "That address couldn't be geocoded, please make sure that it's correct.", 'bgmp' ), "error" );
				add_notice(
					__( "Geocode response:", 'bgmp' ) . ' <pre>' . print_r( $coordinates, true ) . '</pre>',
					"error"
				);
				return false;
			}

			return array( 'latitude' => $coordinates->results[0]->geometry->location->lat, 'longitude' => $coordinates->results[0]->geometry->location->lng );
		}

		/**
		 * Checks if a given string represents a valid set of geographic coordinates
		 * Expects latitude/longitude notation, not minutes/seconds
		 *
		 * @param string $coordinates
		 * @return mixed false if any of the tests fails | an array with 'latitude' and 'longitude' keys/value pairs if all of the tests succeed
		 */
		public static function validateCoordinates( $coordinates ) {
			// @todo - some languages swap the roles of the commas and decimal point. this assumes english.

			$coordinates = str_replace( ' ', '', $coordinates );

			if ( ! $coordinates ) {
				return false;
			}

			if ( substr_count( $coordinates, ',' ) != 1 ) {
				return false;
			}

			$coordinates = explode( ',', $coordinates );
			$latitude    = $coordinates[0];
			$longitude   = $coordinates[1];

			if ( ! is_numeric( $latitude ) || $latitude < - 90 || $latitude > 90 ) {
				return false;
			}

			if ( ! is_numeric( $longitude ) || $longitude < - 180 || $longitude > 180 ) {
				return false;
			}

			return array( 'latitude' => $latitude, 'longitude' => $longitude );
		}

		/**
		 * Reverse-geocodes a set of coordinates
		 * Google's API has a daily request limit, but this is only called during upgrades from 1.0, so that shouldn't ever be a problem.
		 *
		 * @param string $latitude
		 * @param string $longitude
		 */
		protected function reverseGeocode( $latitude, $longitude ) {
			$geocodeResponse = wp_remote_get( 'http://maps.googleapis.com/maps/api/geocode/json?latlng=' . $latitude . ',' . $longitude . '&sensor=false' );
			$address         = json_decode( $geocodeResponse['body'] );

			if ( is_wp_error( $geocodeResponse ) || empty( $address->results ) ) {
				return false;
			} else {
				return $address->results[0]->formatted_address;
			}
		}

		/**
		 * Defines the [bgmp-map] shortcode
		 *
		 * @param array $attributes Array of parameters automatically passed in by WordPress
		 * @return string The output of the shortcode
		 */
		public function mapShortcode( $attributes ) {
			if ( ! wp_script_is( 'googleMapsAPI', 'queue' ) || ! wp_script_is( 'bgmp', 'queue' ) || ! wp_style_is( 'bgmp_style', 'queue' ) ) {
				$error = sprintf(
					__( '<p class="error">%s error: JavaScript and/or CSS files aren\'t loaded. If you\'re using do_shortcode() you need to add a filter to your theme first. See <a href="%s">the FAQ</a> for details.</p>', 'bgmp' ),
					BGMP_NAME,
					'http://wordpress.org/extend/plugins/basic-google-maps-placemarks/faq/'
				);

				// @todo maybe change this to use core/views/message.php

				return $error;
			}

			if ( isset( $attributes['categories'] ) )
				$attributes['categories'] = apply_filters( 'bgmp_mapShortcodeCategories', $attributes['categories'] ); // @todo - deprecated b/c 1.9 output bgmpdata in post; can now just set args in do_shortcode() . also  not consistent w/ shortcode naming scheme and have filter for all arguments now. need a way to notify people

			$attributes = apply_filters( 'bgmp_map-shortcode-arguments', $attributes ); // @todo - deprecated b/c 1.9 output bgmpdata in post...
			$attributes = $this->cleanMapShortcodeArguments( $attributes );

			ob_start();
			do_action( 'bgmp_meta-address-before' );	// @todo - deprecated b/c named incorrectly
			do_action( 'bgmp_shortcode-bgmp-map-before' );
			echo $this->render_template( 'core/shortcode-bgmp-map.php', array( 'attributes' => $attributes ) );
			do_action( 'bgmp_shortcode-bgmp-map-after' );
			$output = ob_get_clean();

			return $output;
		}

		/**
		 * Defines the [bgmp-list] shortcode
		 *
		 * @param array $attributes Array of parameters automatically passed in by WordPress
		 * @return string The output of the shortcode
		 */
		public function listShortcode( $attributes ) {
			$attributes = apply_filters( 'bgmp_list-shortcode-arguments', $attributes );
			// @todo shortcode_atts()

			$params = array(
				'numberposts' => - 1,
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'orderby'     => 'title',
				'order'       => 'ASC'
			);

			if ( isset( $attributes['categories'] ) && ! empty( $attributes['categories'] ) ) {
				// @todo - check each cat to make sure it exists? if not, print error to admin panel.
				// non-existant cats don't break the query or anything, so the only purpose for this would be to give feedback to the admin.

				$params['tax_query'] = array(
					array(
						'taxonomy' => self::TAXONOMY,
						'field'    => 'slug',
						'terms'    => explode( ',', $attributes['categories'] )
					)
				);
			}

			$viewOnMap = isset( $attributes['viewonmap'] ) && $attributes['viewonmap'] == true;

			$posts = get_posts( apply_filters( 'bgmp_list-shortcode-params', $params ) );
			$posts = apply_filters( 'bgmp_list-shortcode-posts', $posts );

			if ( $posts ) {
				$output = '<ul id="' . 'bgmp_list" class="' . 'bgmp_list">'; // Note: id should be removed and everything switched to class, because there could be more than one list on a page. That would be backwards-compatability, though.

				foreach ( $posts as $p ) {
					$variables = array(
						'p'         => $p,
						'viewOnMap' => $viewOnMap,
						'address'   => get_post_meta( $p->ID, 'bgmp_address', true ),
					);
					$markerHTML = $this->render_template( 'core/shortcode-bgmp-list-marker.php', $variables, 'always' );

					$output .= apply_filters( 'bgmp_list-marker-output', $markerHTML, $p->ID );	// @todo deprecate b/c render_template has filter for this and everything else now
				}

				$output .= '</ul>';

				return $output;
			} else {
				return __( 'No Placemarks found', 'bgmp' );
			}
		}

		/**
		 * Gets map options
		 *
		 * @param array $attributes
		 * @return string JSON-encoded array
		 */
		public function getMapOptions( $attributes ) {
			$clusterStyles = array(
				'people' => array(
					array(
						'url'         => plugins_url( 'includes/marker-clusterer/images/people35.png', __FILE__ ),
						'height'      => 35,
						'width'       => 35,
						'anchor'      => array( 16, 0 ),
						'textColor'   => '#ff00ff',
						'textSize'    => 10
					),

					array(
						'url'         => plugins_url( 'includes/marker-clusterer/images/people45.png', __FILE__ ),
						'height'      => 45,
						'width'       => 45,
						'anchor'      => array( 24, 0 ),
						'textColor'   => '#ff0000',
						'textSize'    => 11
					),

					array(
						'url'         => plugins_url( 'includes/marker-clusterer/images/people55.png', __FILE__ ),
						'height'      => 55,
						'width'       => 55,
						'anchor'      => array( 32, 0 ),
						'textColor'   => '#ffffff',
						'textSize'    => 12
					)
				),

				'conversation' => array(
					array(
						'url'         => plugins_url( 'includes/marker-clusterer/images/conv30.png', __FILE__ ),
						'height'      => 27,
						'width'       => 30,
						'anchor'      => array( 3, 0 ),
						'textColor'   => '#ff00ff',
						'textSize'    => 10
					),

					array(
						'url'         => plugins_url( 'includes/marker-clusterer/images/conv40.png', __FILE__ ),
						'height'      => 36,
						'width'       => 40,
						'anchor'      => array( 6, 0 ),
						'textColor'   => '#ff0000',
						'textSize'    => 11
					),

					array(
						'url'         => plugins_url( 'includes/marker-clusterer/images/conv50.png', __FILE__ ),
						'height'      => 50,
						'width'       => 45,
						'anchor'      => array( 8, 0 ),
						'textSize'    => 12
					)
				),

				'hearts' => array(
					array(
						'url'         => plugins_url( 'includes/marker-clusterer/images/heart30.png', __FILE__ ),
						'height'      => 26,
						'width'       => 30,
						'anchor'      => array( 4, 0 ),
						'textColor'   => '#ff00ff',
						'textSize'    => 10
					),

					array(
						'url'         => plugins_url( 'includes/marker-clusterer/images/heart40.png', __FILE__ ),
						'height'      => 35,
						'width'       => 40,
						'anchor'      => array( 8, 0 ),
						'textColor'   => '#ff0000',
						'textSize'    => 11
					),

					array(
						'url'         => plugins_url( 'includes/marker-clusterer/images/heart50.png', __FILE__ ),
						'height'      => 50,
						'width'       => 44,
						'anchor'      => array( 12, 0 ),
						'textSize'    => 12
					)
				)
			);

			$options = array(
				'mapWidth'           => $this->settings->mapWidth, // @todo move these into 'map' subarray? but then have to worry about backwards compat
				'mapHeight'          => $this->settings->mapHeight,
				'latitude'           => $this->settings->mapLatitude,
				'longitude'          => $this->settings->mapLongitude,
				'zoom'               => $this->settings->mapZoom,
				'type'               => $this->settings->mapType,
				'typeControl'        => $this->settings->mapTypeControl,
				'navigationControl'  => $this->settings->mapNavigationControl,
				'infoWindowMaxWidth' => $this->settings->mapInfoWindowMaxWidth,
				'streetViewControl'  => apply_filters( 'bgmp_street-view-control', true ), // deprecated b/c of bgmp_map-options filter?
				'viewOnMapScroll'    => false,

				'clustering'         => array(
					'enabled'        => $this->settings->markerClustering,
					'maxZoom'        => $this->settings->clusterMaxZoom,
					'gridSize'       => $this->settings->clusterGridSize,
					'style'          => $this->settings->clusterStyle,
					'styles'         => $clusterStyles,
				),
			);

			// Reset center/zoom when only displaying single placemark
			if ( isset( $attributes['placemark'] ) && apply_filters( 'bgmp_reset-individual-map-center-zoom', true ) ) {
				$latitude    = get_post_meta( $attributes['placemark'], 'bgmp_latitude', true );
				$longitude   = get_post_meta( $attributes['placemark'], 'bgmp_longitude', true );
				$coordinates = $this->validateCoordinates( $latitude . ',' . $longitude );

				if ( $coordinates !== false ) {
					$options['latitude']  = $latitude;
					$options['longitude'] = $longitude;
					$options['zoom']      = apply_filters( 'bgmp_individual-map-default-zoom', 13 ); // deprecated b/c of bgmp_map-options filter?
				}
			}

			$options = shortcode_atts( $options, $attributes );

			return apply_filters( 'bgmp_map-options', $options );
		}

		/**
		 * Gets the published placemarks from the database, formats and outputs them.
		 *
		 * @param array $attributes
		 * @return string JSON-encoded array
		 */
		public function getMapPlacemarks( $attributes ) {
			$placemarks = array();

			$query = array(
				'numberposts' => - 1,
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish'
			);

			if ( isset( $attributes['placemark'] ) )
				$query['p'] = $attributes['placemark'];

			if ( isset( $attributes['categories'] ) && ! empty( $attributes['categories'] ) ) {
				$query['tax_query'] = array(
					array(
						'taxonomy' => self::TAXONOMY,
						'field'    => 'slug',
						'terms'    => $attributes['categories']
					)
				);
			}

			$query               = apply_filters( 'bgmp_get-placemarks-query', $query ); // @todo - filter name deprecated
			$publishedPlacemarks = get_posts( apply_filters( 'bgmp_get-map-placemarks-query', $query ) );

			if ( $publishedPlacemarks ) {
				foreach ( $publishedPlacemarks as $pp ) {
					$postID = $pp->ID;

					$categories = get_the_terms( $postID, self::TAXONOMY );
					if ( ! is_array( $categories ) ) {
						$categories = array();
					}

					$icon        = wp_get_attachment_image_src( get_post_thumbnail_id( $postID ), apply_filters( 'bgmp_featured-icon-size', 'thumbnail' ) );
					$defaultIcon = apply_filters( 'bgmp_default-icon', plugins_url( 'images/default-marker.png', dirname( __FILE__ ) ), $postID );

					$placemark = array(
						'id'         => $postID,
						'title'      => apply_filters( 'the_title', $pp->post_title ),
						'latitude'   => get_post_meta( $postID, 'bgmp_latitude', true ),
						'longitude'  => get_post_meta( $postID, 'bgmp_longitude', true ),
						'details'    => apply_filters( 'the_content', $pp->post_content ), // note: don't use setup_postdata/get_the_content() in this instance -- http://lists.automattic.com/pipermail/wp-hackers/2013-January/045053.html
						'categories' => $categories,
						'icon'       => is_array( $icon ) ? $icon[0] : $defaultIcon,
						'zIndex'     => get_post_meta( $postID, 'bgmp_zIndex', true )
					);

					$placemarks[] = apply_filters( 'bgmp_get-map-placemarks-individual-placemark', $placemark );
				}
			}

			$placemarks = apply_filters( 'bgmp_get-placemarks-return', $placemarks ); // @todo - filter name deprecated
			return apply_filters( 'bgmp_get-map-placemarks-return', $placemarks );
		}

		/**
		 * Render a template
		 *
		 * Allows parent/child themes to override the markup by placing the a file named basename( $default_template_path ) in their root folder,
		 * and also allows plugins or themes to override the markup by a filter. Themes might prefer that method if they place their templates
		 * in sub-directories to avoid cluttering the root folder. In both cases, the theme/plugin will have access to the variables so they can
		 * fully customize the output.
		 *
		 * @param  string $default_template_path The path to the template, relative to the plugin's `views` folder
		 * @param  array  $variables             An array of variables to pass into the template's scope, indexed with the variable name so that it can be extract()-ed
		 * @param  string $require               'once' to use require_once() | 'always' to use require()
		 * @return string
		 */
		public function render_template( $default_template_path = false, $variables = array(), $require = 'once' ) {
			$template_path = locate_template( basename( $default_template_path ) );
			if ( ! $template_path ) {
				$template_path = dirname( dirname( __FILE__ ) ) . '/views/' . $default_template_path;
			}
			$template_path = apply_filters( 'bgmp_template_path', $template_path );

			if ( is_file( $template_path ) ) {
				extract( $variables );
				ob_start();

				if ( 'always' == $require ) {
					require( $template_path );
				} else {
					require_once( $template_path );
				}

				$template_content = apply_filters( 'bgmp_template_content', ob_get_clean(), $default_template_path, $template_path, $variables );
			} else {
				$template_content = '';
			}

			return $template_content;
		}

		/**
		 * Writes options to the database
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function shutdown() {
			if ( $this->updatedOptions )
				update_option( 'bgmp_options', $this->options );
		}
	} // end BasicGoogleMapsPlacemarks
}

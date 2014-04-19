<?php

if ( ! class_exists( 'BGMP_Settings' ) ) {
	/**
	 * Registers and handles the plugin's settings
	 */
	class BGMP_Settings extends BGMP_Module {
		public $map_width, $map_height, $map_address, $map_latitude, $map_longitude, $map_zoom, $map_type, $map_types, $map_type_control, $map_navigation_control, $map_info_window_max_width, $marker_clustering, $cluster_max_zoom, $cluster_grid_size, $cluster_style;

		/**
		 * Constructor
		 *
		 * @param object Basic_Google_Maps_Placemarks object
		 */
		public function __construct() {
			add_action( 'init',          array( $this, 'init' ), 9 ); // lower priority so that variables defined here will be available to other init callbacks
			add_action( 'init',          array( $this, 'update_map_coordinates' ) );
			add_action( 'admin_menu',    array( $this, 'add_settings_page' ) );
			add_action( 'admin_init',    array( $this, 'add_settings' ) ); // @todo - this may need to fire after admin_menu

			add_filter( 'plugin_action_links_basic-google-maps-placemarks/basic-google-maps-placemarks.php', array( $this, 'add_settings_link' ) );
		}

		/**
		 * Performs various initialization functions
		 */
		public function init() {
			// @todo saving this as a single array instead of separate options

			$this->map_width     = get_option( 'bgmp_map-width',        600 );
			$this->map_height    = get_option( 'bgmp_map-height',       400 );
			$this->map_address   = get_option( 'bgmp_map-address',      __( 'Seattle', 'bgmp' ) );
			$this->map_latitude  = get_option( 'bgmp_map-latitude',     47.6062095 );
			$this->map_longitude = get_option( 'bgmp_map-longitude',    -122.3320708 );
			$this->map_zoom      = get_option( 'bgmp_map-zoom',         7 );
			$this->map_type      = get_option( 'bgmp_map-type',        'ROADMAP' );

			$this->map_types = array(
				'ROADMAP'   => __( 'Street Map', 'bgmp' ),
				'SATELLITE' => __( 'Satellite Images', 'bgmp' ),
				'HYBRID'    => __( 'Hybrid', 'bgmp' ),
				'TERRAIN'   => __( 'Terrain', 'bgmp' )
			);

			$this->map_type_control          = get_option( 'bgmp_map-type-control',       'off' );
			$this->map_navigation_control    = get_option( 'bgmp_map-navigation-control', 'DEFAULT' );
			$this->map_info_window_max_width = get_option( 'bgmp_map-info-window-width',   500 );

			$this->marker_clustering  = get_option( 'bgmp_marker-clustering',  '' );
			$this->cluster_max_zoom   = get_option( 'bgmp_cluster-max-zoom',   '7' );
			$this->cluster_grid_size  = get_option( 'bgmp_cluster-grid-size',  '40' );
			$this->cluster_style      = get_option( 'bgmp_cluster-style',      'default' );
		}

		/**
		 * Get the map center coordinates from the address and update the database values
		 *
		 * The latitude/longitude need to be updated when the address changes, but there's no way to do that with the settings API
		 */
		public function update_map_coordinates() {
			// @todo - this could be done during a settings validation callback?
			global $bgmp;

			$have_coordinates = true;

			if ( isset( $_POST['bgmp_map-address'] ) ) {
				if ( empty( $_POST['bgmp_map-address'] ) ) {
					$have_coordinates = false;
				} else {
					$coordinates = $bgmp->geocode( $_POST['bgmp_map-address'] );

					if ( ! $coordinates ) {
						$have_coordinates = false;
					}
				}

				if ( $have_coordinates ) {
					update_option( 'bgmp_map-latitude', $coordinates['latitude'] );
					update_option( 'bgmp_map-longitude', $coordinates['longitude'] );
				} else {
					add_notice( "That address couldn't be geocoded, please make sure that it's correct.", 'error' );

					update_option( 'bgmp_map-latitude', '' ); // @todo - update these
					update_option( 'bgmp_map-longitude', '' );
				}
			}
		}

		/**
		 * Adds a page to Settings menu
		 */
		public function add_settings_page() {
			add_options_page(
				BGMP_NAME . ' Settings',
				BGMP_NAME,
				'manage_options',
				'bgmp_settings',
				array( $this, 'markup_settings_page' )
			);

			add_meta_box(
				'bgmp_rasr-plug', __( 'Re-Abolish Slavery', 'bgmp' ),
				array( $this, 'markup_rasr_meta_box' ),
				'settings_page_' . 'bgmp_settings',
				'side'
			);
		}

		/**
		 * Creates the markup for the settings page
		 */
		public function markup_settings_page() {
			$variables = array(
				'rasr_meta_box_id'   => 'bgmp_rasr-plug',
				'rasr_meta_box_page' => 'bgmp_settings', // @todo better var name
			);
			$variables['hidden']       = get_hidden_meta_boxes( $variables['rasr_meta_box_page'] );
			$variables['hidden_class'] = in_array( $variables['rasr_meta_box_page'], $variables['hidden'] ) ? ' hide-if-js' : '';

			// @todo some of above may not be needed

			if ( current_user_can( 'manage_options' ) )
				echo $GLOBALS['bgmp']->render_template( 'settings/settings.php', $variables );
			else
				wp_die( 'Access denied.' );
		}

		/**
		 * Creates the markup for the Re-Abolish Slavery Ribbon meta box
		 */
		public function markup_rasr_meta_box() {
			echo $GLOBALS['bgmp']->render_template( 'settings/meta-re-abolish-slavery.php' );
		}

		/**
		 * Adds a 'Settings' link to the Plugins page
		 *
		 * @param array $links The links currently mapped to the plugin
		 * @return array
		 */
		public function add_settings_link( $links ) {
			array_unshift( $links, '<a href="http://wordpress.org/extend/plugins/basic-google-maps-placemarks/faq/">' . __( 'Help', 'bgmp' ) . '</a>' );
			array_unshift( $links, '<a href="options-general.php?page=' . 'bgmp_settings">' . __( 'Settings', 'bgmp' ) . '</a>' );

			return $links;
		}

		/**
		 * Adds our custom settings to the admin Settings pages
		 *
		 * We intentionally don't register the map-latitude and map-longitude settings because they're set by update_map_coordinates()
		 */
		public function add_settings() {
			add_settings_section(
				'bgmp_map-settings',
				'',
				array( $this, 'markup_settings_sections' ),
				'bgmp_settings'
			);

			add_settings_section(
				'bgmp_marker-cluster-settings',
				'',
				array( $this, 'markup_settings_sections' ),
				'bgmp_settings'
			);


			// Map Settings
			add_settings_field(
				'bgmp_map-width',
				__( 'Map Width', 'bgmp' ),
				array( $this, 'markup_map_settings_fields' ),
				'bgmp_settings',
				'bgmp_map-settings',
				array( 'label_for' => 'bgmp_map-width' )
			);

			add_settings_field(
				'bgmp_map-height',
				__( 'Map Height', 'bgmp' ),
				array( $this, 'markup_map_settings_fields' ),
				'bgmp_settings',
				'bgmp_map-settings',
				array( 'label_for' => 'bgmp_map-height' )
			);

			add_settings_field(
				'bgmp_map-address',
				__( 'Map Center Address', 'bgmp' ),
				array( $this, 'markup_map_settings_fields' ),
				'bgmp_settings',
				'bgmp_map-settings',
				array( 'label_for' => 'bgmp_map-address' )
			);

			add_settings_field(
				'bgmp_map-zoom',
				__( 'Zoom', 'bgmp' ),
				array( $this, 'markup_map_settings_fields' ),
				'bgmp_settings',
				'bgmp_map-settings',
				array( 'label_for' => 'bgmp_map-zoom' )
			);

			add_settings_field(
				'bgmp_map-type',
				__( 'Map Type', 'bgmp' ),
				array( $this, 'markup_map_settings_fields' ),
				'bgmp_settings',
				'bgmp_map-settings',
				array( 'label_for' => 'bgmp_map-type' )
			);

			add_settings_field(
				'bgmp_map-type-control',
				__( 'Type Control', 'bgmp' ),
				array( $this, 'markup_map_settings_fields' ),
				'bgmp_settings',
				'bgmp_map-settings',
				array( 'label_for' => 'bgmp_map-type-control' )
			);

			add_settings_field(
				'bgmp_map-navigation-control',
				__( 'Navigation Control', 'bgmp' ),
				array( $this, 'markup_map_settings_fields' ),
				'bgmp_settings',
				'bgmp_map-settings',
				array( 'label_for' => 'bgmp_map-navigation-control' )
			);

			add_settings_field(
				'bgmp_map-info-window-width',
				__( 'Info. Window Maximum Width', 'bgmp' ),
				array( $this, 'markup_map_settings_fields' ),
				'bgmp_settings',
				'bgmp_map-settings',
				array( 'label_for' => 'bgmp_map-info-window-width' )
			);

			register_setting( 'bgmp_settings', 'bgmp_map-width' );
			register_setting( 'bgmp_settings', 'bgmp_map-height' );
			register_setting( 'bgmp_settings', 'bgmp_map-address' );
			register_setting( 'bgmp_settings', 'bgmp_map-zoom' );
			register_setting( 'bgmp_settings', 'bgmp_map-type' );
			register_setting( 'bgmp_settings', 'bgmp_map-type-control' );
			register_setting( 'bgmp_settings', 'bgmp_map-navigation-control' );
			register_setting( 'bgmp_settings', 'bgmp_map-info-window-width' );


			// Marker Clustering
			add_settings_field(
				'bgmp_marker-clustering',
				__( 'Marker Clustering', 'bgmp' ),
				array( $this, 'markup_marker_cluster_fields' ),
				'bgmp_settings',
				'bgmp_marker-cluster-settings',
				array( 'label_for' => 'bgmp_marker-clustering' )
			);

			add_settings_field(
				'bgmp_cluster-max-zoom',
				__( 'Max Zoom', 'bgmp' ),
				array( $this, 'markup_marker_cluster_fields' ),
				'bgmp_settings',
				'bgmp_marker-cluster-settings',
				array( 'label_for' => 'bgmp_cluster-max-zoom' )
			);

			add_settings_field(
				'bgmp_cluster-grid-size',
				__( 'Grid Size', 'bgmp' ),
				array( $this, 'markup_marker_cluster_fields' ),
				'bgmp_settings',
				'bgmp_marker-cluster-settings',
				array( 'label_for' => 'bgmp_cluster-grid-size' )
			);

			add_settings_field(
				'bgmp_cluster-style',
				__( 'Style', 'bgmp' ),
				array( $this, 'markup_marker_cluster_fields' ),
				'bgmp_settings',
				'bgmp_marker-cluster-settings',
				array( 'label_for' => 'bgmp_cluster-style' )
			);

			register_setting( 'bgmp_settings', 'bgmp_marker-clustering' );
			register_setting( 'bgmp_settings', 'bgmp_cluster-max-zoom' );
			register_setting( 'bgmp_settings', 'bgmp_cluster-grid-size' );
			register_setting( 'bgmp_settings', 'bgmp_cluster-style' );

			// @todo - add input validation  -- http://ottopress.com/2009/wordpress-settings-api-tutorial/
		}

		/**
		 * Adds the markup for the  section introduction text to the Settings page
		 *
		 * @param array $section
		 */
		public function markup_settings_sections( $section ) {
			echo $GLOBALS['bgmp']->render_template( 'settings/section-headers.php', array( 'section' => $section ), 'always' );
		}

		/**
		 * Adds the markup for the all of the fields in the Map Settings section
		 *
		 * @param array $field
		 */
		public function markup_map_settings_fields( $field ) {
			$variables = array(
				'field'                     => $field,
				'map_width'                 => $this->map_width,
				'map_height'                => $this->map_height,
				'map_address'               => $this->map_address,
				'map_latitude'              => $this->map_latitude,
				'map_longitude'             => $this->map_longitude,
				'map_zoom'                  => $this->map_zoom,
				'map_type'                  => $this->map_type,
				'map_types'                 => $this->map_types,
				'map_type_control'          => $this->map_type_control,
				'map_navigation_control'    => $this->map_navigation_control,
				'map_info_window_max_width' => $this->map_info_window_max_width,
			);

			echo $GLOBALS['bgmp']->render_template( 'settings/fields-map.php', $variables, 'always' );
		}

		/**
		 * Adds the markup for the all of the fields in the Map Settings section
		 *
		 * @param array $field
		 */
		public function markup_marker_cluster_fields( $field ) {
			$variables = array(
				'field'              => $field,
				'marker_clustering'  => $this->marker_clustering,
				'cluster_max_zoom'   => $this->cluster_max_zoom,
				'cluster_grid_size'  => $this->cluster_grid_size,
				'cluster_style'      => $this->cluster_style,
			);

			echo $GLOBALS['bgmp']->render_template( 'settings/fields-marker-clusterer.php', $variables, 'always' );
		}
	} // end BGMP_Settings
}

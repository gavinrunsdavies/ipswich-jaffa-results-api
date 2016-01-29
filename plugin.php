<?php
/*
Plugin Name: Ipswich JAFFA RC Results WP REST API
* Ref: https://github.com/petenelson/extending-wp-rest-api
*/

if ( ! defined( 'ABSPATH' ) ) die( 'restricted access' );

require_once plugin_dir_path( __FILE__ ) .'v1/class-ipswich-jaffa-results-wp-rest-api-controller-v1.php';
require_once plugin_dir_path( __FILE__ ) .'v2/class-ipswich-jaffa-results-wp-rest-api-controller-v2.php';

// hook into the rest_api_init action so we can start registering routes
$api_controller_V1 = new Ipswich_JAFFA_Results_WP_REST_API_Controller_V1();
add_action( 'rest_api_init', array( $api_controller_V1, 'rest_api_init') );
add_action( 'plugins_loaded', array( $api_controller_V1, 'plugins_loaded') );

// $api_controller_V2 = new Ipswich_JAFFA_Results_WP_REST_API_Controller_V2();
// add_action( 'rest_api_init', array( $api_controller_V2, 'rest_api_init') );
// add_action( 'plugins_loaded', array( $api_controller_V2, 'plugins_loaded') );

?>
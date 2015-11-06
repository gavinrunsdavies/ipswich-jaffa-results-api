<?php
/*
Plugin Name: Ipswich JAFFA RC Results WP REST API
* Ref: https://github.com/petenelson/extending-wp-rest-api
*/

if ( ! defined( 'ABSPATH' ) ) die( 'restricted access' );

require_once plugin_dir_path( __FILE__ ) .'includes/class-ipswich-jaffa-results-wp-rest-api-controller.php';

// hook into the rest_api_init action so we can start registering routes
$api_controller = new Ipswich_JAFFA_Results_WP_REST_API_Controller();
add_action( 'rest_api_init', array( $api_controller, 'rest_api_init') );
add_action( 'plugins_loaded', array( $api_controller, 'plugins_loaded') );

?>
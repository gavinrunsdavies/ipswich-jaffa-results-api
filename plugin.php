<?php
/*
Plugin Name: Ipswich JAFFA RC Results WP REST API
*/

if ( ! defined( 'ABSPATH' ) ) die( 'restricted access' );

require_once plugin_dir_path( __FILE__ ) .'Config.php';
require_once plugin_dir_path( __FILE__ ) .'WordPressApiHelper.php';

require_once plugin_dir_path( __FILE__ ) .'v2/EventsController.php';
require_once plugin_dir_path( __FILE__ ) .'v2/DistancesController.php';
require_once plugin_dir_path( __FILE__ ) .'v2/MeetingsController.php';
require_once plugin_dir_path( __FILE__ ) .'v2/RacesController.php';
require_once plugin_dir_path( __FILE__ ) .'v2/ResultsController.php';
require_once plugin_dir_path( __FILE__ ) .'v2/RunnerOfTheMonthController.php';
require_once plugin_dir_path( __FILE__ ) .'v2/RunnersController.php';
require_once plugin_dir_path( __FILE__ ) .'v2/StatisticsController.php';
require_once plugin_dir_path( __FILE__ ) .'v2/LeaguesController.php';

require_once plugin_dir_path( __FILE__ ) .'v3/ResultsController.php';
require_once plugin_dir_path( __FILE__ ) .'v3/RunnerOfTheMonthController.php';
require_once plugin_dir_path( __FILE__ ) .'v3/AdminController.php';

// hook into the rest_api_init action so we can start registering routes
$namespace = 'ipswich-jaffa-api/v2'; // base endpoint for our custom API

// Create just one DB connection. Previously load was causing failure when multiple API calls were requested.
$resultsDb = new \wpdb(JAFFA_RESULTS_DB_USER, JAFFA_RESULTS_DB_PASSWORD, JAFFA_RESULTS_DB_NAME, DB_HOST);	

$eventsController = new IpswichJAFFARunningClubAPI\V2\EventsController($namespace, $resultsDb);
$distancesController = new IpswichJAFFARunningClubAPI\V2\DistancesController($namespace, $resultsDb);
$meetingsController = new IpswichJAFFARunningClubAPI\V2\MeetingsController($namespace, $resultsDb);
$racesController = new IpswichJAFFARunningClubAPI\V2\RacesController($namespace, $resultsDb);
$resultsController = new IpswichJAFFARunningClubAPI\V2\ResultsController($namespace, $resultsDb);
$runnerOfTheMonthController = new IpswichJAFFARunningClubAPI\V2\RunnerOfTheMonthController($namespace, $resultsDb);
$runnersController = new IpswichJAFFARunningClubAPI\V2\RunnersController($namespace, $resultsDb);
$statisticsController = new IpswichJAFFARunningClubAPI\V2\StatisticsController($namespace, $resultsDb);
$leaguesController = new IpswichJAFFARunningClubAPI\V2\LeaguesController($namespace, $resultsDb);

$namespaceV3 = 'ipswich-jaffa-api/v3'; 
$adminV3Controller = new IpswichJAFFARunningClubAPI\V3\AdminController($namespaceV3, $resultsDb);
$resultsV3Controller = new IpswichJAFFARunningClubAPI\V3\ResultsController($namespaceV3, $resultsDb);
$runnerOfTheMonthV3Controller = new IpswichJAFFARunningClubAPI\V3\RunnerOfTheMonthController($namespaceV3, $resultsDb);

$helper = new IpswichJAFFARunningClubAPI\WordPressApiHelper();

	

add_action( 'rest_api_init', array( $eventsController, 'registerRoutes') );
add_action( 'rest_api_init', array( $distancesController, 'registerRoutes') );
add_action( 'rest_api_init', array( $meetingsController, 'registerRoutes') );
add_action( 'rest_api_init', array( $racesController, 'registerRoutes') );
add_action( 'rest_api_init', array( $resultsController, 'registerRoutes') );
add_action( 'rest_api_init', array( $runnerOfTheMonthController, 'registerRoutes') );
add_action( 'rest_api_init', array( $runnersController, 'registerRoutes') );
add_action( 'rest_api_init', array( $statisticsController, 'registerRoutes') );
add_action( 'rest_api_init', array( $leaguesController, 'registerRoutes') );

add_action( 'rest_api_init', array( $resultsV3Controller, 'registerRoutes') );
add_action( 'rest_api_init', array( $adminV3Controller, 'registerRoutes') );
add_action( 'rest_api_init', array( $runnerOfTheMonthV3Controller, 'registerRoutes') );

// Customise user response for JWT login
add_filter( 'jwt_auth_token_before_dispatch', array($helper, 'custom_wp_user_token_response'), 10, 2);
?>
<?php
/*
Plugin Name: Ipswich JAFFA RC Results WP REST API V4
*/

if (!defined('ABSPATH')) die('restricted access');

define('IPSWICH_JAFFA_API_PLUGIN_PATH', plugin_dir_path(__FILE__));

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'Config.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'WordPressApiHelper.php';

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Categories/CategoriesController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/EventsController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DistancesController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/MeetingsController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/RacesController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/ResultsController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/RunnerOfTheMonthController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/RunnersController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/StatisticsController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/LeaguesController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/TeamResultsController.php';

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'v3/ResultsController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'v3/RunnerOfTheMonthController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'v3/AdminController.php';

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/Meetings/MeetingsController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/Races/RacesController.php';

// Create just one DB connection. Previously load was causing failure when multiple API calls were requested.
$resultsDb = new \wpdb(JAFFA_RESULTS_DB_USER, JAFFA_RESULTS_DB_PASSWORD, JAFFA_RESULTS_DB_NAME, DB_HOST);

$routeV2 = 'ipswich-jaffa-api/v2'; // base endpoint for our custom API
$categoriesController = new IpswichJAFFARunningClubAPI\V2\Categories\CategoriesController($routeV2, $resultsDb);
$courseTypesController = new IpswichJAFFARunningClubAPI\V4\CourseTypes\CourseTypesController($routeV4, $resultsDb);
$distancesController = new IpswichJAFFARunningClubAPI\V2\Distances\DistancesController($routeV2, $resultsDb);
$eventsController = new IpswichJAFFARunningClubAPI\V2\Events\EventsController($routeV2, $resultsDb);
$gendersController = new IpswichJAFFARunningClubAPI\V2\Genders\GendersController($routeV2, $resultsDb);
$leaguesController = new IpswichJAFFARunningClubAPI\V2\Leagues\LeaguesController($routeV2, $resultsDb);
$runnersController = new IpswichJAFFARunningClubAPI\V2\Runners\RunnersController($routeV2, $resultsDb);
$runnerOfTheMonthController = new IpswichJAFFARunningClubAPI\V2\RunnerOfTheMonth\RunnerOfTheMonthController($routeV2, $resultsDb);
$statisticsController = new IpswichJAFFARunningClubAPI\V2\Statistics\StatisticsController($routeV2, $resultsDb);
$teamResultsController = new IpswichJAFFARunningClubAPI\V2\TeamResults\TeamResultsController($routeV2, $resultsDb);

$meetingsController = new IpswichJAFFARunningClubAPI\V2\MeetingsController($routeV2, $resultsDb);
$racesController = new IpswichJAFFARunningClubAPI\V2\RacesController($routeV2, $resultsDb);
$resultsController = new IpswichJAFFARunningClubAPI\V2\ResultsController($routeV2, $resultsDb);


$routeV3 = 'ipswich-jaffa-api/v3';
$adminV3Controller = new IpswichJAFFARunningClubAPI\V3\AdminController($routeV3, $resultsDb);
$resultsV3Controller = new IpswichJAFFARunningClubAPI\V3\ResultsController($routeV3, $resultsDb);
$runnerOfTheMonthV3Controller = new IpswichJAFFARunningClubAPI\V3\RunnerOfTheMonthController($routeV3, $resultsDb);

$routeV4 = 'ipswich-jaffa-api/v4';
$v4MeetingsController = new IpswichJAFFARunningClubAPI\V4\Meetings\MeetingsController($routeV4, $resultsDb);
$v4RacesController = new IpswichJAFFARunningClubAPI\V4\Races\RacesController($routeV4, $resultsDb);

$helper = new IpswichJAFFARunningClubAPI\WordPressApiHelper();

add_action('rest_api_init', array($categoriesController, 'registerRoutes'));
add_action('rest_api_init', array($courseTypesController, 'registerRoutes'));
add_action('rest_api_init', array($distancesController, 'registerRoutes'));
add_action('rest_api_init', array($eventsController, 'registerRoutes'));
add_action('rest_api_init', array($gendersController, 'registerRoutes'));
add_action('rest_api_init', array($leaguesController, 'registerRoutes'));
add_action('rest_api_init', array($runnersController, 'registerRoutes'));
add_action('rest_api_init', array($runnerOfTheMonthController, 'registerRoutes'));
add_action('rest_api_init', array($statisticsController, 'registerRoutes'));
add_action('rest_api_init', array($teamResultsController, 'registerRoutes'));

add_action('rest_api_init', array($meetingsController, 'registerRoutes'));
add_action('rest_api_init', array($racesController, 'registerRoutes'));
add_action('rest_api_init', array($resultsController, 'registerRoutes'));

add_action('rest_api_init', array($resultsV3Controller, 'registerRoutes'));
add_action('rest_api_init', array($adminV3Controller, 'registerRoutes'));
add_action('rest_api_init', array($runnerOfTheMonthV3Controller, 'registerRoutes'));

add_action('rest_api_init', array($v4MeetingsController, 'registerRoutes'));
add_action('rest_api_init', array($v4RacesController, 'registerRoutes'));

// Customise user response for JWT login
add_filter('jwt_auth_token_before_dispatch', array($helper, 'custom_wp_user_token_response'), 10, 2);

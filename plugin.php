<?php
/*
Plugin Name: Ipswich JAFFA RC Results WP REST API
*/

if (!defined('ABSPATH')) die('restricted access');

define('IPSWICH_JAFFA_API_PLUGIN_PATH', plugin_dir_path(__FILE__));

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'Config.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'WordPressApiHelper.php';

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Categories/CategoriesController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/CourseTypes/CourseTypesController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Distances/DistancesController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Events/EventsController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Genders/GendersController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/GrandPrix/GrandPrixController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/HistoricRecords/HistoricRecordsController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Leagues/LeaguesController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Meetings/MeetingsController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Races/RacesController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Rankings/RankingsController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Records/RecordsController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Results/ResultsController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/RunnerResults/RunnerResultsController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Runners/RunnersController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Results/ResultsController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/RunnerOfTheMonth/RunnerOfTheMonthController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Statistics/StatisticsController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/TeamResults/TeamResultsController.php';

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'v3/ResultsController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'v3/RunnerOfTheMonthController.php';

// Create just one DB connection. Previously load was causing failure when multiple API calls were requested.
$resultsDb = new \wpdb(JAFFA_RESULTS_DB_USER, JAFFA_RESULTS_DB_PASSWORD, JAFFA_RESULTS_DB_NAME, DB_HOST);

$routeV2 = 'ipswich-jaffa-api/v2'; // base endpoint for our custom API
$categoriesController = new IpswichJAFFARunningClubAPI\V2\Categories\CategoriesController($routeV2, $resultsDb);
$courseTypesController = new IpswichJAFFARunningClubAPI\V2\CourseTypes\CourseTypesController($routeV2, $resultsDb);
$distancesController = new IpswichJAFFARunningClubAPI\V2\Distances\DistancesController($routeV2, $resultsDb);
$eventsController = new IpswichJAFFARunningClubAPI\V2\Events\EventsController($routeV2, $resultsDb);
$gendersController = new IpswichJAFFARunningClubAPI\V2\Genders\GendersController($routeV2, $resultsDb);
$grandPrixController = new IpswichJAFFARunningClubAPI\V2\GrandPrix\GrandPrixController($routeV2, $resultsDb);
$historicRecordsController = new IpswichJAFFARunningClubAPI\V2\HistoricRecords\HistoricRecordsController($routeV2, $resultsDb);
$leaguesController = new IpswichJAFFARunningClubAPI\V2\Leagues\LeaguesController($routeV2, $resultsDb);
$meetingsController = new IpswichJAFFARunningClubAPI\V2\Meetings\MeetingsController($routeV2, $resultsDb);
$racesController = new IpswichJAFFARunningClubAPI\V2\Races\RacesController($routeV2, $resultsDb);
$rankingsController = new IpswichJAFFARunningClubAPI\V2\Rankings\RankingsController($routeV2, $resultsDb);
$recordsController = new IpswichJAFFARunningClubAPI\V2\Records\RecordsController($routeV2, $resultsDb);
$resultsController = new IpswichJAFFARunningClubAPI\V2\Results\ResultsController($routeV2, $resultsDb);
$runnerResultsController = new IpswichJAFFARunningClubAPI\V2\RunnerResults\RunnerResultsController($routeV2, $resultsDb);
$runnersController = new IpswichJAFFARunningClubAPI\V2\Runners\RunnersController($routeV2, $resultsDb);
$runnerOfTheMonthController = new IpswichJAFFARunningClubAPI\V2\RunnerOfTheMonth\RunnerOfTheMonthController($routeV2, $resultsDb);
$statisticsController = new IpswichJAFFARunningClubAPI\V2\Statistics\StatisticsController($routeV2, $resultsDb);
$teamResultsController = new IpswichJAFFARunningClubAPI\V2\TeamResults\TeamResultsController($routeV2, $resultsDb);

$routeV3 = 'ipswich-jaffa-api/v3';
$resultsV3Controller = new IpswichJAFFARunningClubAPI\V3\ResultsController($routeV3, $resultsDb);
$runnerOfTheMonthV3Controller = new IpswichJAFFARunningClubAPI\V3\RunnerOfTheMonthController($routeV3, $resultsDb);

$helper = new IpswichJAFFARunningClubAPI\WordPressApiHelper();

add_action('rest_api_init', array($categoriesController, 'registerRoutes'));
add_action('rest_api_init', array($courseTypesController, 'registerRoutes'));
add_action('rest_api_init', array($distancesController, 'registerRoutes'));
add_action('rest_api_init', array($eventsController, 'registerRoutes'));
add_action('rest_api_init', array($gendersController, 'registerRoutes'));
add_action('rest_api_init', array($grandPrixController, 'registerRoutes'));
add_action('rest_api_init', array($historicRecordsController, 'registerRoutes'));
add_action('rest_api_init', array($leaguesController, 'registerRoutes'));
add_action('rest_api_init', array($meetingsController, 'registerRoutes'));
add_action('rest_api_init', array($racesController, 'registerRoutes'));
add_action('rest_api_init', array($rankingsController, 'registerRoutes'));
add_action('rest_api_init', array($recordsController, 'registerRoutes'));
add_action('rest_api_init', array($resultsController, 'registerRoutes'));
add_action('rest_api_init', array($runnersController, 'registerRoutes'));
add_action('rest_api_init', array($runnerResultsController, 'registerRoutes'));
add_action('rest_api_init', array($runnerOfTheMonthController, 'registerRoutes'));
add_action('rest_api_init', array($statisticsController, 'registerRoutes'));
add_action('rest_api_init', array($teamResultsController, 'registerRoutes'));

add_action('rest_api_init', array($resultsV3Controller, 'registerRoutes'));
add_action('rest_api_init', array($runnerOfTheMonthV3Controller, 'registerRoutes'));

// Customise user response for JWT login
add_filter('jwt_auth_token_before_dispatch', array($helper, 'custom_wp_user_token_response'), 10, 2);

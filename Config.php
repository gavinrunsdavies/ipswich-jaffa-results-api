<?php
/**
 * The base configurations of the WordPress.
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //

/** The name of the database for WordPress */
define('JAFFA_RESULTS_DB_NAME', '***');

/** MySQL database username */
define('JAFFA_RESULTS_DB_USER', '***');

/** MySQL database password */
define('JAFFA_RESULTS_DB_PASSWORD', '***');

define('JAFFA_RESULTS_UkAthleticsWebAccessKey', '***');
define('JAFFA_RESULTS_UkAthleticsLicenceCheckUrl', '***');

define('OPEN_AI_API_HISTORIC_RACE_RESULTS_SECRET', getenv('OPENAI_API_HISTORIC_RACE_RESULTS'));

?>

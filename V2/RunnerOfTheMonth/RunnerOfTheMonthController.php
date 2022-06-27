<?php

namespace IpswichJAFFARunningClubAPI\V2\RunnerOfTheMonth;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Genders/Genders.php';
require_once 'RunnerOfTheMonthCommand.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class RunnerOfTheMonthController extends BaseController implements IRoute
{
	const MENS_CATEGORY = 'Mens';
	const LADIES_CATEGORY = 'Ladies';

	public function __construct(string $route, $db)
	{
		parent::__construct($route, new RunnerOfTheMonthCommand($db));
	}

	public function registerRoutes()
	{
		register_rest_route($this->route, '/runnerofthemonth', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this->command, 'saveWinners')
		));

		register_rest_route($this->route, '/runnerofthemonth/vote', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array($this->command, 'saveRunnerOfTheMonthVote')
		));

		register_rest_route($this->route, '/runnerofthemonth/resultsvote/(?P<resultId>[\d]+)', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array($this->command, 'saveRunnerOfTheMonthResultVote'),
			'args'                => array(
				'resultId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		register_rest_route($this->route, '/runnerofthemonth/winners', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getRunnerOfTheMonthWinners')
		));

		register_rest_route($this->route, '/runnerofthemonth/winners/year/(?P<year>[\d]+)/month/(?P<month>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getRunnerOfTheMonthWinners'),
			'args'                => array(
				'year'           => array(
					'required'          => true
				),
				'month'           => array(
					'required'          => true
				)
			)
		));
	}
}

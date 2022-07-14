<?php

namespace IpswichJAFFARunningClubAPI\V2\Statistics;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'StatisticsCommand.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class StatisticsController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new StatisticsCommand($db));
	}

	public function registerRoutes()
	{
		register_rest_route($this->route, '/statistics/type/(?P<typeId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getStatistics'),
			'args'                => array(
				'typeId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		register_rest_route($this->route, '/statistics/results/runner/year/(?P<year>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getResultCountByRunnerByYear'),
			'args'                => array(
				'year'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		register_rest_route($this->route, '/statistics/results/runner', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getResultCountByRunnerByYear'),
			'args'                => array()
		));

		register_rest_route($this->route, '/statistics/clubresults', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getClubResults'),
			'args'                => array()
		));

		register_rest_route($this->route, '/statistics/groupedrunnerresultscount', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getGroupedRunnerResultsCount'),
			'args'                => array()
		));

		register_rest_route($this->route, '/statistics/meanPercentageGradingByMonth', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getMeanPercentageGradingByMonth'),
			'args'                => array()
		));
	}

}

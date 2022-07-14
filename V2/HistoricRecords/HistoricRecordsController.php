<?php

namespace IpswichJAFFARunningClubAPI\V2\HistoricRecords;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'HistoricRecordsCommand.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class HistoricRecordsController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new HistoricRecordsCommand($db));
	}

	public function registerRoutes()
	{
		register_rest_route($this->route, '/results/historicrecords/distance/(?P<distanceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getHistoricClubRecords'),
			'args'                => array(
				'distanceId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		register_rest_route($this->route, '/results/historicrecords/category/(?P<categoryId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getHistoricClubRecordsByCategory'),
			'args'                => array(
				'categoryId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));
	}
}

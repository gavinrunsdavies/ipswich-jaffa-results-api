<?php

namespace IpswichJAFFARunningClubAPI\V2\Records;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'RecordsCommand.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class RecordsController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new RecordsCommand($db));
	}

	public function registerRoutes()
	{
		register_rest_route($this->route, '/results/records', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getOverallClubRecords')
		));

		register_rest_route($this->route, '/results/records/holders', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getTopClubRecordHolders')
		));

		register_rest_route($this->route, '/results/records/distance/(?P<distanceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getClubRecords'),
			'args'                => array(
				'distanceId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));
	}
}

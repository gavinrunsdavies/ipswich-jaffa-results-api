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
			'callback'            => array($this, 'getOverallClubRecords')
		));

		register_rest_route($this->route, '/results/records/holders', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getTopClubRecordHolders')
		));

		register_rest_route($this->route, '/results/records/distance/(?P<distanceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getClubRecords'),
			'args'                => array(
				'distanceId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));
	}

	public function getOverallClubRecords(\WP_REST_Request $request)
	{
		$parameters = $request->get_query_params();
		$response = $this->command->getOverallClubRecords($parameters['distanceIds']);

		return rest_ensure_response($response);
	}

	public function getClubRecords(\WP_REST_Request $request)
	{
		$response = $this->command->getClubRecords($request['distanceId']);

		return rest_ensure_response($response);
	}

	public function getClubRecordsCountByRunner(\WP_REST_Request $request)
	{
		$response = $this->command->getClubRecordsCountByRunner();

		return rest_ensure_response($response);
	}

	public function getTopClubRecordHolders(\WP_REST_Request $request)
	{
		$parameters = $request->get_query_params();
		$limit = $parameters['limit'] ?? 3;
		$response = $this->command->getTopClubRecordHolders($limit);

		return rest_ensure_response($response);
	}
}

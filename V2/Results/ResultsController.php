<?php

namespace IpswichJAFFARunningClubAPI\V2\Results;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'ResultsCommand.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class ResultsController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new ResultsCommand($db));
	}

	public function registerRoutes()
	{
		register_rest_route($this->route, '/results', array(
			'methods'             => \WP_REST_Server::READABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this, 'getResults')
		));

		register_rest_route($this->route, '/results', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this, 'saveResult'),
			'args'                => array(
				'result'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'validateResult'),
				)
			)
		));

		register_rest_route($this->route, '/results/(?P<resultId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array($this, 'deleteResult'),
			'permission_callback' => array($this, 'isAuthorized'),
			'args'                => array(
				'resultId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				)
			)
		));

		register_rest_route($this->route, '/results/(?P<resultId>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this, 'updateResult'),
			'args'                => array(
				'resultId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidResultUpdateField')
				),
				'value'           => array(
					'required'          => true
				)
			)
		));

		register_rest_route($this->route, '/results/updateAgeGrading', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this, 'updateAgeGrading')
		));

		// The following may belong in their own controllers	
		register_rest_route( $this->route, '/results/race/(?P<raceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getRaceResults' ),
			'args'                => array(
				'raceId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)
		) );
    
        register_rest_route( $this->route, '/results/county', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getCountyChampions' )
		) );
	}

	public function getResults(\WP_REST_Request $request)
	{
		// TODO, eventID, fromDate, toDate and limit. All optional.
		// Sanitization needed before
		$parameters = $request->get_query_params();
		$response = $this->command->getResults($parameters['eventId'], $parameters['fromDate'], $parameters['toDate'], $parameters['numberOfResults']);

		return rest_ensure_response($response);
	}

	public function saveResult(\WP_REST_Request $request)
	{
		$response = $this->command->insertResult($request['result']);

		return rest_ensure_response($response);
	}

	public function deleteResult(\WP_REST_Request $request)
	{
		$response = $this->command->deleteResult($request['resultId']);

		return rest_ensure_response($response);
	}

	public function updateResult(\WP_REST_Request $request)
	{
		$response = $this->command->updateResult($request['resultId'], $request['field'], $request['value']);

		return rest_ensure_response($response);
	}

	public function getRaceResults(\WP_REST_Request $request)
	{
		$response = $this->command->getRaceResults($request['raceId']);

		return rest_ensure_response($response);
	}

	public function getCountyChampions(\WP_REST_Request $request)
	{
		$response = $this->command->getCountyChampions();

		return rest_ensure_response($response);
	}

	public function updateAgeGrading(\WP_REST_Request $request)
	{
		$parameters = $request->get_query_params();
		$response = $this->command->updateAgeGrading($parameters['fromDate'], $parameters['toDate']);

		return rest_ensure_response($response);
	}
	
	public function validateResult($result, $request, string $key)
	{
		$invalid = false;
		if (
			intval($result['raceId']) < 1 ||
			intval($result['runnerId']) < 1 ||
			intval($result['position']) < 0 ||
			intval($result['team']) < 0
		) {
			$invalid = true;
		}

		if (!$invalid) {
			$date = date_parse($result['date']);
			if (checkdate($date['month'], $date['day'], $date['year']) === FALSE) {
				$invalid = true;
			}
		}

		if (!$invalid && strpos($result['result'], ':') !== false) {
			$time = explode(":", $result['result']);

			if ($time[0] < 0 || $time[1] < 0 || $time[2] < 0 || $time[1] > 60 || $time[2] > 60) {
				$invalid = true;
			} 
		} 		

		if ($invalid) {
			return new \WP_Error(
				'rest_invalid_param',
				sprintf('%s %s has invalid values', $key, json_encode($result)),
				array('status' => 400)
			);
		}

		return true;
	}

	public function isValidResultUpdateField(string $value, $request, string $key){
		if ( $value == 'info' || $value == 'position' || $value == 'result' || $value == 'scoring_team' || $value == 'race_id') {
			return true;
		} else {
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %s must be info, position, result, scoring_team, race_id only.', $key, $value ), array( 'status' => 400 ) );
		} 			
	}	
}

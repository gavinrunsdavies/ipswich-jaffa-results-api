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
			'callback'            => array($this->command, 'getResults')
		));

		register_rest_route($this->route, '/results', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this->command, 'saveResult'),
			'args'                => array(
				'result'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'validateResult'),
				)
			)
		));

		register_rest_route($this->route, '/results/(?P<resultId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array($this->command, 'deleteResult'),
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
			'callback'            => array($this->command, 'updateResult'),
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

		// The following may belong in their own controllers	
		register_rest_route( $this->route, '/results/race/(?P<raceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this->command, 'getRaceResults' ),
			'args'                => array(
				'raceId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)
		) );
    
        register_rest_route( $this->route, '/results/county', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this->command, 'getCountyChampions' )
		) );
	}
	
	public function validateResult($result, $request, string $key)
	{
		$invalid = false;
		if (
			intval($result['raceId']) < 1 ||
			intval($result['runnerId']) < 1 ||
			intval($result['position']) < 0 ||
			intval($result['team']) < 0 ||
			$result['isGrandPrixResult'] < 0 ||
			$result['isGrandPrixResult'] > 1
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

			if ($time[0] < 0 || $time[1] < 0 || $time[2] < 0 || $time[1] > 59 || $time[2] > 59) {
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
				sprintf( '%s %s must be info or position or result or grandprix or scoring_team only.', $key, $value ), array( 'status' => 400 ) );
		} 			
	}	
}

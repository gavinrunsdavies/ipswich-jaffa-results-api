<?php

namespace IpswichJAFFARunningClubAPI\V2\RunnerResults;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'RunnerResultsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class RunnerResultsController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new RunnerResultsDataAccess($db));
	}

	public function registerRoutes()
	{
		register_rest_route( $this->namespace, '/results/runner/(?P<runnerId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getMemberResults' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)		
		) );
		
		register_rest_route( $this->namespace, '/results/runner/(?P<runnerId>[\d]+)/personalbests', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getMemberPBResults' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)		
		) );
		
		register_rest_route( $this->namespace, '/results/runner/(?P<runnerId>[\d]+)/insights/distance/(?P<distanceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getMemberInsightsRaceDistance' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					),
				'distanceId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)		
		) );
		
		register_rest_route( $this->namespace, '/results/runner/(?P<runnerId>[\d]+)/insights/numberOfRaces', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getMemberInsightsNumberOfRaces' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)	
			)
		) );
		
		register_rest_route( $this->namespace, '/results/runner/(?P<runnerId>[\d]+)/insights/totalRaceTime', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getMemberInsightsTotalRaceTime' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)	
				)
		) );
							
		register_rest_route( $this->namespace, '/results/runner/compare', array(
			'methods'             => \WP_REST_Server::CREATABLE,				
			'callback'            => array( $this, 'compareMemberRaces' ),
			'args'                => array(
				'runnerIds'           => array(
					'required'          => true
					)
				)		
		) );
	}

	public function getMemberResults( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getMemberResults($request['runnerId']);

		return rest_ensure_response( $response );
	}
	
	public function getMemberPBResults( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getMemberPBResults($request['runnerId']);

		return rest_ensure_response( $response );
	}
	
	public function compareMemberRaces( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getHeadToHeadResults($request['runnerIds']);

		return rest_ensure_response( $response );
	}

	public function getMemberInsightsRaceDistance( \WP_REST_Request $request ) {
		$raceTimes = $this->dataAccess->getMemberInsightsRaceDistance($request['distanceId']);

		$memberTotals = $this->dataAccess->getRunnerDistanceResultMinMaxAverage($request['runnerId'], $request['distanceId']);

		$response = array();
		$response['raceTimes'] = $raceTimes;
		$response['slowest'] = $memberTotals->slowest;
		$response['fastest'] = $memberTotals->fastest;
		$response['mean'] = $memberTotals->mean;
		return rest_ensure_response( $response );
	}

	public function getMemberInsightsNumberOfRaces( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getMemberInsightsNumberOfRaces($request['runnerId']);

		return rest_ensure_response( $response );
	}
							
	public function getMemberInsightsTotalRaceTime( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getMemberInsightsTotalRaceTime($request['runnerId']);

		return rest_ensure_response( $response );
	}
}

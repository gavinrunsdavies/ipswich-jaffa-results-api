<?php

namespace IpswichJAFFARunningClubAPI\V2\Rankings;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'RankingsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class RankingsController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new RankingsDataAccess($db));
	}

	public function registerRoutes()
	{
		register_rest_route( $this->namespace, '/results/ranking/distance/(?P<distanceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getResultRankings' ),
			'args'                => array(
				'distanceId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)		
		) );
		
		register_rest_route( $this->namespace, '/results/ranking/averageWMA', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getAveragePercentageRankings' )
		) );
		
		register_rest_route( $this->namespace, '/results/ranking/wma', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getWMAPercentageRankings' )				
		) );
	}

	public function getResultRankings( \WP_REST_Request $request ) {
		$parameters = $request->get_query_params();			
		$response = $this->dataAccess->getResultRankings($request['distanceId'], $parameters['year'], $parameters['sexId'], $parameters['categoryId']);

		return rest_ensure_response( $response );
	}
	
	public function getWMAPercentageRankings( \WP_REST_Request $request ) {
		$parameters = $request->get_query_params();			
		$response = $this->dataAccess->getWMAPercentageRankings($parameters['sexId'], $parameters['distanceId'], $parameters['year'], $parameters['distinct']);

		return rest_ensure_response( $response );
	}
	
	public function getAveragePercentageRankings( \WP_REST_Request $request ) {
		$parameters = $request->get_query_params();			
		$response = $this->dataAccess->getAveragePercentageRankings($parameters['sexId'], $parameters['year'], $parameters['numberOfRaces']);

		return rest_ensure_response( $response );
	}
}

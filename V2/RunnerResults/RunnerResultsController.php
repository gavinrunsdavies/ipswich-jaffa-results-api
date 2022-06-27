<?php

namespace IpswichJAFFARunningClubAPI\V2\RunnerResults;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'RunnerResultsCommand.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class RunnerResultsController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new RunnerResultsCommand($db));
	}

	public function registerRoutes()
	{
		register_rest_route( $this->route, '/results/runner/(?P<runnerId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this->command, 'getMemberResults' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)		
		) );
		
		register_rest_route( $this->route, '/results/runner/(?P<runnerId>[\d]+)/personalbests', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this->command, 'getMemberPBResults' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)		
		) );
		
		register_rest_route( $this->route, '/results/runner/(?P<runnerId>[\d]+)/insights/distance/(?P<distanceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this->command, 'getMemberInsightsRaceDistance' ),
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
		
		register_rest_route( $this->route, '/results/runner/(?P<runnerId>[\d]+)/insights/numberOfRaces', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this->command, 'getMemberInsightsNumberOfRaces' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)	
			)
		) );
		
		register_rest_route( $this->route, '/results/runner/(?P<runnerId>[\d]+)/insights/totalRaceTime', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this->command, 'getMemberInsightsTotalRaceTime' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)	
				)
		) );
							
		register_rest_route( $this->route, '/results/runner/compare', array(
			'methods'             => \WP_REST_Server::CREATABLE,				
			'callback'            => array( $this->command, 'compareMemberRaces' ),
			'args'                => array(
				'runnerIds'           => array(
					'required'          => true
					)
				)		
		) );
	}
}

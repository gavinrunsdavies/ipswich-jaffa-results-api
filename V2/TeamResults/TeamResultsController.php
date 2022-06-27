<?php
namespace IpswichJAFFARunningClubAPI\V2\TeamResults;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH .'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH .'V2/IRoute.php';
require_once 'TeamResultsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;
	
class TeamResultsController extends BaseController implements IRoute {			
	
	public function __construct(string $route, $db) 
	{        
		parent::__construct($route, new TeamResultsDataAccess($db));
	}
	
	public function registerRoutes() 
	{										
		register_rest_route( $this->route, '/team-results/(?P<teamResultId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this->command, 'getTeamResult' ),
			'args'                => array(
				'teamResultId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId' ),
					),
				)
		) );		
		
		register_rest_route( $this->route, '/team-results', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this->command, 'saveTeamResult' ),				
			'args'                => array(
				'name'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isNotNull' )
					),
				'meetingId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isNotNull' )
					),
				'resultIds'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isNotNull' )
					)
				)
		) );
		
		register_rest_route( $this->route, '/team-results/(?P<teamResultId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this->command, 'deleteTeamResult' ),
			'permission_callback' => array( $this, 'isAuthorized' ),
			'args'                => array(
				'teamResultId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)
		) );
	}	
}

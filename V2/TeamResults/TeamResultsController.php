<?php
namespace IpswichJAFFARunningClubAPI\V2\TeamResults;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH .'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH .'V2/IRoute.php';
require_once 'TeamResultsCommand.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;
	
class TeamResultsController extends BaseController implements IRoute {			
	
	public function __construct(string $route, $db) 
	{        
		parent::__construct($route, new TeamResultsCommand($db));
	}
	
	public function registerRoutes() 
	{										
		register_rest_route( $this->route, '/team-results/(?P<teamResultId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getTeamResult' ),
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
			'callback'            => array( $this, 'saveTeamResult' ),				
			'args'                => array(
				'name'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isNotNull' )
					),
				'category'           => array(
					'required'          => false
					),
				'result'           => array(
					'required'          => false
					),
				'position'           => array(
					'required'          => false,												
					'validate_callback' => array( $this, 'isValidId' )
					),
				'meetingId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId' )
					),
				'resultIds'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isNotNull' )
					)
				)
		) );
		
		register_rest_route( $this->route, '/team-results/(?P<teamResultId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'deleteTeamResult' ),
			'permission_callback' => array( $this, 'isAuthorized' ),
			'args'                => array(
				'teamResultId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)
		) );
	}
	
	public function getTeamResult( \WP_REST_Request $request ) {
	
		return rest_ensure_response($this->command->getTeamResult($request['teamResultId']));
	}

	public function saveTeamResult( \WP_REST_Request $request ) {

		return rest_ensure_response($this->command->saveTeamResult(
			$request['name'],
			$request['category'],
			$request['result'], 
			$request['position'],
			$request['meetingId'],
			$request['resultIds']
		));		
	}

	public function deleteTeamResult( \WP_REST_Request $request ) {
		
		return rest_ensure_response($this->command->deleteTeamResult($request['teamResultId']));
	}
}

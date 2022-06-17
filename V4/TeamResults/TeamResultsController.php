<?php
namespace IpswichJAFFARunningClubAPI\V4\TeamResults;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH .'V4/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH .'V4/IRoute.php';
require_once 'TeamResultsDataAccess.php';

use IpswichJAFFARunningClubAPI\V4\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V4\IRoute as IRoute;
	
class TeamResultsController extends BaseController implements IRoute {			
	
	public function __construct(string $route, $db) 
	{        
		parent::__construct($route, new TeamResultsDataAccess($db));
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
	
		$response = $this->dataAccess->getTeamResult($request['teamResultId']);
		
		return $this->processDataResponse($response, function($response) {
			return $response;
		});	
	}

	public function saveTeamResult( \WP_REST_Request $request ) {

		$response = $this->dataAccess->insertTeamResult($request);
		
		return $this->processDataResponse($response, function($response) {
			return $response;
		});	
	}

	public function deleteTeamResult( \WP_REST_Request $request ) {
		
		$response = $this->dataAccess->deleteTeamResult($request['teamResultId']);
		
		return $this->processDataResponse($response, function($response) {
			return $response;
		});	
	}
}
?>
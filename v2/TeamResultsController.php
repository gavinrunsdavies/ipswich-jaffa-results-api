<?php
namespace IpswichJAFFARunningClubAPI\V2;

require_once plugin_dir_path( __FILE__ ) .'BaseController.php';
require_once plugin_dir_path( __FILE__ ) .'IRoute.php';
	
class TeamResultsController extends BaseController implements IRoute {			
	
	public function __construct($namespace, $db) {        
		parent::__construct($namespace, $db);
	}
	
	public function registerRoutes() {										
		
		register_rest_route( $this->namespace, '/team-results/(?P<teamResultId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getTeamResult' ),
			'args'                => array(
				'teamResultId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId' ),
					),
				)
		) );		
		
		register_rest_route( $this->namespace, '/team-results', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'saveTeamResult' ),				
			'args'                => array(
				'name'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validateTeamResult' )
					),
				'meetingId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validateTeamResult' )
					),
				'resultIds'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validateTeamResult' )
					)
				)
		) );
		
		register_rest_route( $this->namespace, '/team-results/(?P<teamResultId>[\d]+)', array(
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
		
		return rest_ensure_response( $response );
	}

	public function saveTeamResult( \WP_REST_Request $request ) {

		$response = $this->dataAccess->insertTeamResult($request);
		
		return rest_ensure_response( $response );
	}

	/*public function updateTeamResult( \WP_REST_Request $request ) {

		$response = $this->dataAccess->updateTeamResultId($request['team-result']);
		
		return rest_ensure_response( $response );
	}*/

	public function deleteTeamResult( \WP_REST_Request $request ) {
		$parameters = $request->get_query_params();	

		$response = $this->dataAccess->deleteTeamResult($request['teamResultId']);
		
		return rest_ensure_response( $response );
	}

	public function validateTeamResult($value, $request, $key){
		if ( $value != null ) {
			return true;
		} else {
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %d invalid.', $key, $value ), array( 'status' => 400 ) );
		} 			
	}
}
?>
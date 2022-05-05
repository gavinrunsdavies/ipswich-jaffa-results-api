<?php
namespace IpswichJAFFARunningClubAPI\V2;

require_once plugin_dir_path( __FILE__ ) .'BaseController.php';
require_once plugin_dir_path( __FILE__ ) .'IRoute.php';
	
class LeaguesController extends BaseController implements IRoute {			
	
	public function registerRoutes() {										
		register_rest_route( $this->namespace, '/leagues', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getLeagues' )
		) );
		
		register_rest_route( $this->namespace, '/leagues/(?P<leagueId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getLeague' ),
			'args'                => array(
				'leagueId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId' ),
					),
				)
		) );		
		
		register_rest_route( $this->namespace, '/leagues', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'saveLeague' ),				
			'args'                => array(
				'league'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validateLeague' ),
					),
				)
		) );
		
		// Patch - updates
		register_rest_route( $this->namespace, '/leagues/(?P<leagueId>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'updateLeague' ),
			'args'                => array(
				'leagueId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array( $this, 'isValidLeagueUpdateField' )
					),
				'value'           => array(
					'required'          => true
					)
				)				
		) );
		
		register_rest_route( $this->namespace, '/leagues/(?P<leagueId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'deleteLeague' ),
			'permission_callback' => array( $this, 'isAuthorized' ),
			'args'                => array(
				'leagueId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)
		) );
	}	

	public function getLeagues( \WP_REST_Request $request ) {

		$response = $this->dataAccess->getLeagues();
		
		return rest_ensure_response( $response );
	}

	public function getLeague( \WP_REST_Request $request ) {
	
		$response = $this->dataAccess->getLeague($request['leagueId']);
		
		return rest_ensure_response( $response );
	}

	public function saveLeague( \WP_REST_Request $request ) {

		$response = $this->dataAccess->insertLeague($request['league']);
		
		return rest_ensure_response( $response );
	}

	public function updateLeague( \WP_REST_Request $request ) {

		$response = $this->dataAccess->updateLeague($request['leagueId'], $request['field'], $request['value']);
		
		return rest_ensure_response( $response );
	}

	public function deleteLeague( \WP_REST_Request $request ) {
		$parameters = $request->get_query_params();	

		$response = $this->dataAccess->deleteLeague($request['leagueId'], $parameters['deleteRaceAssociations']);
		
		return rest_ensure_response( $response );
	}

	public function isValidLeagueUpdateField($value, $request, $key){
		if ( $value == 'name' ) {
			return true;
		} else {
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %d must be name only.', $key, $value ), array( 'status' => 400 ) );
		} 			
	}

	public function validateLeague($value, $request, $key){
		if ( $value != null ) {
			return true;
		} else {
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %d invalid.', $key, $value ), array( 'status' => 400 ) );
		} 			
	}
}
?>
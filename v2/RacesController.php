<?php
namespace IpswichJAFFARunningClubAPI\V2;

require_once plugin_dir_path( __FILE__ ) .'BaseController.php';
require_once plugin_dir_path( __FILE__ ) .'IRoute.php';
	
class RacesController extends BaseController implements IRoute {			
	
	public function __construct($namespace, $db) {        
		parent::__construct($namespace, $db);
	}
	
	public function registerRoutes() {										
		
		register_rest_route( $this->namespace, '/events/(?P<eventId>[\d]+)/races', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getRaces' ),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId' ),
					),
				)
		) );			
		
		register_rest_route( $this->namespace, '/races', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'saveRace' ),				
			'args'                => array(
				'race'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validateRace' ),
					),
				)
		) );						
		
		register_rest_route( $this->namespace, '/races/(?P<id>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getRace' ),				
			'args'                 => array(
				'id'           => array(
					'required'          => true,	
					'validate_callback' => array( $this, 'isValidId' )
					)
				)
		) );	

		register_rest_route( $this->namespace, '/races/(?P<id>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'updateRace' ),
			'args'                => array(
				'id'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array( $this, 'isValidRaceUpdateField' )
					),
				'value'           => array(
					'required'          => true
					)
				)				
		) );

		// TODO. Why is this route different? It includes Events resource.
		register_rest_route( $this->namespace, '/events/(?P<eventId>[\d]+)/race/(?P<raceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'deleteRace' ),
			'permission_callback' => array( $this, 'isAuthorized' ),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					),
				'raceId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId' ),
					),
				)
		) );		
	}	

	public function getRaces( \WP_REST_Request $request ) {
	
		$response = $this->dataAccess->getRaces($request['eventId']);
		
		return rest_ensure_response( $response );
	}

	public function saveRace( \WP_REST_Request $request ) {

		$response = $this->dataAccess->insertRace($request['race']);
		
		return rest_ensure_response( $response );
	}

	public function getRace( \WP_REST_Request $request ) {

		$response = $this->dataAccess->getRace($request['id']);
		
		return rest_ensure_response( $response );
	}

	public function updateRace( \WP_REST_Request $request ) {

		if ($request['field'] == "distance_id") {
			$response = $this->dataAccess->updateRaceDistance($request['id'], $request['value']);
		} else {
			$response = $this->dataAccess->updateRace($request['id'], $request['field'], $request['value']);
		}
		
		return rest_ensure_response( $response );
	}
	
	public function deleteRace( \WP_REST_Request $request ) {
		
		$response = $this->dataAccess->deleteRace($request['raceId'], false);
		
		return rest_ensure_response( $response );
	}

    public function isValidRaceUpdateField($value, $request, $key) {
		if ( $value == 'event_id' || 
			$value == 'description' || 
			$value == 'course_type_id' || 
			$value == 'course_number' || 
			$value == 'area' || 
			$value == 'county' ||
			$value == 'country_code' || 
			$value == 'venue' || 
			$value == 'distance_id' || 
			$value == 'conditions' || 
			$value == 'meeting_id' || 
			$value == 'league_id' || 
			$value == 'grand_prix' ) {
			return true;
		} else {
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %s has an invalid value.', $key, $value ), array( 'status' => 400) );
		} 			
	}
}
?>
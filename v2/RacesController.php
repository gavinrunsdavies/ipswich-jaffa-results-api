<?php
namespace IpswichJAFFARunningClubAPI\V2;

require_once plugin_dir_path( __FILE__ ) .'BaseController.php';
require_once plugin_dir_path( __FILE__ ) .'IRoute.php';
	
class RacesController extends BaseController implements IRoute {			
	
	public function __construct($namespace) {        
		parent::__construct($namespace);
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

	private function getRaces( \WP_REST_Request $request ) {
	
		$response = $this->dataAccess->getRaces($request['eventId']);
		
		return rest_ensure_response( $response );
	}

	private function saveRace( \WP_REST_Request $request ) {

		$response = $this->dataAccess->insertRace($request['race']);
		
		return rest_ensure_response( $response );
	}

	private function getRace( \WP_REST_Request $request ) {

		$response = $this->dataAccess->getRace($request['id']);
		
		return rest_ensure_response( $response );
	}

	private function updateRace( \WP_REST_Request $request ) {

		if ($request['field'] == "distance_id") {
			$response = $this->dataAccess->updateRaceDistance($request['id'], $request['value']);
		} else {
			$response = $this->dataAccess->updateRace($request['id'], $request['field'], $request['value']);
		}
		
		return rest_ensure_response( $response );
	}
	
	private function deleteRace( \WP_REST_Request $request ) {
		
		$response = $this->dataAccess->deleteRace($request['raceId'], false);
		
		return rest_ensure_response( $response );
	}
}
?>
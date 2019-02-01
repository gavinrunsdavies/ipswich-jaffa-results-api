<?php
namespace IpswichJAFFARunningClubAPI\V2;

require_once plugin_dir_path( __FILE__ ) .'BaseController.php';
require_once plugin_dir_path( __FILE__ ) .'IRoute.php';
	
class EventsController extends BaseController implements IRoute {			
	
	public function __construct($namespace) {        
		parent::__construct($namespace);
	}
	
	public function registerRoutes() {										
		register_rest_route( $this->namespace, '/events', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getEvents' )
		) );
		
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

		register_rest_route( $this->namespace, '/coursetypes', array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'isAuthorized' ),
				'callback'            => array( $this, 'getCourseTypes' )
			) );			
		
		register_rest_route( $this->namespace, '/events', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'saveEvent' ),				
			'args'                => array(
				'event'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validateEvent' ),
					),
				)
		) );
		
		// Patch - updates
		register_rest_route( $this->namespace, '/events/(?P<eventId>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'updateEvent' ),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array( $this, 'isValidEventUpdateField' )
					),
				'value'           => array(
					'required'          => true
					)
				)				
		) );
		
		register_rest_route( $this->namespace, '/events/merge', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'mergeEvents' ),
			'args'                => array(
				'fromEventId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId' )
					),
				'toEventId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)					
		) );
		
		register_rest_route( $this->namespace, '/events/(?P<eventId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'deleteEvent' ),
			'permission_callback' => array( $this, 'isAuthorized' ),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)
		) );
	}	

	private function getEvents( \WP_REST_Request $request ) {

		$response = $this->dataAccess->getEvents();
		
		return rest_ensure_response( $response );
	}

	private function getRaces( \WP_REST_Request $request ) {
	
		$response = $this->dataAccess->getRaces($request['eventId']);
		
		return rest_ensure_response( $response );
	}

	private function getCourseTypes( \WP_REST_Request $request ) {

		$response = $this->dataAccess->getCourseTypes();
		
		return rest_ensure_response( $response );
	}

	private function saveEvent( \WP_REST_Request $request ) {

		$response = $this->dataAccess->insertEvent($request['event']);
		
		return rest_ensure_response( $response );
	}

	private function updateEvent( \WP_REST_Request $request ) {

		$response = $this->dataAccess->updateEvent($request['eventId'], $request['field'], $request['value']);
		
		return rest_ensure_response( $response );
	}

	private function mergeEvents( \WP_REST_Request $request ) {

		$response = $this->dataAccess->mergeEvents($request['fromEventId'], $request['toEventId']);
		
		return rest_ensure_response( $response );
	}

	private function deleteEvent( \WP_REST_Request $request ) {
		// TODO deleteResults parameter.
		$response = $this->dataAccess->deleteEvent($request['eventId'], false);
		
		return rest_ensure_response( $response );
	}

	private function isValidEventUpdateField($value, $request, $key){
		if ( $value == 'name' || $value == 'website' ) {
			return true;
		} else {
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %d must be name or website only.', $key, $value ), array( 'status' => 400 ) );
		} 			
	}
}
?>
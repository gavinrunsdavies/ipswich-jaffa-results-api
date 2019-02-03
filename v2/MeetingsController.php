<?php
namespace IpswichJAFFARunningClubAPI\V2;

require_once plugin_dir_path( __FILE__ ) .'BaseController.php';
require_once plugin_dir_path( __FILE__ ) .'IRoute.php';
	
class MeetingsController extends BaseController implements IRoute {			
	
	public function __construct($namespace) {        
		parent::__construct($namespace);
	}
	
	public function registerRoutes() {										
		register_rest_route( $this->namespace, '/events/(?P<eventId>[\d]+)/meetings', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getMeetings' ),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId' ),
					)
			)
		) );
		
		register_rest_route( $this->namespace, '/events/(?P<eventId>[\d]+)/meetings/(?P<meetingId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getMeeting' ),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId' ),
					),
				'meetingId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId' ),
					)
				)
		) );
			
		register_rest_route( $this->namespace, '/events/(?P<eventId>[\d]+)/meetings/(?P<meetingId>[\d]+)/races', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getMeetingRaces' ),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId' ),
					),
				'meetingId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId' ),
					)
				)
		) );
			
		register_rest_route( $this->namespace, '/events/(?P<eventId>[\d]+)/meetings', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'saveMeeting' ),				
			'args'                => array(
				'eventId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId' ),
					),
				'meeting'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validateMeeting' ),
					),
				)
		) );
		
		// Patch - updates
		register_rest_route( $this->namespace, '/events/(?P<eventId>[\d]+)/meetings/(?P<meetingId>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'updateMeeting' ),
			'args'                => array(
				'meetingId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array( $this, 'isValidMeetingUpdateField' )
					),
				'value'           => array(
					'required'          => true
					)
				)	
		) );
		
		register_rest_route( $this->namespace, '/events/(?P<eventId>[\d]+)/meetings/(?P<meetingId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'deleteMeeting' ),
			'permission_callback' => array( $this, 'isAuthorized' ),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					),
				'meetingId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId' ),
					),
				)
		) );
	}	

	public function getMeetings( \WP_REST_Request $request ) {

		$response = $this->dataAccess->getMeetings($request['eventId']);
		
		return rest_ensure_response( $response );
	}
	
	public function getMeeting( \WP_REST_Request $request ) {

		$response = $this->dataAccess->getMeeting($request['meetingId']);
		
		return rest_ensure_response( $response );
	}
	
	public function getMeetingRaces( \WP_REST_Request $request ) {

		$response = $this->dataAccess->getMeetingRaces($request['meetingId']);
		
		return rest_ensure_response( $response );
	}
	
	public function saveMeeting( \WP_REST_Request $request ) {

		$response = $this->dataAccess->insertMeeting($request['meeting'], $request['eventId']);
		
		return rest_ensure_response( $response );
	}
	
	public function updateMeeting( \WP_REST_Request $request ) {

		$response = $this->dataAccess->updateMeeting($request['meetingId'], $request['field'], $request['value']);
		
		return rest_ensure_response( $response );
	}
	
	public function deleteMeeting( \WP_REST_Request $request ) {
		
		$response = $this->dataAccess->deleteMeeting($request['meetingId']);
		
		return rest_ensure_response( $response );
	}

	public function isValidMeetingUpdateField($value, $request, $key){
		if ( $value == 'from_date' || $value == 'to_date' || $value == 'name' ) {
			return true;
		} else {
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %d must be name or fromDate or toDate only.', $key, $value ), array( 'status' => 400 ) );
		} 			
	}

	public function validateMeeting($meeting, $request, $key) {
		$date = date_parse($meeting['fromDate']);
		if (checkdate($date['month'], $date['day'], $date['year']) === FALSE) {				
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %s has invalid from date value', $key, json_encode($meeting)), array( 'status' => 400 ) );
		} 
		
		$date = date_parse($meeting['toDate']);
		if (checkdate($date['month'], $date['day'], $date['year']) === FALSE) {				
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %s has invalid to date value', $key, json_encode($meeting)), array( 'status' => 400 ) );
		} 
	}
}
?>
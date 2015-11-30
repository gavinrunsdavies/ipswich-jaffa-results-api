<?php

if ( ! class_exists( 'Ipswich_JAFFA_Results_WP_REST_API_Controller' ) ) {
	
	require_once plugin_dir_path( __FILE__ ) .'class-ipswich-jaffa-results-data-access.php';
	
	class Ipswich_JAFFA_Results_WP_REST_API_Controller {
		
		private $data_access;
		
		public function __construct() {
			$this->data_access = new Ipswich_JAFFA_Results_Data_Access();
		}
		
		public function rest_api_init( ) {			
			
			$namespace = 'ipswich-jaffa-api'; // base endpoint for our custom API
			
			$this->register_routes_authentication($namespace);
			$this->register_routes_distances($namespace);
			$this->register_routes_events($namespace);
			$this->register_routes_runners($namespace);
			$this->register_routes_results($namespace);			
			$this->register_routes_runner_of_the_month($namespace);		
			
			add_filter( 'rest_endpoints', array( $this, 'remove_wordpress_core_endpoints'), 10, 1 );			
		}

		public function plugins_loaded() {

			// enqueue WP_API_Settings script
			add_action( 'wp_print_scripts', function() {
				wp_enqueue_script( 'wp-api' );
			} );					
		}
		
		private function register_routes_authentication($namespace) {					
			register_rest_route( $namespace, '/v1/login', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'login' ),
				'args'                => array(
					'username'           => array(
						'required'          => true						
						),
					'password'           => array(
						'required'          => true						
						)
					)				
			) );					
		}

		/* 
		 * NOTE:  Sanitization is useful when you would rather change value. Validation will throw exception
		 * 
		 */
		private function register_routes_distances($namespace) {			
			
			// http://ipswichjaffa.org.uk/wp-json/ipswich-jaffa-api/v1/distances
			register_rest_route( $namespace, '/v1/distances', array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_distances' ),
				'permission_callback' => array( $this, 'permission_check' )
			) );					
		}
		
		private function register_routes_runner_of_the_month($namespace) {			
			
			register_rest_route( $namespace, '/v1/runnerofthemonth', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'save_winners' )				
			) );					
		}
		
		private function register_routes_events($namespace) {										
			register_rest_route( $namespace, '/v1/events', array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'get_events' )
			) );
			
			register_rest_route( $namespace, '/v1/coursetype', array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'get_coursetypes' )
			) );
			
			register_rest_route( $namespace, '/v1/events', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'save_event' ),				
				'args'                => array(
					'event'           => array(
						'required'          => true,												
						'validate_callback' => array( $this, 'validate_event' ),
						),
					)
			) );
			
			// Patch - updates
			register_rest_route( $namespace, '/v1/events/(?P<eventId>[\d]+)', array(
				'methods'             => WP_REST_Server::EDITABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'update_event' ),
				'args'                => array(
					'eventId'           => array(
						'required'          => true,						
						'validate_callback' => array( $this, 'is_valid_id' )
						),
					'field'           => array(
						'required'          => true,
						'validate_callback' => array( $this, 'is_valid_event_update_field' )
						),
					'value'           => array(
						'required'          => true
						)
					)				
			) );
			
			register_rest_route( $namespace, '/v1/events/merge', array(
				'methods'             => WP_REST_Server::EDITABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'merge_events' ),
				'args'                => array(
					'fromEventId'           => array(
						'required'          => true,												
						'validate_callback' => array( $this, 'is_valid_id' )
						),
					'toEventId'           => array(
						'required'          => true,						
						'validate_callback' => array( $this, 'is_valid_id' )
						)
					)					
			) );
			
			register_rest_route( $namespace, '/v1/events/(?P<eventId>[\d]+)', array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_event' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'eventId'           => array(
						'required'          => true,						
						'validate_callback' => array( $this, 'is_valid_id' )
						)
					)
			) );
			
			register_rest_route( $namespace, '/v1/events/(?P<id>[\d]+)/courses', array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_courses' ),				
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                 => array(
					'id'           => array(
						'required'          => true,	
						'validate_callback' => array( $this, 'is_valid_id' )
						)
					)
			) );
			
			register_rest_route( $namespace, '/v1/events/(?P<eventId>[\d]+)/courses/(?P<courseId>[\d]+)', array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_course' ),		
				'permission_callback' => array( $this, 'permission_check' ),				
				'args'                 => array(
					'eventId'           => array(
						'required'          => true,	
						'validate_callback' => array( $this, 'is_valid_id' )
						),					
					'courseId'           => array(
						'required'          => true,	
						'validate_callback' => array( $this, 'is_valid_id' )
						)
					)
			) );
			
			register_rest_route( $namespace, '/v1/events/(?P<eventId>[\d]+)/courses', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'save_course' ),				
				'args'                => array(
					'eventId'           => array(
						'required'          => true,												
						'validate_callback' => array( $this, 'is_valid_id' )
						),
					)
			) );
		}
		
		private function register_routes_runners($namespace) {
			
			register_rest_route( $namespace, '/v1/runners', array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'get_runners' )
			) );
			
			register_rest_route( $namespace, '/v1/runners', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'save_runner' ),				
				'args'                => array(
					'runner'           => array(
						'required'          => true,												
						'validate_callback' => array( $this, 'validate_runner' ),
						),
					)
			) );
			
			register_rest_route( $namespace, '/v1/runners/(?P<runnerId>[\d]+)', array(
				'methods'             => WP_REST_Server::DELETABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'delete_runner' ),
				'args'                => array(
					'runnerId'           => array(
						'required'          => true,						
						'validate_callback' => array( $this, 'is_valid_id' ),
						)
					)
			) );
			
			register_rest_route( $namespace, '/v1/runners/(?P<runnerId>[\d]+)', array(
				'methods'             => WP_REST_Server::EDITABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'update_runner' ),
				'args'                => array(
					'runnerId'           => array(
						'required'          => true,						
						'validate_callback' => array( $this, 'is_valid_id' )
						),
					'field'           => array(
						'required'          => true,
						'validate_callback' => array( $this, 'is_valid_runner_update_field' )
						),
					'value'           => array(
						'required'          => true
						)
					)				
			) );
			
			register_rest_route( $namespace, '/v1/genders', array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'get_genders' )
			) );
		}
		
		private function register_routes_results($namespace) {
			register_rest_route( $namespace, '/v1/results', array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'get_results' )
			) );
			
			register_rest_route( $namespace, '/v1/results', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'save_result' ),				
				'args'                => array(
					'result'           => array(
						'required'          => true,												
						'validate_callback' => array( $this, 'validate_result' ),
						),
					)
			) );
			
			register_rest_route( $namespace, '/v1/results/(?P<resultId>[\d]+)', array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_result' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'resultId'           => array(
						'required'          => true,						
						'validate_callback' => array( $this, 'is_valid_id' ),
						)
					)
			) );
			
			// Patch - updates
			register_rest_route( $namespace, '/v1/results/(?P<resultId>[\d]+)', array(
				'methods'             => WP_REST_Server::EDITABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'update_result' ),
				'args'                => array(
					'resultId'           => array(
						'required'          => true,						
						'validate_callback' => array( $this, 'is_valid_id' )
						),
					'field'           => array(
						'required'          => true,
						'validate_callback' => array( $this, 'is_valid_result_update_field' )
						),
					'value'           => array(
						'required'          => true
						)
					)				
			) );
		}

		public function save_winners( WP_REST_Request $request ) {

			if ($request['winners']['men'] > 0)
			$response = $this->data_access->insertRunnerOfTheMonthWinners(
				$request['winners']['men'],
				'Men',
				$request['winners']['month'],
				$request['winners']['year']);
				
			if ($response == true && $request['winners']['women'] > 0)
				$response = $this->data_access->insertRunnerOfTheMonthWinners(
				$request['winners']['women'],
				'Ladies',
				$request['winners']['month'],
				$request['winners']['year']);
				
			if ($response == true && $request['winners']['junior'] > 0)
				$response = $this->data_access->insertRunnerOfTheMonthWinners(
				$request['winners']['junior'],
				'Juniors',
				$request['winners']['month'],
				$request['winners']['year']);
			
			return rest_ensure_response( $response );
		}
		
		public function get_distances( WP_REST_Request $request ) {
		    $response = $this->data_access->getDistances();

			return rest_ensure_response( $response );
		}
		
		public function get_coursetypes( WP_REST_Request $request ) {

			$response = $this->data_access->getCourseTypes();
			
			return rest_ensure_response( $response );
		}
		
		public function get_events( WP_REST_Request $request ) {

			$response = $this->data_access->getEvents();
			
			return rest_ensure_response( $response );
		}
		
		public function save_event( WP_REST_Request $request ) {

			$response = $this->data_access->insertEvent($request['event']);
			
			return rest_ensure_response( $response );
		}
		
		public function save_course( WP_REST_Request $request ) {

			$response = $this->data_access->insertCourse($request['course']);
			
			return rest_ensure_response( $response );
		}
		
		public function update_event( WP_REST_Request $request ) {

			$response = $this->data_access->updateEvent($request['eventId'], $request['field'], $request['value']);
			
			return rest_ensure_response( $response );
		}
				
		public function delete_event( WP_REST_Request $request ) {
			// TODO deleteResults parameter.
			$response = $this->data_access->deleteEvent($request['eventId'], false);
			
			return rest_ensure_response( $response );
		}
		
		public function delete_course( WP_REST_Request $request ) {
			// TODO deleteResults parameter.
			$response = $this->data_access->deleteCourse($request['courseId'], false);
			
			return rest_ensure_response( $response );
		}
		
		public function merge_events( WP_REST_Request $request ) {

			$response = $this->data_access->mergeEvents($request['fromEventId'], $request['toEventId']);
			
			return rest_ensure_response( $response );
		}
		
		public function get_genders( WP_REST_Request $request ) {

			$response = $this->data_access->getGenders();

			return rest_ensure_response( $response );
		}
		
		public function get_runners( WP_REST_Request $request ) {

			$response = $this->data_access->getRunners();

			return rest_ensure_response( $response );
		}
		
		public function save_runner( WP_REST_Request $request ) {

			$response = $this->data_access->insertRunner($request['runner']);
			
			return rest_ensure_response( $response );
		}
		
		public function delete_runner( WP_REST_Request $request ) {
			// TODO deleteResults parameter.
			$response = $this->data_access->deleteRunner($request['runnerId'], false);
			
			return rest_ensure_response( $response );
		}
		
		public function update_runner( WP_REST_Request $request ) {

			$response = $this->data_access->updateRunner($request['runnerId'], $request['field'], $request['value']);
			
			return rest_ensure_response( $response );
		}
		
		public function get_results( WP_REST_Request $request ) {
			// TODO, eventID, fromDate, toDate and limit. All optional.
			// Sanitization needed before
			$parameters = $request->get_query_params();
			$response = $this->data_access->getResults($parameters['eventId'], $parameters['fromDate'], $parameters['toDate'], $parameters['numberOfResults']);

			return rest_ensure_response( $response );
		}
		
		public function save_result( WP_REST_Request $request ) {

			$response = $this->data_access->insertResult($request['result']);
			
			return rest_ensure_response( $response );
		}
		
		public function delete_result( WP_REST_Request $request ) {
			
			$response = $this->data_access->deleteResult($request['resultId'], false);
			
			return rest_ensure_response( $response );
		}
		
		public function update_result( WP_REST_Request $request ) {

			$response = $this->data_access->updateResult($request['resultId'], $request['field'], $request['value']);
			
			return rest_ensure_response( $response );
		}
		
		public function get_courses( WP_REST_Request $request ) {

			$response = $this->data_access->getCourses($request['id']);

			return rest_ensure_response( $response );
		}
		
		public function permission_check( WP_REST_Request $request ) {
			$id = $this->basic_auth_handler();
			if ( $id  <= 0 ) {				
				return new WP_Error( 'rest_forbidden',
					sprintf( 'You must be logged in to use this API.' ), array( 'status' => 403 ) );
			} else if (!user_can( $id, 'publish_pages' )){
				return new WP_Error( 'rest_forbidden',
					sprintf( 'You do not have enough privlidges to use this API.' ), array( 'status' => 403 ) );
			} else {
				return true;
			}
		}

		public function is_valid_event_update_field($value, $request, $key){
			if ( $value == 'name' || $value == 'website' ) {
				return true;
			} else {
				return new WP_Error( 'rest_invalid_param',
					sprintf( '%s %d must be name or website only.', $key, $value ), array( 'status' => 400 ) );
			} 			
		}
		
		public function is_valid_runner_update_field($value, $request, $key){
			if ( $value == 'name' || $value == 'current_member' ) {
				return true;
			} else {
				return new WP_Error( 'rest_invalid_param',
					sprintf( '%s %d must be name or current_member only.', $key, $value ), array( 'status' => 400 ) );
			} 			
		}
		
		public function is_valid_result_update_field($value, $request, $key){
			if ( $value == 'info' || $value == 'position' || $value == 'result' || $value == 'grandprix' || $value == 'scoring_team') {
				return true;
			} else {
				return new WP_Error( 'rest_invalid_param',
					sprintf( '%s %d must be info or position or result or grandprix or scoring_team only.', $key, $value ), array( 'status' => 400 ) );
			} 			
		}

		public function is_valid_id( $value, $request, $key ) {
			if ( $value < 1 ) {
				// can return false or a custom WP_Error
				return new WP_Error( 'rest_invalid_param',
					sprintf( '%s %d must be greater than 0', $key, $value ), array( 'status' => 400 ) );
			} else {
				return true;
			}
		}	

		public function validate_event($event, $request, $key) {
			if ( empty($event['name']) || 				
				intval($event['distanceId']) < 0 ||
				(!filter_var($event['website'], FILTER_VALIDATE_URL) !== FALSE && !empty($event['website']))) {				
				return new WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid values', $key, json_encode($event)), array( 'status' => 400 ) );
			} else {
				return true;
			}
		}
		
		public function validate_runner($runner, $request, $key) {			
			if ( empty($runner['name'])) {				
				return new WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid name value.', $key, json_encode($runner)), array( 'status' => 400 ) );
			} 
						
			if (intval($runner['sexId']) < 0) {				
				return new WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid sexId value', $key, json_encode($runner)), array( 'status' => 400 ) );
			} 
			
			if ($runner['isCurrentMember'] < 0 ||
				$runner['isCurrentMember'] > 1) {				
				return new WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid isCurrentMember value', $key, json_encode($runner)), array( 'status' => 400 ) );
			} 
			
			$date=date_parse($runner['dateOfBirth']);
			if (checkdate($date['month'], $date['day'], $date['year']) === FALSE) {				
				return new WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid dateOfBirth value', $key, json_encode($runner)), array( 'status' => 400 ) );
			} else {
				return true;
			}
		}
		
		public function validate_result($result, $request, $key) {					
			if (intval($result['eventId']) < 1) {				
				return new WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid eventId value', $key, json_encode($result)), array( 'status' => 400 ) );
			}
			
			if (intval($result['runnerId']) < 1) {				
				return new WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid runnerId value', $key, json_encode($result)), array( 'status' => 400 ) );
			}
			
			if (intval($result['courseId']) < 0) {				
				return new WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid courseId value', $key, json_encode($result)), array( 'status' => 400 ) );
			}			
			
			if (intval($result['position']) < 0) {				
				return new WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid position value', $key, json_encode($result)), array( 'status' => 400 ) );
			}
			
			if (intval($result['team']) < 0) {				
				return new WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid team value', $key, json_encode($result)), array( 'status' => 400 ) );
			}
			
			if ($result['isGrandPrixResult'] < 0 ||
				$result['isGrandPrixResult'] > 1) {				
				return new WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid isGrandPrixResult value', $key, json_encode($result)), array( 'status' => 400 ) );
			}
				
			$date=date_parse($result['date']);
			if (checkdate($date['month'], $date['day'], $date['year']) === FALSE) {				
				return new WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid date value', $key, json_encode($result)), array( 'status' => 400 ) );
			} 
			
			$time = explode(":", $result['time']);			
			if ($time[0] < 0 || $time[1] < 0 || $time[2] < 0 || $time[1] > 59 || $time[2] > 59){ 					
				return new WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid time value', $key, json_encode($result)), array( 'status' => 400 ) );
			} else {
				return true;
			}
		}

		/**
		 * Unsets all core WP endpoints registered by the WordPress REST API (via rest_endpoints filter)
		 * @param  array   $endpoints   registered endpoints
		 * @return array
		 */
		public function remove_wordpress_core_endpoints( $endpoints ) {

			foreach ( array_keys( $endpoints ) as $endpoint ) {
				if ( stripos( $endpoint, '/wp/v2' ) === 0 ) {
					unset( $endpoints[ $endpoint ] );
				}
			}

			return $endpoints;
		}
		
		public function login(WP_REST_Request $request) {
			$username = base64_decode($request['username']);
			$password = base64_decode($request['password']);
			
			$user = wp_authenticate( $username, $password );
			
			return $user;
		}
		
		private function basic_auth_handler( $user ) {
			// Don't authenticate twice
			if ( ! empty( $user ) ) {
				return $user->ID;
			}
			
			// Check that we're trying to authenticate
			if ( !isset( $_SERVER['PHP_AUTH_USER'] ) ) {
				return $user->ID;
			}
			$username = $_SERVER['PHP_AUTH_USER'];
			$password = $_SERVER['PHP_AUTH_PW'];
			
			/**
			 * In multi-site, wp_authenticate_spam_check filter is run on authentication. This filter calls
			 * get_currentuserinfo which in turn calls the determine_current_user filter. This leads to infinite
			 * recursion and a stack overflow unless the current function is removed from the determine_current_user
			 * filter during authentication.
			 */
			remove_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );
			$user = wp_authenticate( $username, $password );
			add_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );
			if ( is_wp_error( $user ) ) {				
				return 0;
			}
			
			return $user->ID;
		}
	}
}

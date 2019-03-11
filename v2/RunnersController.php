<?php
namespace IpswichJAFFARunningClubAPI\V2;

require_once plugin_dir_path( __FILE__ ) .'BaseController.php';
require_once plugin_dir_path( __FILE__ ) .'IRoute.php';
	
class RunnersController extends BaseController implements IRoute {			
	
	public function __construct($namespace) {        
		parent::__construct($namespace);
	}
	
	public function registerRoutes() {										
		
		register_rest_route( $this->namespace, '/runners', array(
			'methods'             => \WP_REST_Server::READABLE,			
			'callback'            => array( $this, 'getRunners' )
		) );
		
		register_rest_route( $this->namespace, '/runners/(?P<runnerId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getRunner' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' ),
					)
				)
		) );
		
		register_rest_route( $this->namespace, '/runners', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'saveRunner' ),				
			'args'                => array(
				'name'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validateName' )
				),
				'sexId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validateGender' )
				),
				'dateOfBirth'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validateDateOfBirth' )
					)
				)
		) );
		
		register_rest_route( $this->namespace, '/runners/(?P<runnerId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'deleteRunner' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' ),
					)
				)
		) );
		
		register_rest_route( $this->namespace, '/runners/(?P<runnerId>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'updateRunner' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				),
				'name'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validateName' )
				),
				'sexId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validateGender' )
				),
				'dateOfBirth'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validateDateOfBirth' )
					)
				)				
		) );
		
		register_rest_route( $this->namespace, '/genders', array(
			'methods'             => \WP_REST_Server::READABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'getGenders' )
		) );
	}	

	public function getGenders( \WP_REST_Request $request ) {

		$response = $this->dataAccess->getGenders();

		return rest_ensure_response( $response );
	}
	
	public function getRunners( \WP_REST_Request $request ) {
		$loggedIn = $this->isAuthorized($request);
		$response = $this->dataAccess->getRunners($loggedIn);

		return rest_ensure_response( $response );
	}
	
	public function getRunner( \WP_REST_Request $request ) {			
		$response = $this->dataAccess->getRunner($request['runnerId']);
		$certificates = $this->dataAccess->getStandardCertificates($request['runnerId']);
		$distances = array(1,2,3,4,5,7,8);
		$rankings = $this->dataAccess->getRunnerRankings($request['runnerId'], $response->sexId, $distances);
		
		$response->certificates = $certificates;
		$response->rankings = $rankings;
		
		return rest_ensure_response( $response );
	}
	
	public function saveRunner( \WP_REST_Request $request ) {

		$response = $this->dataAccess->insertRunner($request);
		
		return rest_ensure_response( $response );
	}
	
	public function deleteRunner( \WP_REST_Request $request ) {
		// TODO deleteResults parameter.
		$response = $this->dataAccess->deleteRunner($request['runnerId'], false);
		
		return rest_ensure_response( $response );
	}
	
	public function updateRunner( \WP_REST_Request $request ) {

		$response = $this->dataAccess->updateRunner($request);
		
		return rest_ensure_response( $response );
	}

	public function isValidRunnerUpdateField($value, $request, $key){
		if ( $value == 'name' || $value == 'current_member' ) {
			return true;
		} else {
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %d must be name or current_member only.', $key, $value ), array( 'status' => 400 ) );
		} 			
	}

	public function validateName($name, $request, $key) {			
		if ( empty($name)) {				
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %s has invalid name value.', $key, $name), array( 'status' => 400 ) );
		} 
	}

	public function validateDateOfBirth($dateOfBirth, $request, $key) {			
		
		$date=date_parse($dateOfBirth);
		if (checkdate($date['month'], $date['day'], $date['year']) === FALSE) {				
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %s has invalid dateOfBirth value', $key, $dateOfBirth), array( 'status' => 400 ) );
		} else {
			return true;
		}
	}

	public function validateGender($sexId, $request, $key) {									
		if (intval($sexId) < 0) {				
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %s has invalid sexId value', $key, $sexId), array( 'status' => 400 ) );
		} 
		
		return true;
	}
}
?>
<?php
namespace IpswichJAFFARunningClubAPI\V2;

require_once plugin_dir_path( __FILE__ ) .'BaseController.php';
require_once plugin_dir_path( __FILE__ ) .'IRoute.php';
	
class AccountController extends BaseController implements IRoute {			
	
	public function __construct($namespace) {        
		parent::__construct($namespace);
	}
	
	public function registerRoutes() {										
		register_rest_route( $namespace, '/login', array(
			'methods'             => \WP_REST_Server::CREATABLE,
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

	private function login(\WP_REST_Request $request) {
		$username = base64_decode($request['username']);
		$password = base64_decode($request['password']);
		
		$this->user = wp_authenticate( $username, $password );
		
		return $this->user;
	}
}
?>
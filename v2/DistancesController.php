<?php
namespace IpswichJAFFARunningClubAPI\V2;

require_once plugin_dir_path( __FILE__ ) .'BaseController.php';
require_once plugin_dir_path( __FILE__ ) .'IRoute.php';
	
class DistancesController extends BaseController implements IRoute {			
	
	public function __construct($namespace, $db) {        
		parent::__construct($namespace, $db);
	}
	
	public function registerRoutes() {										
		register_rest_route( $this->namespace, '/distances', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getDistances' )
		) );	
	}	

	public function getDistances( \WP_REST_Request $request ) {

		$response = $this->dataAccess->getDistances();
		
		return rest_ensure_response( $response );
	}
}
?>
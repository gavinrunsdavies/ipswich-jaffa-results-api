<?php
namespace IpswichJAFFARunningClubAPI\V2;

require_once plugin_dir_path( __FILE__ ) .'BaseController.php';
require_once plugin_dir_path( __FILE__ ) .'IRoute.php';
	
class CategoriesController extends BaseController implements IRoute {			
	
	public function registerRoutes() {										
		register_rest_route( $this->namespace, '/categories', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getCategories' )
		) );			
	}	

	public function getCategories( \WP_REST_Request $request ) {

		$response = $this->dataAccess->getCategories();
		
		return rest_ensure_response( $response );
	}
}
?>
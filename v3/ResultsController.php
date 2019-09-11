<?php
namespace IpswichJAFFARunningClubAPI\V3;
	
require_once plugin_dir_path( __FILE__ ) .'BaseController.php';
require_once plugin_dir_path( __FILE__ ) .'IRoute.php';

class ResultsController extends BaseController implements IRoute {	
	
	public function __construct($namespace) {        
		parent::__construct($namespace);
	}
	
	public function registerRoutes() {
		register_rest_route( $this->namespace, '/results/load', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'loadResults' )
		) );
	}		
	
	public function loadResults($request) {		
		
		$results = $this->csvToArray($_FILES["file"]["tmp_name"], ',', $request['numberOfHeaderRows']);
		
		return rest_ensure_response( $results );
	}
	
	private function csvToArray($filename='', $delimiter=',', $numberOfHeaderRows = 0)
	{	
		if(!file_exists($filename) || !is_readable($filename))
			return FALSE;
		
		$header = NULL;
		$data = array();
		$rowCount = 0;
		if (($handle = fopen($filename, 'r')) !== FALSE)
		{
			while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
			{
				$rowCount++;
				if($rowCount > $numberOfHeaderRows)					
					$data[] = $row;
			}
			fclose($handle);
		}
		return $data;
	}	
}
?>
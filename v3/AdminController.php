<?php
namespace IpswichJAFFARunningClubAPI\V3;
	
require_once plugin_dir_path( __FILE__ ) .'BaseController.php';
require_once plugin_dir_path( __FILE__ ) .'IRoute.php';

class AdminController extends BaseController implements IRoute {			
	
	public function __construct($namespace, $db) {        
		parent::__construct($namespace, $db);
		$this->data_access_v2 = new \IpswichJAFFARunningClubAPI\V2\ResultsDataAccess($db);		
	}
	
	private $data_access_v2;	
	
	public function registerRoutes() {	
		register_rest_route( $this->namespace, '/admin/eventtoraces', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'saveEventAsRaces' ),
			'args'                => array(
				'sourceEventId' => array(
					'required'    => true,				
					'validate_callback' => array( $this, 'isValidId' )					
					),
				'destinationEventId' => array(
					'required'    => true,		
					'validate_callback' => array( $this, 'isValidId' )					
					),
				'raceName' => array(
					'required'    => true,					
					)
				)
		) );
	}
	
	public function saveEventAsRaces( \WP_REST_Request $request ) {
		$sourceEventId = $request['sourceEventId'];
		$destinationEventId = $request['destinationEventId'];
		$raceName = $request['raceName'];
		
		$sourceEventRaces = $this->data_access_v2->getRaces($sourceEventId);
		
		foreach($sourceEventRaces as $race) {
			$this->data_access_v2->updateRace($race->id, 'event_id', $sourceEventId);
			$this->data_access_v2->updateRace($race->id, 'description', $raceName);
		}
	}	
}
?>
<?php
namespace IpswichJAFFARunningClubAPI\V2;

require_once plugin_dir_path( __FILE__ ) .'BaseController.php';
require_once plugin_dir_path( __FILE__ ) .'IRoute.php';
	
class StatisticsController extends BaseController implements IRoute {			
	
	public function __construct($namespace) {        
		parent::__construct($namespace);
	}
	
	public function registerRoutes() {												
		register_rest_route( $this->namespace, '/statistics/type/(?P<typeId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getStatistics' ),
			'args'                => array(
				'typeId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId')
				)
			)
		) );
	}	

	public function getStatistics( \WP_REST_Request $request ) {
		switch ($request['typeId'])
		{
			case 1:
				$response = $this->dataAccess->getResultsByYearAndCounty();
				break;
			case 2:
				$response = $this->dataAccess->getResultsByYearAndCountry();
				break;
			case 3:
				$response = $this->dataAccess->getResultsCountByYear();
				break;
			case 4:
				$response = $this->dataAccess->getPersonalBestTotals();
				break;
			case 5:
				$response = $this->dataAccess->getPersonalBestTotalByYear();
				break;				
			case 6:
				$response = $this->dataAccess->getTopAttendedRaces();
				break;
			case 7:
				$response = $this->dataAccess->getTopMembersRacing();
				break;	
			case 8:
				$response = $this->dataAccess->getTopMembersRacingByYear();
				break;					
			default:
			break;
		}
		
		return rest_ensure_response( $response );
	}
}
?>
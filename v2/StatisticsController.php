<?php
namespace IpswichJAFFARunningClubAPI\V2;

require_once plugin_dir_path( __FILE__ ) .'BaseController.php';
require_once plugin_dir_path( __FILE__ ) .'IRoute.php';
	
class StatisticsController extends BaseController implements IRoute {			
	
	public function __construct($namespace, $db) {        
		parent::__construct($namespace, $db);
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
											
		register_rest_route( $this->namespace, '/statistics/results/runner/year/(?P<year>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getResultCountByRunnerByYear' ),
			'args'                => array(
				'year'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId')
				)
			)
		) );

		register_rest_route( $this->namespace, '/statistics/results/runner', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getResultCountByRunnerByYear' ),
			'args'                => array()
		) );

		register_rest_route( $this->namespace, '/statistics/clubresults', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getClubResults' ),
			'args'                => array()
		) );

		register_rest_route( $this->namespace, '/statistics/groupedrunnerresultscount', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getGroupedRunnerResultsCount' ),
			'args'                => array()
		) );
		
		register_rest_route( $this->namespace, '/statistics/meanPercentageGradingByMonth', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getMeanPercentageGradingByMonth' ),
			'args'                => array()
		) );
	}
	
	public function getMeanPercentageGradingByMonth( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getMeanPercentageGradingByMonth();	
		$gradingsByCategory = array();

		foreach ($response as $item) {	
			$categoryCode = $item->categoryCode;
			if (!array_key_exists($categoryCode, $gradingsByCategory)) {				
				$gradingsByCategory[$categoryCode] = array();				
			}
			$gradingsByCategory[$categoryCode][] = array("date" => $item->date, "meanGrading" => $item->meanGrading);
		}

		return $gradingsByCategory;
	}

	public function getResultCountByRunnerByYear( \WP_REST_Request $request ) {
		$parameters = $request->get_query_params();
		$limit = $parameters['limit'] ?? 50;
		return $this->dataAccess->getResultCountByRunnerByYear($request['year'], $limit);		
	}

	public function getClubResults( \WP_REST_Request $request ) {
		$parameters = $request->get_query_params();
		$limit = $parameters['limit'] ?? 5000;
		return $this->dataAccess->getClubResultsCount($request['year'], $limit);		
	}

	public function getGroupedRunnerResultsCount( \WP_REST_Request $request ) {
		$parameters = $request->get_query_params();
		$groupSize = $parameters['groupSize'] ?? 50;
		$minimumResultCount = $parameters['minimumResultCount'] ?? 0;
		return $this->dataAccess->getGroupedRunnerResultsCount($groupSize, $minimumResultCount);		
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
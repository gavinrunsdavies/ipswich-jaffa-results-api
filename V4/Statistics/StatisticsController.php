<?php
namespace IpswichJAFFARunningClubAPI\V4\Statistics;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH .'V4/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH .'V4/IRoute.php';
require_once 'StatisticsDataAccess.php';

use IpswichJAFFARunningClubAPI\V4\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V4\IRoute as IRoute;
	
class StatisticsController extends BaseController implements IRoute 
{				
	public function __construct(string $route, $db) 
	{        
		parent::__construct($route, new StatisticsDataAccess($db));
	}
	
	public function registerRoutes() 
	{												
		register_rest_route( $this->route, '/statistics/type/(?P<typeId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getStatistics' ),
			'args'                => array(
				'typeId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId')
				)
			)
		) );
											
		register_rest_route( $this->route, '/statistics/results/runner/year/(?P<year>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getResultCountByRunnerByYear' ),
			'args'                => array(
				'year'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId')
				)
			)
		) );

		register_rest_route( $this->route, '/statistics/results/runner', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getResultCountByRunnerByYear' ),
			'args'                => array()
		) );

		register_rest_route( $this->route, '/statistics/clubresults', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getClubResults' ),
			'args'                => array()
		) );

		register_rest_route( $this->route, '/statistics/groupedrunnerresultscount', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getGroupedRunnerResultsCount' ),
			'args'                => array()
		) );
		
		register_rest_route( $this->route, '/statistics/meanPercentageGradingByMonth', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getMeanPercentageGradingByMonth' ),
			'args'                => array()
		) );
	}
	
	public function getMeanPercentageGradingByMonth( \WP_REST_Request $request ) 
	{
		$response = $this->dataAccess->getMeanPercentageGradingByMonth();	
		
		return $this->processDataResponse($response, function($response)
			{
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
		);					
	}

	public function getResultCountByRunnerByYear( \WP_REST_Request $request ) 
	{
		$parameters = $request->get_query_params();
		$limit = $parameters['limit'] ?? 50;
		$response = $this->dataAccess->getResultCountByRunnerByYear($request['year'], $limit);	
		return $this->processDataResponse($response, function($response) {
			return $response;
		});	
	}

	public function getClubResults( \WP_REST_Request $request ) 
	{
		$parameters = $request->get_query_params();
		$limit = $parameters['limit'] ?? 5000;
		$response = $this->dataAccess->getClubResultsCount($request['year'], $limit);
		return $this->processDataResponse($response, function($response) {
			return $response;
		});			
	}

	public function getGroupedRunnerResultsCount( \WP_REST_Request $request ) 
	{
		$parameters = $request->get_query_params();
		$groupSize = $parameters['groupSize'] ?? 50;
		$minimumResultCount = $parameters['minimumResultCount'] ?? 0;
		$response = $this->dataAccess->getGroupedRunnerResultsCount($groupSize, $minimumResultCount);	
		return $this->processDataResponse($response, function($response) {
			return $response;
		});		
	}

	public function getStatistics( \WP_REST_Request $request ) 
	{
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
		
		return $this->processDataResponse($response, function($response) {
			return $response;
		});	
	}
}
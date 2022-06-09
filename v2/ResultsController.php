<?php
namespace IpswichJAFFARunningClubAPI\V2;	

require_once plugin_dir_path( __FILE__ ) .'BaseController.php';
require_once plugin_dir_path( __FILE__ ) .'IRoute.php';
require_once plugin_dir_path( __DIR__ ) .'/Constants/CourseTypes.php';
require_once plugin_dir_path( __DIR__ ) .'/Constants/Distances.php';

class ResultsController extends BaseController implements IRoute {			
	
	private $invalidCourseTypes = array(
		\IpswichJAFFARunningClubAPI\Constants\CourseTypes::MULTITERRAIN, 
		\IpswichJAFFARunningClubAPI\Constants\CourseTypes::FELL, 
		\IpswichJAFFARunningClubAPI\Constants\CourseTypes::CROSS_COUNTRY, 
		\IpswichJAFFARunningClubAPI\Constants\CourseTypes::PARK, 
		\IpswichJAFFARunningClubAPI\Constants\CourseTypes::VIRTUAL
	);

	private $standardDistances = array(
		\IpswichJAFFARunningClubAPI\Constants\Distances::FIVE_KILOMETRES, 
		\IpswichJAFFARunningClubAPI\Constants\Distances::FIVE_MILES, 
		\IpswichJAFFARunningClubAPI\Constants\Distances::TEN_KILOMETRES, 
		\IpswichJAFFARunningClubAPI\Constants\Distances::TEN_MILES, 
		\IpswichJAFFARunningClubAPI\Constants\Distances::HALF_MARATHON, 
		\IpswichJAFFARunningClubAPI\Constants\Distances::TWENTY_MILES, 
		\IpswichJAFFARunningClubAPI\Constants\Distances::MARATHON
	);

	public function registerRoutes() {		
		register_rest_route( $this->namespace, '/results/correctStandardCertifcates', array(
			'methods'             => \WP_REST_Server::READABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'correctStandardCertifcates' )
		) );

		register_rest_route( $this->namespace, '/results', array(
			'methods'             => \WP_REST_Server::READABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'getResults' )
		) );
		
		register_rest_route( $this->namespace, '/results', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'saveResult' ),				
			'args'                => array(
				'result'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validateResult' ),
					),
				)
		) );
		
		register_rest_route( $this->namespace, '/results/(?P<resultId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'deleteResult' ),
			'permission_callback' => array( $this, 'isAuthorized' ),
			'args'                => array(
				'resultId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' ),
					)
				)
		) );
		
		// Patch - updates
		register_rest_route( $this->namespace, '/results/(?P<resultId>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'updateResult' ),
			'args'                => array(
				'resultId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array( $this, 'isValidResultUpdateField' )
					),
				'value'           => array(
					'required'          => true
					)
				)				
		) );

		register_rest_route( $this->namespace, '/results/records', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getOverallClubRecords' )	
		) );

		register_rest_route( $this->namespace, '/results/records/holders', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getTopClubRecordHolders' )			
		) );
		
		register_rest_route( $this->namespace, '/results/records/distance/(?P<distanceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getClubRecords' ),
			'args'                => array(
				'distanceId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)		
		) );
		
		register_rest_route( $this->namespace, '/results/historicrecords/distance/(?P<distanceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getHistoricClubRecords' ),
			'args'                => array(
				'distanceId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)		
		) );

		register_rest_route( $this->namespace, '/results/historicrecords/category/(?P<categoryId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getHistoricClubRecordsByCategory' ),
			'args'                => array(
				'categoryId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)		
		) );
		
		register_rest_route( $this->namespace, '/results/ranking/distance/(?P<distanceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getResultRankings' ),
			'args'                => array(
				'distanceId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)		
		) );
		
		register_rest_route( $this->namespace, '/results/ranking/averageWMA', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getAveragePercentageRankings' )
		) );
		
		register_rest_route( $this->namespace, '/results/ranking/wma', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getWMAPercentageRankings' )				
		) );
		
		register_rest_route( $this->namespace, '/results/runner/(?P<runnerId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getMemberResults' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)		
		) );
		
		register_rest_route( $this->namespace, '/results/runner/(?P<runnerId>[\d]+)/personalbests', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getMemberPBResults' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)		
		) );
		
		register_rest_route( $this->namespace, '/results/runner/(?P<runnerId>[\d]+)/insights/distance/(?P<distanceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getMemberInsightsRaceDistance' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					),
				'distanceId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)		
		) );
		
		register_rest_route( $this->namespace, '/results/runner/(?P<runnerId>[\d]+)/insights/numberOfRaces', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getMemberInsightsNumberOfRaces' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)	
			)
		) );
		
		register_rest_route( $this->namespace, '/results/runner/(?P<runnerId>[\d]+)/insights/totalRaceTime', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getMemberInsightsTotalRaceTime' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)	
				)
		) );
							
		register_rest_route( $this->namespace, '/results/runner/compare', array(
			'methods'             => \WP_REST_Server::CREATABLE,				
			'callback'            => array( $this, 'compareMemberRaces' ),
			'args'                => array(
				'runnerIds'           => array(
					'required'          => true
					)
				)		
		) );
		
		register_rest_route( $this->namespace, '/results/grandPrix/(?P<year>[\d]{4})/(?P<sexId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getGrandPrixPoints' ),
			'args'                => array(
				'sexId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				),
				'year'           => array(
					'required'          => true
				)
		) );
		
		register_rest_route( $this->namespace, '/results/race/(?P<raceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getRaceResults' ),
			'args'                => array(
				'raceId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				)
		) );
    
        register_rest_route( $this->namespace, '/results/county', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getCountyChampions' )
		) );
	}

	public function correctStandardCertifcates( \WP_REST_Request $request ) {
		$response = $this->dataAccess->correctStandardTypesForResultsAfter2015();

		return rest_ensure_response( $response );
	}

	public function getMemberInsightsRaceDistance( \WP_REST_Request $request ) {
		$raceTimes = $this->dataAccess->getMemberInsightsRaceDistance($request['distanceId']);

		$memberTotals = $this->dataAccess->getRunnerDistanceResultMinMaxAverage($request['runnerId'], $request['distanceId']);

		$response = array();
		$response['raceTimes'] = $raceTimes;
		$response['slowest'] = $memberTotals->slowest;
		$response['fastest'] = $memberTotals->fastest;
		$response['mean'] = $memberTotals->mean;
		return rest_ensure_response( $response );
	}
							
	public function getMemberInsightsNumberOfRaces( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getMemberInsightsNumberOfRaces($request['runnerId']);

		return rest_ensure_response( $response );
	}
							
	public function getMemberInsightsTotalRaceTime( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getMemberInsightsTotalRaceTime($request['runnerId']);

		return rest_ensure_response( $response );
	}

	public function getOverallClubRecords( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getOverallClubRecords();

		return rest_ensure_response( $response );
	}
							
	public function getClubRecords( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getClubRecords($request['distanceId']);

		return rest_ensure_response( $response );
	}

	public function getTopClubRecordHolders( \WP_REST_Request $request ) {
		$distances = array(1,2,3,4,5,7,8);
		$recordHolders = array();
		foreach ($distances as $distanceId) {
			$records = $this->dataAccess->getClubRecords($distanceId);
			foreach ($records as $categoryRecord) {
				if (!array_key_exists($categoryRecord->runnerId, $recordHolders)) {  
					$recordHolders[$categoryRecord->runnerId] = array();
				}
				$recordHolders[$categoryRecord->runnerId][] = $categoryRecord;
		    }
		}

		$parameters = $request->get_query_params();
		$filteredRecordHolders = array();
		$limit = $parameters['limit'] ?? 3;
		foreach ($recordHolders as $holder => $records) {
			if (count($records) >= $limit) {
				$runner = array("id" => $holder, "name" => $records[0]->runnerName);
				$runnerRecords = array();
				foreach ($records as $record) {					
					$runnerRecords[]= array(
						"eventId" => $record->eventId,
						"eventName" => $record->eventName,
						"date" => $record->date,
						"distance" => $record->distance,
						"result" => $record->result,
						"categoryCode" => $record->categoryCode,
						"raceId" => $record->raceId,
						"description" => $record->description,
						"venue" => $record->venue);					
				}
				$filteredRecordHolders[] = array("runner" => $runner, "records" => $runnerRecords);
			}
		}

		return rest_ensure_response( $filteredRecordHolders );
	}

	public function getRaceResults( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getRaceResults($request['raceId']);
      
		$pbRunners = array();
		foreach ($response as $result) {
		  if (!in_array($result->runnerId, $pbRunners)) {  
			$pbRunners[] = $result->runnerId;
		  }
		}   
		
		$runnerIds = implode (", ", $pbRunners);
		
		$previousPersonalBestResults = $this->dataAccess->getPreviousPersonalBest($runnerIds, $request['raceId']);
		
		foreach ($response as $result) {
		  foreach ($previousPersonalBestResults as $previousBestResult) {
			if ($result->runnerId == $previousBestResult->runnerId) {
			  $result->previousPersonalBestResult = $previousBestResult->previousBest;
			  break;
			}
		  }          
		}
		
		return rest_ensure_response( $response );
	}
	
	public function getMemberResults( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getMemberResults($request['runnerId']);

		return rest_ensure_response( $response );
	}
	
	public function getMemberPBResults( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getMemberPBResults($request['runnerId']);

		return rest_ensure_response( $response );
	}		
	
	public function compareMemberRaces( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getHeadToHeadResults($request['runnerIds']);

		return rest_ensure_response( $response );
	}
	
	public function getResultRankings( \WP_REST_Request $request ) {
		$parameters = $request->get_query_params();			
		$response = $this->dataAccess->getResultRankings($request['distanceId'], $parameters['year'], $parameters['sexId'], $parameters['categoryId']);

		return rest_ensure_response( $response );
	}
	
	public function getWMAPercentageRankings( \WP_REST_Request $request ) {
		$parameters = $request->get_query_params();			
		$response = $this->dataAccess->getWMAPercentageRankings($parameters['sexId'], $parameters['distanceId'], $parameters['year'], $parameters['distinct']);

		return rest_ensure_response( $response );
	}
	
	public function getHistoricClubRecords( \WP_REST_Request $request ) {			
		$response = $this->dataAccess->getAllRaceResults($request['distanceId']);
		
		// Group data in to catgeories and pick best times
		$distanceMeasurementUnitTypes = array(3,4,5);
		$categoryCode = 0;
		$records = array();
		foreach ($response as $item) {
			if ($item->courseTypeId != null && in_array($item->courseTypeId, $this->invalidCourseTypes)){
	  			continue;
			}
	
			$categoryCode = $item->categoryCode;
			if (!array_key_exists($categoryCode, $records)) {
				$result = array("runnerId" => $item->id, "runnerName" => $item->name, "raceId" => $item->raceId, "raceDescription" => $item->raceDescription, "eventName" => $item->eventName, "time" => $item->result, "position" => $item->position, "date" => $item->date);
				$records[$categoryCode] = array("id" => $item->categoryId, "code" => $item->categoryCode, "records" => array($result));
				
				continue;
			}
			
			$currentResult = $item->result;
			$count = count($records[$categoryCode]['records']);
			$previousRecord = $records[$categoryCode]['records'][$count-1]['time'];
			if (in_array($item->resultMeasurementUnitTypeId, $distanceMeasurementUnitTypes)) {
				if ($currentResult > $previousRecord) {
					$records[$categoryCode]['records'][] = array("runnerId" => $item->id, "runnerName" => $item->name, "raceId" => $item->raceId, "raceDescription" => $item->raceDescription, "eventName" => $item->eventName, "time" => $item->result, "position" => $item->position, "date" => $item->date);
				}									
			} else {
				if ($currentResult < $previousRecord) {
					$records[$categoryCode]['records'][] = array("runnerId" => $item->id, "runnerName" => $item->name, "raceId" => $item->raceId, "raceDescription" => $item->raceDescription, "eventName" => $item->eventName, "time" => $item->result, "position" => $item->position, "date" => $item->date);
				}	
			}
		}

		// Sort Record by Category name
		ksort($records);		

		return rest_ensure_response( $records );
	}

	public function getHistoricClubRecordsByCategory( \WP_REST_Request $request ) {			
		$response = $this->dataAccess->getAllRaceResultsByCategory($request['categoryId']);
		
		// Group data in to distances and pick best times
		$distanceMeasurementUnitTypes = array(3,4,5);
		$distance = 0;
		$records = array();
		
		foreach ($response as $item) {
			if (!$this->isValidCourseTypeForMeasuredDistance($item->courseTypeId)) {
	  			continue;
			}

			if (!$this->isStandardDistance($item->distanceId)) {
				continue;
		    }
	
			$distance = $item->distance;
			if (!array_key_exists($distance, $records)) {
				$result = array("distance" => $distance, "runnerId" => $item->id, "runnerName" => $item->name, "raceId" => $item->raceId, "raceDescription" => $item->raceDescription, "eventName" => $item->eventName, "time" => $item->result, "position" => $item->position, "startDate" => $item->date, "endDate" => date("Y-m-d"));
				$records[$distance] = array($result);
				
				continue;
			}
			
			$currentResult = $item->result;
			$count = count($records[$distance]);
			$previousRecord = $records[$distance][$count-1]['time'];
			if (in_array($item->resultMeasurementUnitTypeId, $distanceMeasurementUnitTypes)) {
				if ($currentResult > $previousRecord) {
					$records[$distance][$count-1]['endDate'] = $item->date;
					$records[$distance][] = array("distance" => $distance, "runnerId" => $item->id, "runnerName" => $item->name, "raceId" => $item->raceId, "raceDescription" => $item->raceDescription, "eventName" => $item->eventName, "time" => $item->result, "position" => $item->position, "startDate" => $item->date, "endDate" => date("Y-m-d"));
				}									
			} else {
				if ($currentResult < $previousRecord) {
					$records[$distance][$count-1]['endDate'] = $item->date;
					$records[$distance][] = array("distance" => $distance, "runnerId" => $item->id, "runnerName" => $item->name, "raceId" => $item->raceId, "raceDescription" => $item->raceDescription, "eventName" => $item->eventName, "time" => $item->result, "position" => $item->position, "startDate" => $item->date, "endDate" => date("Y-m-d"));
				}	
			}
		}

		// Flatten
		$flattenedRecords = [];		
		foreach ($records as $value) {	
			foreach ($value as $item) {
				$flattenedRecords[] = $item;
			}
		}

		return rest_ensure_response($flattenedRecords);
	}

	private function isValidCourseTypeForMeasuredDistance($courseTypeId) {
		return $courseTypeId == null || !in_array($courseTypeId, $this->invalidCourseTypes);
	}

	private function isStandardDistance($distanceId) {
		return in_array($distanceId, $this->standardDistances);
	}
	
	public function getAveragePercentageRankings( \WP_REST_Request $request ) {
		$parameters = $request->get_query_params();			
		$response = $this->dataAccess->getAveragePercentageRankings($parameters['sexId'], $parameters['year'], $parameters['numberOfRaces']);

		return rest_ensure_response( $response );
	}
	
	// Group data in structure:
	// {
	  // "5": {
		// "id": "5",
		// "name": "Alan Jackson",
		// "dateOfBirth": "1980-01-02",
		// "races": [
		  // {
			// "id": "954",
			// "points": "85"
		  // },
		  // {
			// "id": "1512",
			// "points": "79"
		  // },
		  // {
			// "id": "729",
			// "points": "90"
		  // }
		// ],
		// "totalPoints": 254
	  // },
	  // "9": {
		// "id": "9",
		// "name": "Alistair Dick",
		// "races": [
		  // {
			// "id": "954",
			// "points": "88"
		  // },
		  // {
			// "id": "549",
			// "points": "96"
		  // }
		// ],
		// "totalPoints": 184
	  // }
	public function getGrandPrixPoints( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getGrandPrixPoints($request['year'], $request['sexId']);

		// Calculate GP points
		// Handicap - base on position
		// Ekiden - base on time for each race distance
		// Others - base on time then position for event
		
		// Group data in to events
		$events = array();
		$races = array();
		$results = array();
		foreach ($response as $item) {
			$eventId = $item->eventId;

			if ($eventId == 203) {
				$resultSetId = $eventId + '_' + $item->distanceId; // Change resultSetId to be eventId + distanceId to give a unique grouping.
			} else {
				$resultSetId = $eventId;
			}
			
			if (!array_key_exists($resultSetId, $events)) {	
				if ($eventId == 203) {
					$sortOrder = 'RESULT'; 
				} else if ($eventId == 89) {
					$sortOrder = 'POSITION';
				} else if ($item->result != '00:00:00' && $item->result != '') {
					$sortOrder = 'RESULT';
				} else {
					$sortOrder = 'POSITION';
				}
				
				$events[$resultSetId] = array("id" => $eventId, "name" => $item->eventName, "sortOrder" => $sortOrder, "results" => array());
			}
						
			$events[$resultSetId]['results'][] = $item;	
			
			$runnerId = $item->runnerId;
			if (!array_key_exists($runnerId, $results)) {
				$gpCategory = $this->getGrandPrixCategory($item->dateOfBirth, $request['year']);
				$results[$runnerId] = array("id" => $runnerId, "name" => $item->name, "categoryCode" => $gpCategory, "races" => array());
			}
			
			$raceId = $item->raceId;
			if (!in_array($raceId, $races)) {
				$races[] = $raceId;
			}
		}
		
		$events = $this->removeDuplicateEkidenRunnerResults($events);			
		
		foreach ($events as $key => $event) {
			if ($event['sortOrder'] == 'POSITION') {
				uasort($event['results'], array($this, 'compareGrandPrixEventByPosition'));
			} else {
				uasort($event['results'], array($this, 'compareGrandPrixEventByResult'));					
			}
			// Re-index array.
			$events[$key]['results'] = array_values($event['results']);				
		}			
		
		foreach ($events as $event) {
			$points = 100;

			foreach ($event['results'] as $result) {		
				if (array_key_exists($result->runnerId, $results)) {
					$results[$result->runnerId]['races'][] = array("id" => $result->raceId, "points" => $points);
					$results[$result->runnerId]['totalPoints'] += $points;
				}
				$points--;
			}
		}		
		
		// Get race details
		$raceDetails = $this->dataAccess->getRaceDetails($races);
		
		foreach ($results as $runner){
			$results[$runner['id']]['best8Score'] = $this->getGrandPrixBest8Score($runner['races']);
		}
		
		$getGrandPrixPointsResponse = array(
			"races" => $raceDetails,
			"results" => array_values($results)
		);
		
		return rest_ensure_response( $getGrandPrixPointsResponse );
	}
	
	private function getGrandPrixCategory($dateOfBirth, $year) 
	{
	//http://stackoverflow.com/questions/3776682/php-calculate-age		

	  $dob = new \DateTime($dateOfBirth);
	  $gpDate = new \DateTime("$year-04-01");

	  $diff = $dob->diff($gpDate);
				
	  if ($diff->y < 40) 
		return "Open";
	  if ($diff->y < 50) 
		return "V40";
	  if ($diff->y < 60) 
		return "V50";
	  
	  return "V60";
	}
	
	private function getGrandPrixBest8Score($races) 
	{			
		uasort($races, array($this, 'compareGrandPrixRaces'));

		// Get best 8 scores 
		$best8Score = 0;   

		if (count($races) < 8)
			return 0;
			
		$count = 1;
		foreach ($races as $race) 
		{        
			$best8Score += $race['points'];				
			if ($count == 8) 
			{
			  break;
			}
			$count++;
		}		
	  
		return $best8Score;
	} // end function getGrandPrixBest8Score
	
	private function removeDuplicateEkidenRunnerResults($events) {
		foreach ($events as $key => $event) {
			if ($event["id"] == 203) {				
				$events[$key]["results"] = $this->uniqueMultidimArray($event["results"]); 					
			}
		}
		
		return $events;
	}
	
	// From http://php.net/manual/en/function.array-unique.php
	private function uniqueMultidimArray($array) {
		$temp_array = array();
		$i = 0;
		$key_array = array();
	   
		foreach($array as $val) {				
			if (!in_array($val->runnerId, $key_array)) {
				$key_array[$i] = $val->runnerId;
				$temp_array[$i] = $val;
				$i++;
			}							
		}
		
		return $temp_array;
	} 
	
	private function compareGrandPrixEventByPosition($a, $b) {
		if ($a->position == $b->position) {
			return 0;
		}
		
		return ($a->position > $b->position) ? 1 : -1;
	}
	
	private function compareGrandPrixEventByResult($a, $b) {
		if ($a->result == $b->result) {
			return 0;
		}
		
		// Add 00: prefix to compare hh:mm:ss to mm:ss
		$aFullTime = $a->result;
		if (strlen($a->result) < 8) {
			$aFullTime = '00:'.$a->result;
		}
		
		$bFullTime = $b->result;
		if (strlen($b->result) < 8) {
			$bFullTime = '00:'.$b->result;
		}			
					
		return ($aFullTime > $bFullTime) ? 1 : -1;
	}
	
	private function compareGrandPrixRaces($a, $b) {
		if ($a['points'] == $b['points']) {
			return 0;
		}
		
		return ($a['points'] > $b['points']) ? -1 : 1;
	}

	public function getResults( \WP_REST_Request $request ) {
		// TODO, eventID, fromDate, toDate and limit. All optional.
		// Sanitization needed before
		$parameters = $request->get_query_params();
		$response = $this->dataAccess->getResults($parameters['eventId'], $parameters['fromDate'], $parameters['toDate'], $parameters['numberOfResults']);

		return rest_ensure_response( $response );
	}
	
	public function saveResult( \WP_REST_Request $request ) {

		$response = $this->dataAccess->insertResult($request['result']);
		
		return rest_ensure_response( $response );
	}
	
	public function deleteResult( \WP_REST_Request $request ) {
		
		$response = $this->dataAccess->deleteResult($request['resultId'], false);
		
		return rest_ensure_response( $response );
	}
	
	public function updateResult( \WP_REST_Request $request ) {

		$response = $this->dataAccess->updateResult($request['resultId'], $request['field'], $request['value']);
		
		return rest_ensure_response( $response );
	}
  
  public function getCountyChampions( \WP_REST_Request $request ) {
		    $response = $this->dataAccess->getCountyChampions();

			return rest_ensure_response( $response );
	}

	public function validateResult($result, $request, $key) {					
		if (intval($result['eventId']) < 1) {				
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %s has invalid eventId value', $key, json_encode($result)), array( 'status' => 400 ) );
		}
		
		if (intval($result['runnerId']) < 1) {				
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %s has invalid runnerId value', $key, json_encode($result)), array( 'status' => 400 ) );
		}		
		
		if (intval($result['position']) < 0) {				
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %s has invalid position value', $key, json_encode($result)), array( 'status' => 400 ) );
		}
		
		if (intval($result['team']) < 0) {				
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %s has invalid team value', $key, json_encode($result)), array( 'status' => 400 ) );
		}
		
		if ($result['isGrandPrixResult'] < 0 ||
			$result['isGrandPrixResult'] > 1) {				
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %s has invalid isGrandPrixResult value', $key, json_encode($result)), array( 'status' => 400 ) );
		}
			
		$date=date_parse($result['date']);
		if (checkdate($date['month'], $date['day'], $date['year']) === FALSE) {				
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %s has invalid date value', $key, json_encode($result)), array( 'status' => 400 ) );
		} 
		
		if (strpos($result['result'], ':') !== false) {
			$time = explode(":", $result['result']);	

			if ($time[0] < 0 || $time[1] < 0 || $time[2] < 0 || $time[1] > 59 || $time[2] > 59){ 					
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid time value', $key, json_encode($result)), array( 'status' => 400 ) );
			} else {
				return true;
			}
		} else {
			// TODO validate distance (meters)
		}
	}

	public function isValidResultUpdateField($value, $request, $key){
		if ( $value == 'info' || $value == 'position' || $value == 'result' || $value == 'grandprix' || $value == 'scoring_team' || $value == 'race_id') {
			return true;
		} else {
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %d must be info or position or result or grandprix or scoring_team only.', $key, $value ), array( 'status' => 400 ) );
		} 			
	}		
}
?>
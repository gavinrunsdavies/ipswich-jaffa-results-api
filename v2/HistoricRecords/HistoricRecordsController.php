<?php

namespace IpswichJAFFARunningClubAPI\V2\HistoricRecords;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/CourseTypes/CourseTypes.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Distances/Distances.php';
require_once 'HistoricRecordsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;
use IpswichJAFFARunningClubAPI\V2\CourseTypes\CourseTypes as CourseTypes;
use IpswichJAFFARunningClubAPI\V2\Distances\Distances as Distances;

class HistoricRecordsController extends BaseController implements IRoute
{
	private $invalidCourseTypes = array(
		CourseTypes::MULTITERRAIN, 
		CourseTypes::FELL, 
		CourseTypes::CROSS_COUNTRY, 
		CourseTypes::PARK, 
		CourseTypes::VIRTUAL
	);

	private $standardDistances = array(
		Distances::FIVE_KILOMETRES, 
		Distances::FIVE_MILES, 
		Distances::TEN_KILOMETRES, 
		Distances::TEN_MILES, 
		Distances::HALF_MARATHON, 
		Distances::TWENTY_MILES, 
		Distances::MARATHON
	);

	public function __construct(string $route, $db)
	{
		parent::__construct($route, new HistoricRecordsDataAccess($db));
	}

	public function registerRoutes()
	{
		register_rest_route($this->namespace, '/results/historicrecords/distance/(?P<distanceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getHistoricClubRecords'),
			'args'                => array(
				'distanceId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		register_rest_route($this->namespace, '/results/historicrecords/category/(?P<categoryId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getHistoricClubRecordsByCategory'),
			'args'                => array(
				'categoryId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));
	}

	public function getHistoricClubRecords(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getAllRaceResults($request['distanceId']);

		// Group data in to catgeories and pick best times
		$distanceMeasurementUnitTypes = array(3, 4, 5);
		$categoryCode = 0;
		$records = array();
		foreach ($response as $item) {
			if ($item->courseTypeId != null && in_array($item->courseTypeId, $this->invalidCourseTypes)) {
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
			$previousRecord = $records[$categoryCode]['records'][$count - 1]['time'];
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

		return rest_ensure_response($records);
	}

	public function getHistoricClubRecordsByCategory(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getAllRaceResultsByCategory($request['categoryId']);

		// Group data in to distances and pick best times
		$distanceMeasurementUnitTypes = array(3, 4, 5);
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
			$previousRecord = $records[$distance][$count - 1]['time'];
			if (in_array($item->resultMeasurementUnitTypeId, $distanceMeasurementUnitTypes)) {
				if ($currentResult > $previousRecord) {
					$records[$distance][$count - 1]['endDate'] = $item->date;
					$records[$distance][] = array("distance" => $distance, "runnerId" => $item->id, "runnerName" => $item->name, "raceId" => $item->raceId, "raceDescription" => $item->raceDescription, "eventName" => $item->eventName, "time" => $item->result, "position" => $item->position, "startDate" => $item->date, "endDate" => date("Y-m-d"));
				}
			} else {
				if ($currentResult < $previousRecord) {
					$records[$distance][$count - 1]['endDate'] = $item->date;
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

	private function isValidCourseTypeForMeasuredDistance($courseTypeId)
	{
		return $courseTypeId == null || !in_array($courseTypeId, $this->invalidCourseTypes);
	}

	private function isStandardDistance($distanceId)
	{
		return in_array($distanceId, $this->standardDistances);
	}
}

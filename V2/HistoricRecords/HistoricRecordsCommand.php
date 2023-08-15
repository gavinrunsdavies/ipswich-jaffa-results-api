<?php

namespace IpswichJAFFARunningClubAPI\V2\HistoricRecords;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/CourseTypes/CourseTypes.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Distances/Distances.php';
require_once 'HistoricRecordsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;
use IpswichJAFFARunningClubAPI\V2\CourseTypes\CourseTypes as CourseTypes;
use IpswichJAFFARunningClubAPI\V2\Distances\Distances as Distances;

class HistoricRecordsCommand extends BaseCommand
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

	public function __construct($db)
	{
		parent::__construct(new HistoricRecordsDataAccess($db));
	}

	public function getHistoricClubRecordsByDistance(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getAllRaceResults($request['distanceId']);

		// Group data in to catgeories and pick best performances
		$distanceMeasurementUnitTypes = array(3, 4, 5);
		$categoryCode = 0;
		$records = array();
		foreach ($response as $item) {
			if (!$this->isValidCourseTypeForMeasuredDistance($item->courseTypeId)) {
				continue;
			}

			$categoryCode = $item->categoryCode;
			if (!array_key_exists($categoryCode, $records)) {
				$result = array("runnerId" => $item->id, "runnerName" => $item->name, "raceId" => $item->raceId, "raceDescription" => $item->raceDescription, "eventName" => $item->eventName, "time" => $item->result, "performance" => $item->performance, "position" => $item->position, "date" => $item->date);
				$records[$categoryCode] = array("id" => $item->categoryId, "code" => $item->categoryCode, "records" => array($result));

				continue;
			}

			$currentResult = $item->performance;
			$count = count($records[$categoryCode]['records']);
			$previousRecord = $records[$categoryCode]['records'][$count - 1]['performance'];
			if (in_array($item->resultMeasurementUnitTypeId, $distanceMeasurementUnitTypes)) {
				if ($currentResult > $previousRecord) {
					$records[$categoryCode]['records'][] = array("runnerId" => $item->id, "runnerName" => $item->name, "raceId" => $item->raceId, "raceDescription" => $item->raceDescription, "eventName" => $item->eventName, "time" => $item->result, "performance" => $item->performance, "position" => $item->position, "date" => $item->date);
				}
			} else {
				if ($currentResult < $previousRecord) {
					$records[$categoryCode]['records'][] = array("runnerId" => $item->id, "runnerName" => $item->name, "raceId" => $item->raceId, "raceDescription" => $item->raceDescription, "eventName" => $item->eventName, "time" => $item->result, "performance" => $item->performance, "position" => $item->position, "date" => $item->date);
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

			if (!$this->isValidDistance($item->distanceId)) {
				continue;
			}

			$distance = $item->distance;
			if (!array_key_exists($distance, $records)) {
				$record = array("runnerId" => $item->id, "runnerName" => $item->name, "raceId" => $item->raceId, "raceDescription" => $item->raceDescription, "eventName" => $item->eventName, "time" => $item->result, "performance" => $item->performance, "position" => $item->position, "date" => $item->date);
				$records[$distance] = array("id" => $item->distanceId, "code" => $item->distance, "records" => array($record));

				continue;
			}

			$currentResult = $item->performance;
			$count = count($records[$distance]['records']);
			$previousRecord = $records[$distance]['records'][$count - 1]['performance'];
			if (in_array($item->resultMeasurementUnitTypeId, $distanceMeasurementUnitTypes)) {
				if ($currentResult > $previousRecord) {
					$records[$distance]['records'][] = array("runnerId" => $item->id, "runnerName" => $item->name, "raceId" => $item->raceId, "raceDescription" => $item->raceDescription, "eventName" => $item->eventName, "time" => $item->result, "performance" => $item->performance, "position" => $item->position, "date" => $item->date);
				}
			} else {
				if ($currentResult < $previousRecord) {
					$records[$distance]['records'][] = array("runnerId" => $item->id, "runnerName" => $item->name, "raceId" => $item->raceId, "raceDescription" => $item->raceDescription, "eventName" => $item->eventName, "time" => $item->result, "performance" => $item->performance, "position" => $item->position, "date" => $item->date);
				}
			}
		}

		// Sort Record by distance
		ksort($records);

		return rest_ensure_response($records);
	}

	private function isValidCourseTypeForMeasuredDistance(?int $courseTypeId)
	{
		return $courseTypeId == null || !in_array($courseTypeId, $this->invalidCourseTypes);
	}

	private function isValidDistance(?int $distanceId)
	{
		return $distanceId > 0;
	}
}

<?php

namespace IpswichJAFFARunningClubAPI\V2\Records;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once 'RecordsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;

class RecordsCommand extends BaseCommand
{
	public function __construct($db)
	{
		parent::__construct(new RecordsDataAccess($db));
	}

	public function getOverallClubRecords(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getOverallClubRecords();

		return rest_ensure_response($response);
	}

	public function getClubRecords(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getClubRecordsByCategoryAndDistance($request['distanceId']);

		return rest_ensure_response($response);
	}

	public function getTopClubRecordHolders(\WP_REST_Request $request)
	{
		$distances = array(1, 2, 3, 4, 5, 7, 8);
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
					$runnerRecords[] = array(
						"eventId" => $record->eventId,
						"eventName" => $record->eventName,
						"date" => $record->date,
						"distance" => $record->distance,
						"result" => $record->result,
						"performance" => $record->performance,
						"categoryCode" => $record->categoryCode,
						"raceId" => $record->raceId,
						"description" => $record->description,
						"venue" => $record->venue
					);
				}
				$filteredRecordHolders[] = array("runner" => $runner, "records" => $runnerRecords);
			}
		}

		return rest_ensure_response($filteredRecordHolders);
	}
}

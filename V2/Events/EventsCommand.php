<?php

namespace IpswichJAFFARunningClubAPI\V2\Events;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once 'EventsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;

class EventsCommand extends BaseCommand
{
	public function __construct($db)
	{
		parent::__construct(new EventsDataAccess($db));
	}

	public function getEventRaceInsights(\WP_REST_Request $request)
	{
		$distanceMetrics = $this->dataAccess->getEventRaceInsightsByDistance($request['eventId']);

		$yearlyMetrics = $this->dataAccess->getEventRaceInsightsByYear($request['eventId']);

		$response = array(
			"years" => $yearlyMetrics,
			"distance" => $distanceMetrics
		);

		return rest_ensure_response($response);
	}

	public function getEvents(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getEvents();

		return rest_ensure_response($response);
	}

	public function getTopAttendees(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getEventTopAttendees($request['eventId']);

		// Array: year, name, count
		// Transform to JSON of form:
		// Data = {}
		// "year1" : {
		//	 	"name1" : count,
		// 		"name2" : count,
		// 		"name3" : count,
		// },
		// "year2" : {
		//	 	"name1" : count,
		// 		"name2" : count,
		// 		"name3" : count,
		// },

		$topAttendeesByYear = array();
		$lastYear = 0;
		foreach ($response as $item) {

			// Should only be the first time
			if (!array_key_exists($item->year, $topAttendeesByYear)) {
				$topAttendeesByYear[$item->year] = new class
				{
				};
			}

			// Build up cumulative values. Add all values from last year
			if ($lastYear != $item->year) {
				if ($lastYear != 0) {
					$topAttendeesByYear[$item->year] = clone $topAttendeesByYear[$lastYear];
				}
				$lastYear = $item->year;
			}

			$topAttendeesByYear[$item->year]->{$item->name} = $item->runningTotal;
		}

		return rest_ensure_response($topAttendeesByYear);
	}

	public function saveEvent(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->insertEvent($request['event']);

		return rest_ensure_response($response);
	}

	public function updateEvent(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->updateEvent($request['eventId'], $request['field'], $request['value']);

		return rest_ensure_response($response);
	}

	public function mergeEvents(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->mergeEvents($request['fromEventId'], $request['toEventId']);

		return rest_ensure_response($response);
	}

	public function deleteEvent(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->deleteEvent($request['eventId']);

		return rest_ensure_response($response);
	}

	public function isValidEventUpdateField(string $value, $request, string $key)
	{
		if ($value == 'name' || $value == 'website') {
			return true;
		} else {
			return new \WP_Error(
				'rest_invalid_param',
				sprintf('%s %d must be name or website only.', $key, $value),
				array('status' => 400)
			);
		}
	}
}

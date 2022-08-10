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

	public function getEventRaceInsights(int $eventId)
	{
		$distanceMetrics = $this->dataAccess->getEventRaceInsightsByDistance($eventId);

		$yearlyMetrics = $this->dataAccess->getEventRaceInsightsByYear($eventId);

		return array(
			"years" => $yearlyMetrics,
			"distance" => $distanceMetrics
		);
	}

	public function getEvents()
	{
		return $this->dataAccess->getEvents();
	}

	public function getEventTopAttendees(int $eventId)
	{
		$response = $this->dataAccess->getEventTopAttendees($eventId);

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

		return $topAttendeesByYear;
	}

	public function saveEvent($event)
	{
		return $this->dataAccess->insertEvent($event);
	}

	public function updateEvent(int $eventId, string $field, ?string $value)
	{
		return $this->dataAccess->updateEvent($eventId, $field, $value);
	}

	public function mergeEvents(int $fromEventId, int $toEventId)
	{
		return $this->dataAccess->mergeEvents($fromEventId, $toEventId);
	}

	public function deleteEvent(int $eventId)
	{
		return $this->dataAccess->deleteEvent($eventId);
	}
}

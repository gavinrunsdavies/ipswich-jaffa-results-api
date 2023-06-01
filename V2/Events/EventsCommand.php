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

		$topAttendees = $this->getEventTopAttendees($eventId);

		return array(
			"years" => $yearlyMetrics,
			"distance" => $distanceMetrics,
			"attendees" => $topAttendees
		);
	}

	public function getEvents()
	{
		return $this->dataAccess->getEvents();
	}

	private function getEventTopAttendees(int $eventId)
	{
		$response = $this->dataAccess->getEventTopAttendees($eventId);

		// Array: id, name, count
		// Transform to JSON of form:
		// Data = [
		// "{
		//	 	"name" : an example,
		// 		"id" : 123,
		// 		"count" : 22,
		//      "lastRaceDate" : "2001-01-14"
		// },
		// "{
		//	 	"name" : another name,
		// 		"id" : 45,
		// 		"count" : 19,
		//      "lastRaceDate" : "2019-09-14"
		// }]

		$topAttendees = array();
		foreach ($response as $item) {
			$topAttendees[] = $item;
		}

		return $topAttendees;
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

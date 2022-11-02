<?php

namespace IpswichJAFFARunningClubAPI\V2\Meetings;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once 'MeetingsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\Meetings\Meeting as Meeting;
use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;
use IpswichJAFFARunningClubAPI\V2\Races\RacesCommand as RacesCommand;

class MeetingsCommand extends BaseCommand
{
	private $racesCommand;

	public function __construct($db)
	{
		parent::__construct(new MeetingsDataAccess($db));

		$this->racesCommand = new RacesCommand($db);
	}

	public function getMeetings(int $eventId)
	{
		return $this->dataAccess->getMeetings($eventId);
	}

	public function getMeetingForRace(int $eventId, int $raceId)
	{
		$event = $this->dataAccess->getEvent($eventId);
		$race = $this->racesCommand->getRace($raceId);

		if (is_wp_error($race)) {
			return $race;
		}

		if ($race->meetingId > 0) {
			$meeting = $this->dataAccess->getMeeting($race->meetingId);
			$races = $this->dataAccess->getMeetingRaces($race->meetingId);
			$teams = $this->dataAccess->getMeetingTeams($race->meetingId);
			$results = $this->dataAccess->getMeetingResults($race->meetingId);
		} else {
			// Create a virtual meeting
			$meeting = new class($event->name, $race->date)
			{
				public $name;
				public $id = 0;
				public $fromDate;
				public $toDate;
				public $description = '';

				public function __construct($name, $date)
				{
					$this->name = $name;
					$this->fromDate = $date;
					$this->toDate = $date;
				}
			};
			
			$races = $this->dataAccess->getMeetingRacesForEventAndDate($eventId, $race->date);
		}

		if ($teams) {
			foreach ($teams as $team) {
				$team->results = array();
				if ($results) {
					foreach ($results as $result) {
						if ($team->teamId == $result->teamId) {
							$team->results[] = $result;
						}
					}
				}
			}
		}

		return new Meeting($meeting, $races, $teams, $event);
	}

	public function getMeeting(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->getMeeting($request['meetingId']);

		return rest_ensure_response($response);
	}

	public function getMeetingById(\WP_REST_Request $request)
	{
		$meeting = $this->dataAccess->getMeetingById($request['meetingId']);
		$races = $this->dataAccess->getMeetingRaces($request['meetingId']);
		$teams = $this->dataAccess->getMeetingTeams($request['meetingId']);
		$results = $this->dataAccess->getMeetingResults($request['meetingId']);

		if ($teams) {
			foreach ($teams as $team) {
				$team->results = array();
				if ($results) {
					foreach ($results as $result) {
						if ($team->teamId == $result->teamId) {
							$team->results[] = $result;
						}
					}
				}
			}
		}

		$response = new Meeting($meeting, $races, $teams, null);

		return rest_ensure_response($response);
	}

	public function getMeetingRaces(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->getMeetingRaces($request['meetingId']);

		return rest_ensure_response($response);
	}

	public function saveMeeting(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->insertMeeting($request['meeting'], $request['eventId']);

		return rest_ensure_response($response);
	}

	public function updateMeeting(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->updateMeeting($request['meetingId'], $request['field'], $request['value']);

		return rest_ensure_response($response);
	}

	public function deleteMeeting(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->deleteMeeting($request['meetingId']);

		return rest_ensure_response($response);
	}
}

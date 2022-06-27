<?php

namespace IpswichJAFFARunningClubAPI\V2\Meetings;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once 'MeetingsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;

class MeetingsCommand extends BaseCommand
{
	public function __construct($db)
	{
		parent::__construct(new MeetingsDataAccess($db));
	}

	public function getMeetings(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->getMeetings($request['eventId']);

		return rest_ensure_response($response);
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

		$response = new class($meeting, $races, $teams)
		{

			public $meeting;
			public $races;
			public $teams;

			public function __construct($meeting, $races, $teams)
			{
				$this->meeting = $meeting;
				$this->races = $races;
				$this->teams = $teams;
			}
		};

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

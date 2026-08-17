<?php

namespace IpswichJAFFARunningClubAPI\V2\Meetings;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once 'MeetingsDataAccess.php';
require_once 'Meeting.class.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Records/RecordsCommand.php';

use IpswichJAFFARunningClubAPI\V2\Meetings\Meeting as Meeting;
use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;
use IpswichJAFFARunningClubAPI\V2\Races\RacesCommand as RacesCommand;
use IpswichJAFFARunningClubAPI\V2\Records\RecordsCommand as RecordsCommand;

class MeetingsCommand extends BaseCommand
{
	private $racesCommand;
	private $recordsCommand;

	public function __construct($db)
	{
		parent::__construct(new MeetingsDataAccess($db));

		$this->racesCommand = new RacesCommand($db);
		$this->recordsCommand = new RecordsCommand($db);
	}

	public function getMeetings(int $eventId)
	{
		return $this->dataAccess->getMeetings($eventId);
	}

	public function getMeetingForRace(int $raceId)
	{
		$race = $this->racesCommand->getRace($raceId);

		if (is_wp_error($race)) {
			return new \WP_Error('rest_invalid_param', 'No race found for given Id', array('status' => 400));
		}

		$event = $this->dataAccess->getEvent($race->eventId);

		if ($race->meetingId > 0) {
			$meeting = $this->dataAccess->getMeetingById($race->meetingId);
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
				public $report = '';
				public $image = '';

				public function __construct($name, $date)
				{
					$this->name = $name;
					$this->fromDate = $date;
					$this->toDate = $date;
				}
			};
			
			$races = $this->dataAccess->getMeetingRacesForEventAndDate($race->eventId, $race->date);
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

	public function generateMeetingReport(int $meetingId)
	{
		$meeting = $this->dataAccess->getMeetingById($meetingId);

		if (is_wp_error($meeting)) {
			return $meeting;
		}

		if (empty($meeting) || !isset($meeting->id) || $meeting->id <= 0) {
			return new \WP_Error('rest_invalid_param', 'No meeting found for given Id', array('status' => 400));
		}

		$meetingRaces = $this->dataAccess->getMeetingRaces($meetingId);

		if (is_wp_error($meetingRaces)) {
			return $meetingRaces;
		}

		$recordContext = $this->getMeetingRecordContext($meetingRaces);

		$input = array(
			'meeting' => $meeting,
			'races' => $meetingRaces,
			'recordContext' => $recordContext
		);
return $input;
		return $this->getAIGeneratedMeetingReport($input);
	}

	public function saveMeetingReport(\WP_REST_Request $request)
	{
		$meetingId = (int) $request['meetingId'];
		$report = trim($request['report'] ?? '');
		$image = null;

		if (!empty($request['featured_image'])) {
			$image = trim($request['featured_image']);
		} elseif (!empty($request['image'])) {
			$image = trim($request['image']);
		}

		$response = $this->dataAccess->updateMeeting($meetingId, 'report', $report);
		if (is_wp_error($response)) {
			return $response;
		}

		if ($image !== null && $image !== '') {
			$response = $this->dataAccess->updateMeeting($meetingId, 'image', $image);
			if (is_wp_error($response)) {
				return $response;
			}
		}

		return rest_ensure_response($this->dataAccess->getMeetingById($meetingId));
	}

	private function getMeetingRecordContext($meetingRaces)
	{
		$recordContext = array();
		$processedDistanceIds = array();

		if (is_array($meetingRaces)) {
			foreach ($meetingRaces as $race) {
				$distanceId = isset($race->distanceId) ? (int) $race->distanceId : 0;
				$raceId = isset($race->id) ? (int) $race->id : 0;

				if ($distanceId > 0 && $raceId > 0 && !in_array($distanceId, $processedDistanceIds, true)) {
					$processedDistanceIds[] = $distanceId;
					$overallRecords = $this->recordsCommand->getOverallClubRecords((string) $distanceId);
					$ageCategoryRecords = $this->recordsCommand->getClubRecords($distanceId);

					$recordContext[] = (object) array(
						'distanceId' => $distanceId,
						'distance' => $race->distance ?? null,
						'overallRecords' => is_wp_error($overallRecords) ? array() : $overallRecords,
						'ageCategoryRecords' => is_wp_error($ageCategoryRecords) ? array() : $ageCategoryRecords,
					);
				}
			}
		}

		return $recordContext;
	}

	private function getAIGeneratedMeetingReport($reportData)
	{
		$apiKey = null;
		if (defined('OPENAI_API_HISTORIC_RACE_RESULTS')) {
			$apiKey = OPENAI_API_HISTORIC_RACE_RESULTS;
		} elseif (defined('OPEN_AI_API_SCERET__HISTORIC_RACE_RESULTS')) {
			$apiKey = OPEN_AI_API_SCERET__HISTORIC_RACE_RESULTS;
		}

		if (empty($apiKey)) {
			return new \WP_Error('open_ai_api_error', 'OpenAI API key is not configured.', array('status' => 500));
		}

		$instruction = $this->loadMeetingReportInstruction();

		$payload = array(
			'model' => 'gpt-4o-mini',
			'messages' => array(
				array(
					'role' => 'user',
					'content' => $instruction . "\n\nJSON input:\n" . json_encode($reportData)
				)
			),
			'temperature' => 0.4,
		);

		$ch = curl_init('https://api.openai.com/v1/chat/completions');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Authorization: Bearer ' . $apiKey,
		));

		$response = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpcode !== 200) {
			return new \WP_Error('open_ai_api_error', 'OpenAI API request failed', array('response' => $response));
		}

		$decoded = json_decode($response, true);
		if (isset($decoded['choices'][0]['message']['content'])) {
			return array(
				'success' => true,
				'content' => $decoded['choices'][0]['message']['content']
			);
		}

		return array(
			'success' => false,
			'error' => 'No response content found',
			'raw' => $decoded
		);
	}

	private function loadMeetingReportInstruction()
	{
		$instructionFile = IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Meetings/meeting-report-instruction.txt';
		if (file_exists($instructionFile)) {
			$content = file_get_contents($instructionFile);
			if ($content !== false) {
				return trim($content);
			}
		}

		return "Create an HTML meeting report for Ipswich JAFFA Running Club using the provided meeting and race data. Output should be friendly, concise and in HTML only. Use a short introduction naming the meeting and dates, then include highlights in a <ul> with <li> items. Mention key races, distances, venues, top performances, club wins, any notable PBs, and any record context available for the races' distances. If there are no races, say so clearly in a paragraph. Do not output markdown or JSON. Return valid HTML for display.";
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

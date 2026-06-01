<?php

namespace IpswichJAFFARunningClubAPI\V2\Races;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Meetings/MeetingsCommand.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Events/EventsCommand.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Volunteers/VolunteersDataAccess.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Results/ResultsCommand.php';
require_once 'RacesCommand.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\Events\EventsCommand as EventsCommand;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;
use IpswichJAFFARunningClubAPI\V2\Meetings\MeetingsCommand as MeetingsCommand;
use IpswichJAFFARunningClubAPI\V2\Volunteers\VolunteersDataAccess as VolunteersDataAccess;
use IpswichJAFFARunningClubAPI\V2\Results\ResultsCommand as ResultsCommand;

class RacesController extends BaseController implements IRoute
{
	private $meetingsCommand;
	private $eventsCommand;
	private $volunteersDataAccess;
	private $resultsCommand;

	public function __construct(string $route, $db)
	{
		parent::__construct($route, new RacesCommand($db));
		$this->meetingsCommand = new MeetingsCommand($db);
		$this->eventsCommand = new EventsCommand($db);
		$this->volunteersDataAccess = new VolunteersDataAccess($db);
		$this->resultsCommand = new ResultsCommand($db);
	}

	public function registerRoutes()
	{
		// Save Race - two routes
		register_rest_route($this->route, '/races', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this, 'saveRace')
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/races', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this, 'saveRace'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/races', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getRaces'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				),
				'date'           => array(
					'required'          => false,
					'validate_callback' => array($this, 'isValidDate')
				)
			)
		));

		// Get Race - two routes
		register_rest_route($this->route, '/races/(?P<id>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getRace'),
			'args'                 => array(
				'id'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		register_rest_route($this->route, '/races/latest', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getLatestRacesDetails'),
			'args'                 => array(
				'count'           => array(
					'required'          => false,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/races/(?P<id>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getRace'),
			'args'                 => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'id'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		register_rest_route($this->route, '/races/history', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getHistoricRaces'),
			'args'                => array(
				'date'           => array(
					'required'          => false,
					'validate_callback' => array($this, 'isValidDate')
				)
			)
		));

		// Update Race - two routes
		register_rest_route($this->route, '/races/(?P<id>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this, 'updateRace'),
			'args'                => array(
				'id'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidRaceUpdateField')
				),
				'value'           => array(
					'required'          => true
				)
			)
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/races/(?P<id>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this, 'updateRace'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'id'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidRaceUpdateField')
				),
				'value'           => array(
					'required'          => true
				)
			)
		));

		// Delete race
		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/races/(?P<raceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array($this, 'deleteRace'),
			'permission_callback' => array($this, 'isAuthorized'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'raceId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				)
			)
		));

		// TODO "race" not "races" in URL
		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/race/(?P<raceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array($this, 'deleteRace'),
			'permission_callback' => array($this, 'isAuthorized'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'raceId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				)
			)
		));

		register_rest_route($this->route, '/races/(?P<raceId>[\d]+)/results-page', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getRaceResultsPage'),
			'args'                => array(
				'raceId'            => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));
	}

	public function saveRace(\WP_REST_Request $request)
	{
		return rest_ensure_response($this->command->saveRace($request['race']));
	}

	public function getRaces(\WP_REST_Request $request)
	{
		$parameters = $request->get_query_params();
		return rest_ensure_response($this->command->getRaces($request['eventId'], $parameters['date']));
	}

	public function getRace(\WP_REST_Request $request)
	{
		return rest_ensure_response($this->command->getRace($request['id']));
	}

	public function updateRace(\WP_REST_Request $request)
	{
		return rest_ensure_response($this->command->updateRace($request['id'], $request['field'], $request['value']));
	}

	public function deleteRace(\WP_REST_Request $request)
	{
		return rest_ensure_response($this->command->deleteRace($request['raceId']));
	}

	public function getRaceResultsPage(\WP_REST_Request $request)
	{
		$race = $this->command->getRace($request['raceId']);
		if (is_wp_error($race)) {
			return rest_ensure_response($race);
		}

		$meetingData = $this->meetingsCommand->getMeetingForRace($request['raceId']);
		if (is_wp_error($meetingData)) {
			return rest_ensure_response($meetingData);
		}

		$volunteers = array();
		if (!empty($meetingData->meeting->id) && $meetingData->meeting->id > 0) {
			$volunteerResult = $this->volunteersDataAccess->getVolunteersForMeeting($meetingData->meeting->id);
			if (!is_wp_error($volunteerResult)) {
				$volunteers = $volunteerResult;
			}
		}

		$insightsData = $this->eventsCommand->getEventRaceInsights($race->eventId);
		$insightsResponse = $this->getMappedRaceInsightsData($insightsData);

		// Attach per-race results to each race object
		if (!empty($meetingData->races) && is_array($meetingData->races)) {
			foreach ($meetingData->races as $idx => $r) {
				$resultsForRace = $this->resultsCommand->getRaceResults($r->id);
				if (is_wp_error($resultsForRace)) {
					$meetingData->races[$idx]->results = array();
				} else {
					$meetingData->races[$idx]->results = $resultsForRace;
				}
			}
		}

		return rest_ensure_response(array(
			'meeting' => $meetingData->meeting,
			'races' => $meetingData->races,
			'teams' => $meetingData->teams,
			'volunteers' => $volunteers,
			'insights' => $insightsResponse
		));
	}

	private function getMappedRaceInsightsData($insightsData)
	{
		$insightsDistanceRequiredData = array_flip(['distance', 'count', 'meanPerformance', 'minPerformance', 'fastestRunnerId', 'fastestRunnerName', 'fastestRaceDate', 'maxPerformance']);
		$insightsAttendeesRequiredData = array_flip(['name', 'count', 'lastRaceDate']);
		$insightsYearsRequiredData = array_flip(['year', 'count', 'distance','minPerformance', 'meanPerformance', 'maxPerformance']);

		$insightsResponse = new stdClass();
		$insightsResponse->years     = array_map( fn($item) => array_intersect_key( (array)$item, $insightsYearsRequiredData ), $insightsData['years'] );
		$insightsResponse->distances = array_map( fn($item) => array_intersect_key( (array)$item, $insightsDistanceRequiredData ), $insightsData['distance'] );
		$insightsResponse->attendees = array_map( fn($item) => array_intersect_key( (array)$item, $insightsAttendeesRequiredData ), $insightsData['attendees'] );

		return $insightsResponse;
	}

	public function getLatestRacesDetails(\WP_REST_Request $request)
	{
		$parameters = $request->get_query_params();
		return rest_ensure_response($this->command->getLatestRacesDetails($parameters['count']));
	}

	public function getHistoricRaces(\WP_REST_Request $request)
	{
		$parameters = $request->get_query_params();
		return rest_ensure_response($this->command->getHistoricRaces($parameters['date']));
	}

	public function isValidRaceUpdateField(string $value, $request, string $key)
	{
		if (
			$value == 'event_id' ||
			$value == 'description' ||
			$value == 'course_type_id' ||
			$value == 'course_number' ||
			$value == 'area' ||
			$value == 'county' ||
			$value == 'country_code' ||
			$value == 'venue' ||
			$value == 'distance_id' ||
			$value == 'conditions' ||
			$value == 'meeting_id' ||
			$value == 'league_id' ||
			$value == 'grand_prix' ||
			$value == 'report'
		) {
			return true;
		} else {
			return new \WP_Error(
				'rest_invalid_param',
				sprintf('%s %s has an invalid value.', $key, $value),
				array('status' => 400)
			);
		}
	}
}

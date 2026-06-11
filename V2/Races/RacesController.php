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
                foreach ($volunteerResult as $volunteer) {
                    $volunteers[] = (object) array(
                        'runnerId' => isset($volunteer->runner_id) ? (int) $volunteer->runner_id : null,
                        'runnerName' => $volunteer->runner_name ?? null,
                        'volunteerRoleId' => isset($volunteer->volunteer_role_id) ? (int) $volunteer->volunteer_role_id : null,
                        'volunteerRoleName' => $volunteer->volunteer_role_name ?? null
                    );
                }
            }
        }

        $insightsData = $this->eventsCommand->getEventRaceInsights($race->eventId);
        $insightsResponse = $this->getMappedRaceInsightsData($insightsData);

        $event = (object) array(
            'id' => isset($meetingData->event->id) ? (int) $meetingData->event->id : null,
            'name' => $meetingData->event->name ?? null
        );

        $meeting = (object) array(
            'id' => isset($meetingData->meeting->id) ? (int) $meetingData->meeting->id : null,
            'name' => $meetingData->meeting->name ?? null,
            'fromDate' => $meetingData->meeting->fromDate ?? null,
            'toDate' => $meetingData->meeting->toDate ?? null
        );

        $races = array();
        if (!empty($meetingData->races) && is_array($meetingData->races)) {
            foreach ($meetingData->races as $raceItem) {
                // Get race results
                $raceResults = $this->resultsCommand->getRaceResults($raceItem->id);
                $normalizedResults = array();
                
                if (!is_wp_error($raceResults) && !empty($raceResults)) {
                    foreach ($raceResults as $result) {
                        $normalizedResults[] = (object) array(
                            'position' => isset($result->position) ? (int) $result->position : null,
                            'runnerId' => isset($result->runnerId) ? (int) $result->runnerId : null,
                            'runnerName' => $result->runnerName ?? null,
                            'performance' => isset($result->performance) ? (float) $result->performance : null,
                            'isPersonalBest' => isset($result->isPersonalBest) ? (int) $result->isPersonalBest : 0,
                            'isSeasonBest' => isset($result->isSeasonBest) ? (int) $result->isSeasonBest : 0,
                            'categoryCode' => $result->categoryCode ?? null,
                            'team' => isset($result->team) ? (int) $result->team : 0,
                            'info' => $result->info ?? null,
                            'percentageGrading' => isset($result->percentageGrading) ? (float) $result->percentageGrading : 0,
                            'percentageGradingBest' => isset($result->percentageGradingBest) ? (int) $result->percentageGradingBest : 0,
                            'standardType' => $result->standardType ?? null,
                            'runnerTotalResults' => isset($result->runnerTotalResults) ? (int) $result->runnerTotalResults : 0,
                            'runnerBadges' => is_array($result->runnerBadges) ? $result->runnerBadges : array(),
                            'previousPersonalBestPerformance' => isset($result->previousPersonalBestPerformance) ? (float) $result->previousPersonalBestPerformance : null,
                            'previousPersonalBestResult' => $result->previousPersonalBestResult ?? null
                        );
                    }
                }

                $races[] = (object) array(
                    'id' => isset($raceItem->id) ? (int) $raceItem->id : null,
                    'date' => $raceItem->date ?? null,
                    'description' => $raceItem->description ?? null,
                    'distance' => $raceItem->distance ?? null,
                    'courseType' => $raceItem->courseType ?? null,
                    'courseTypeId' => isset($raceItem->courseTypeId) ? (int) $raceItem->courseTypeId : null,
                    'conditions' => $raceItem->conditions ?? null,
                    'venue' => $raceItem->venue ?? null,
                    'county' => $raceItem->county ?? null,
                    'area' => $raceItem->area ?? null,
                    'countryCode' => $raceItem->countryCode ?? null,
                    'resultUnitTypeId' => isset($raceItem->resultUnitTypeId) ? (int) $raceItem->resultUnitTypeId : null,
                    'report' => $raceItem->report ?? null,
                    'results' => $normalizedResults
                );
            }
        }

        $teams = array();
        if (!empty($meetingData->teams) && is_array($meetingData->teams)) {
            foreach ($meetingData->teams as $team) {
                $teamResults = array();
                if (!empty($team->results) && is_array($team->results)) {
                    foreach ($team->results as $result) {
                        $teamResults[] = (object) array(
                            'teamOrder' => isset($result->teamOrder) ? (int) $result->teamOrder : null,
                            'runnerId' => isset($result->runnerId) ? (int) $result->runnerId : null,
                            'runnerName' => $result->runnerName ?? null,
                            'runnerResult' => $result->runnerResult ?? null
                        );
                    }
                }

                $teams[] = (object) array(
                    'teamName' => $team->teamName ?? null,
                    'teamCategory' => $team->teamCategory ?? null,
                    'teamPosition' => isset($team->teamPosition) ? (int) $team->teamPosition : null,
                    'teamResult' => $team->teamResult ?? null,
                    'results' => $teamResults
                );
            }
        }

        $otherRaces = array();
        $racesForEvent = $this->command->getRaces($race->eventId, null);
        if (!is_wp_error($racesForEvent) && !empty($racesForEvent)) {
            foreach ($racesForEvent as $raceItem) {
                $otherRaces[] = (object) array(
                    'id' => isset($raceItem->id) ? (int) $raceItem->id : null,
                    'date' => $raceItem->date ?? null,
                    'count' => isset($raceItem->count) ? (int) $raceItem->count : null
                );
            }
        }

        return rest_ensure_response(array(
            'event' => $event,
            'meeting' => $meeting,
            'races' => $races,
            'teams' => $teams,
            'volunteers' => $volunteers,
            'insights' => $insightsResponse,
            'otherRaces' => $otherRaces
        ));
    }

    private function getMappedRaceInsightsData($insightsData)
    {
        $insightsResponse = new \stdClass();
        $insightsResponse->years = array_map(
            fn($item) => (object) array(
                'year' => isset($item->year) ? (int) $item->year : null,
                'count' => isset($item->count) ? (int) $item->count : null,
                'distance' => $item->distance ?? null,
                'minPerformance' => isset($item->minPerformance) ? (float) $item->minPerformance : null,
                'meanPerformance' => isset($item->meanPerformance) ? (float) $item->meanPerformance : null,
                'maxPerformance' => isset($item->maxPerformance) ? (float) $item->maxPerformance : null
            ),
            $insightsData['years']
        );
        $insightsResponse->distances = array_map(
            fn($item) => (object) array(
                'distance' => $item->distance ?? null,
                'count' => isset($item->count) ? (int) $item->count : null,
                'meanPerformance' => isset($item->meanPerformance) ? (float) $item->meanPerformance : null,
                'minPerformance' => isset($item->minPerformance) ? (float) $item->minPerformance : null,
                'fastestRunnerId' => isset($item->fastestRunnerId) ? (int) $item->fastestRunnerId : null,
                'fastestRunnerName' => $item->fastestRunnerName ?? null,
                'fastestRaceDate' => $item->fastestRaceDate ?? null,
                'maxPerformance' => isset($item->maxPerformance) ? (float) $item->maxPerformance : null
            ),
            $insightsData['distance']
        );
        $insightsResponse->attendees = array_map(
            fn($item) => (object) array(
                'name' => $item->name ?? null,
                'count' => isset($item->count) ? (int) $item->count : null,
                'lastRaceDate' => $item->lastRaceDate ?? null
            ),
            $insightsData['attendees']
        );

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

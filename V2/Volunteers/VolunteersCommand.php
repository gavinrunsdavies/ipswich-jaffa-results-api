<?php

namespace IpswichJAFFARunningClubAPI\V2\Volunteers;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Races/RacesCommand.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Meetings/MeetingsDataAccess.php';
require_once 'VolunteersDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;
use IpswichJAFFARunningClubAPI\V2\Races\RacesCommand as RacesCommand;
use IpswichJAFFARunningClubAPI\V2\Meetings\MeetingsDataAccess as MeetingsDataAccess;

class VolunteersCommand extends BaseCommand
{
    private $racesCommand;
    private $meetingsDataAccess;

    public function __construct($db)
    {
        parent::__construct(new VolunteersDataAccess($db));
        $this->racesCommand = new RacesCommand($db);
        $this->meetingsDataAccess = new MeetingsDataAccess($db);
    }

    public function getVolunteersForMeeting(\WP_REST_Request $request)
    {
        $response = $this->dataAccess->getVolunteersForMeeting($request['meetingId']);

        return rest_ensure_response($response);
    }

    public function saveVolunteerAssignment(\WP_REST_Request $request)
    {
        $assignment = $request['volunteerAssignment'];

        $meetingId = $this->getOrCreateMeetingIdForAssignment($assignment);
        if (is_wp_error($meetingId)) {
            return $meetingId;
        }

        $assignment['meetingId'] = $meetingId;

        $response = $this->dataAccess->insertVolunteerAssignment($assignment);

        return rest_ensure_response($response);
    }

    public function saveVolunteerAssignments(\WP_REST_Request $request)
    {
        $assignments = $request['volunteerAssignments'];
        $results = array();

        foreach ($assignments as $assignment) {
            $meetingId = $this->getOrCreateMeetingIdForAssignment($assignment);
            if (is_wp_error($meetingId)) {
                return $meetingId;
            }

            $assignment['meetingId'] = $meetingId;

            $insertResult = $this->dataAccess->insertVolunteerAssignment($assignment);
            if (is_wp_error($insertResult)) {
                return $insertResult;
            }

            $results[] = $insertResult;
        }

        return rest_ensure_response($results);
    }

    private function getOrCreateMeetingIdForAssignment(array $assignment)
    {
        if (!empty($assignment['meetingId']) && is_numeric($assignment['meetingId'])) {
            return (int) $assignment['meetingId'];
        }

        if (empty($assignment['raceId']) || !is_numeric($assignment['raceId'])) {
            return new \WP_Error(
                'rest_invalid_param',
                'A volunteer assignment must include either meetingId or raceId.',
                array('status' => 400)
            );
        }

        $race = $this->racesCommand->getRace($assignment['raceId']);
        if (is_wp_error($race)) {
            return $race;
        }

        if (!empty($race->meetingId) && is_numeric($race->meetingId) && $race->meetingId > 0) {
            return (int) $race->meetingId;
        }

        $meeting = $this->meetingsDataAccess->insertMeeting(
            array(
                'fromDate' => $race->date,
                'toDate' => $race->date,
                'name' => $race->eventName
            ),
            $race->eventId
        );

        if (is_wp_error($meeting)) {
            return $meeting;
        }

        $meetingId = $this->extractMeetingId($meeting);
        if ($meetingId <= 0) {
            return new \WP_Error(
                'rest_invalid_response',
                'Unable to determine meeting id from meeting insert result.',
                array('status' => 500)
            );
        }

        return $meetingId;
    }

    private function extractMeetingId($meeting)
    {
        if (is_array($meeting) && count($meeting) > 0 && isset($meeting[0]->id)) {
            return (int) $meeting[0]->id;
        }

        if (is_object($meeting) && isset($meeting->id)) {
            return (int) $meeting->id;
        }

        return 0;
    }

    public function deleteVolunteerAssignment(\WP_REST_Request $request)
    {
        $response = $this->dataAccess->deleteVolunteerAssignment($request['meetingId'], $request['runnerId']);

        return rest_ensure_response($response);
    }
}

<?php

namespace IpswichJAFFARunningClubAPI\V2\Meetings;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';

use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class MeetingsDataAccess extends DataAccess
{
    public function getMeetings(int $eventId)
    {
        $sql = $this->resultsDatabase->prepare(
            'SELECT m.id as id, m.name as name, m.from_date as fromDate, m.to_date as toDate
            FROM `meeting` m
            WHERE m.event_id = %d
            ORDER BY m.from_date DESC', $eventId);

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getMeeting(int $meetingId)
    {
        $sql = $this->resultsDatabase->prepare(
            'SELECT m.id as id, m.name as name, m.from_date as fromDate, m.to_date as toDate, r.id as raceId, r.description as description
					FROM `meeting` m
					LEFT JOIN `race` r on r.meeting_id = m.id
					WHERE m.id = %d', $meetingId);

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getEvent(int $eventId)
    {
        $sql = $this->resultsDatabase->prepare(
            'SELECT e.id as id, e.name as name FROM `events` e WHERE e.id = %d',
            $eventId
        );

        return $this->executeResultQuery(__METHOD__, $sql);
    }

    private function getMeetingIdForEventOnDate(int $eventId, string $date)
    {
        $sql = $this->resultsDatabase->prepare(
            'SELECT r.meeting_id as meetingId FROM `race` r WHERE r.event_id = %d AND r.date = %s AND r.meeting_id IS NOT NULL LIMIT 1',
            $eventId,
            $date
        );

        $result = $this->executeResultQuery(__METHOD__, $sql);

        if (!empty($result) && isset($result->meetingId) && is_numeric($result->meetingId) && $result->meetingId > 0) {
            return (int) $result->meetingId;
        }

        return 0;
    }

    private function updateRacesWithMeetingId(int $eventId, string $date, int $meetingId)
    {
        $sql = $this->resultsDatabase->prepare(
            'UPDATE `race` SET meeting_id = %d WHERE event_id = %d AND date = %s AND (meeting_id IS NULL OR meeting_id = 0)',
            $meetingId,
            $eventId,
            $date
        );

        return $this->executeQuery(__METHOD__, $sql);
    }

    public function getMeetingById(int $meetingId)
    {
        $sql = $this->resultsDatabase->prepare(
            'SELECT m.id as id, m.name as name, m.from_date as fromDate, m.to_date as toDate, m.report as report, m.image as image
			FROM `meeting` m
			WHERE m.id = %d',
            $meetingId
            );

        return $this->executeResultQuery(__METHOD__, $sql);
    }

    public function getMeetingTeams(int $meetingId)
    {
        $sql = $this->resultsDatabase->prepare(
            'SELECT tr.id as teamId, tr.county_championship as countyChampionshipResult, tr.team_name as teamName, tr.category as teamCategory, tr.position as teamPosition, tr.result as teamResult
					FROM `team_results` tr
					WHERE tr.meeting_id = %d', $meetingId);

        return $this->executeResultsQuery(__METHOD__, $sql);  
    }

    public function getMeetingResults(int $meetingId)
    {
        $sql = $this->resultsDatabase->prepare(
            'SELECT tr.id as teamId, p.name as runnerName, p.id as runnerId, r.result as runnerResult, r.performance as runnerPerformance,
				r.position as runnerPosition, trr.order as teamOrder
				FROM `team_results` tr
				INNER JOIN `team_results_runners` trr ON tr.id = trr.team_result_id
				INNER JOIN `results` r on trr.result_id = r.id
				INNER JOIN `runners` p ON r.runner_id = p.id
				WHERE meeting_id = %d
				ORDER BY tr.position, trr.order', $meetingId);

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function insertMeeting($meeting, int $eventId)
    {
        $meetingId = $this->getMeetingIdForEventOnDate($eventId, $meeting['fromDate']);

        if ($meetingId > 0) {
            $updateResult = $this->updateRacesWithMeetingId($eventId, $meeting['fromDate'], $meetingId);
            if (is_wp_error($updateResult)) {
                return $updateResult;
            }

            return $this->getMeeting($meetingId);
        }

        $sql = $this->resultsDatabase->prepare(
            'INSERT INTO `meeting`(`event_id`, `from_date`, `to_date`, `name`) VALUES(%d, %s, %s, %s)',
            $eventId,
            $meeting['fromDate'],
            $meeting['toDate'],
            $meeting['name']
        );

        return $this->insertEntity(__METHOD__, $sql, function ($id) use ($eventId, $meeting) {
            $updateResult = $this->updateRacesWithMeetingId($eventId, $meeting['fromDate'], $id);
            if (is_wp_error($updateResult)) {
                return $updateResult;
            }

            return $this->getMeeting($id);
        });
    }

    public function updateMeeting(int $meetingId, string $field, string $value)
    {
        return $this->updateEntity(__METHOD__, 'meeting', $field, $value, $meetingId, function ($id) {
			return $this->getMeeting($id);
		});
    }

    public function deleteMeeting(int $meetingId)
    {
        $sql = $this->resultsDatabase->prepare('DELETE FROM meeting WHERE id = %d', $meetingId);

        return $this->executeQuery(__METHOD__, $sql);
    }

    public function getMeetingRaces(int $meetingId)
    {
        $sql = $this->resultsDatabase->prepare(
            "SELECT race.id, race.date, race.description, race.course_type_id as courseTypeId,
            race.report as report, d.result_unit_type_id as resultUnitTypeId,
            race.area, race.county, race.country_code AS countryCode,
            race.conditions, race.venue, d.id as distanceId, d.distance, ct.description AS courseType
            FROM `race` race
            LEFT JOIN `distance` d ON race.distance_id = d.id
            LEFT JOIN `course_type` ct ON race.course_type_id = ct.id
            WHERE race.meeting_id = %d
            ORDER BY race.date, race.description",
            $meetingId
        );

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getMeetingRacesForEventAndDate(int $eventId, string $date)
    {
        $sql = $this->resultsDatabase->prepare(
            "SELECT race.id, race.date, race.description, race.course_type_id as courseTypeId,
            race.report as report, d.result_unit_type_id as resultUnitTypeId,
            race.area, race.county, race.country_code AS countryCode,
            race.conditions, race.venue, d.id as distanceId, d.distance, ct.description AS courseType
            FROM `race` race
            LEFT JOIN `distance` d ON race.distance_id = d.id
            LEFT JOIN `course_type` ct ON race.course_type_id = ct.id
            WHERE race.event_id = %d AND race.date = '%s'
            ORDER BY race.description",
            $eventId,
            $date
        );

        return $this->executeResultsQuery(__METHOD__, $sql);
    }
}


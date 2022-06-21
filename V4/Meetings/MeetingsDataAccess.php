<?php

namespace IpswichJAFFARunningClubAPI\V4\Meetings;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/DataAccess.php';

use IpswichJAFFARunningClubAPI\V4\DataAccess as DataAccess;

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

    public function getMeetingById(int $meetingId)
    {
        $sql = $this->resultsDatabase->prepare(
            'SELECT m.id as id, m.name as name, m.from_date as fromDate, m.to_date as toDate, m.report as report
					FROM `meeting` m
					WHERE m.id = %d', $meetingId);

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
            'SELECT tr.id as teamId, p.name as runnerName, p.id as runnerId, r.result as runnerResult,
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
        $sql = $this->resultsDatabase->prepare('insert into `meeting`(`event_id`, `from_date`, `to_date`, `name`) values(%d, %s, %s, %s)', $eventId, $meeting['fromDate'], $meeting['toDate'], $meeting['name']);

        $result = $this->resultsDatabase->query($sql);

        if ($result) {
            return $this->getMeeting($this->resultsDatabase->insert_id);
        }

        return new \WP_Error(__METHOD__,
            'Unknown error in inserting meeting in to the database', array('status' => 500));
    }

    public function updateMeeting(int $meetingId, string $field, string $value)
    {
        if ($field == 'name' || $field == 'from_date' || $field == 'to_date') {
            $result = $this->resultsDatabase->update(
                'meeting',
                array(
                    $field => $value,
                ),
                array('id' => $meetingId),
                array(
                    '%s',
                ),
                array('%d')
            );

            if ($result) {
                return $this->getMeeting($meetingId);
            }

            return new \WP_Error(__METHOD__,
                'Unknown error in updating meeting in to the database', array('status' => 500));
        }

        return new \WP_Error(__METHOD__,
            'Field in meeting may not be updated', array('status' => 400));
    }

    public function deleteMeeting(int $meetingId)
    {
        $sql = $this->resultsDatabase->prepare('DELETE FROM meeting WHERE id = %d', $meetingId);

        return $this->executeQuery(__METHOD__, $sql);
    }

    public function getMeetingRaces(int $meetingId)
    {
        $sql = $this->resultsDatabase->prepare(
            'SELECT ra.id, ra.date, ra.description, ra.course_type_id as courseTypeId, ra.report as report
					FROM `race` ra
					WHERE ra.meeting_id = %d
					ORDER BY ra.date, ra.description', $meetingId);

        return $this->executeResultsQuery(__METHOD__, $sql);
    }
}

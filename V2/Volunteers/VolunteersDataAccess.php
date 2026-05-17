<?php

namespace IpswichJAFFARunningClubAPI\V2\Volunteers;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';

use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class VolunteersDataAccess extends DataAccess
{
    public function getVolunteersForMeeting(int $meetingId)
    {
        $sql = $this->resultsDatabase->prepare(
            'SELECT r.id as runner_id, r.name as runner_name, vr.id as volunteer_role_id, vr.role as volunteer_role_name FROM meeting_volunteers mv JOIN runners r ON mv.runner_id = r.id JOIN volunteer_roles vr ON mv.volunteer_role_id = vr.id WHERE mv.meeting_id = %d ORDER BY r.name',
            $meetingId
        );

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function insertVolunteerAssignment($assignment)
    {
        $sql = $this->resultsDatabase->prepare(
            'INSERT INTO meeting_volunteers (meeting_id, runner_id, volunteer_role_id) VALUES (%d, %d, %d)',
            $assignment['meetingId'],
            $assignment['runnerId'],
            $assignment['volunteerRoleId']
        );

        return $this->executeInsertQuery(__METHOD__, $sql);
    }

    public function insertVolunteerAssignments(array $assignments)
    {
        $results = array();

        foreach ($assignments as $assignment) {
            $result = $this->insertVolunteerAssignment($assignment);
            if (is_wp_error($result)) {
                return $result;
            }

            $results[] = $result;
        }

        return $results;
    }

    public function deleteVolunteerAssignment(int $meetingId, int $runnerId)
    {
        $sql = $this->resultsDatabase->prepare(
            'DELETE FROM meeting_volunteers WHERE meeting_id = %d AND runner_id = %d',
            $meetingId,
            $runnerId
        );

        return $this->executeUpdateQuery(__METHOD__, $sql);
    }
}

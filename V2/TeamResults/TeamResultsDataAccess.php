<?php

namespace IpswichJAFFARunningClubAPI\V2\TeamResults;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';

use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class TeamResultsDataAccess extends DataAccess
{
    public function deleteTeamResult(int $teamResultId)
    {
        $sql = $this->resultsDatabase->prepare(
            'DELETE FROM team_results WHERE id = %d;
			DELETE FROM team_results_runners WHERE team_result_id = %d;',
            $teamResultId,
            $teamResultId
        );

        return $this->executeQuery(__METHOD__, $sql);
    }

    public function getTeamResult(int $teamResultId)
    {
        $sql = $this->resultsDatabase->prepare(
            'SELECT tr.id as teamId, p.name as runnerName, p.id as runnerId, r.result as runnerResult,
			r.position as runnerPosition, trr.order as teamOrder
			FROM `team_results` tr
			INNER JOIN `team_results_runners` trr ON tr.id = trr.team_result_id
			INNER JOIN `results` r on trr.result_id = r.id
			INNER JOIN `runners` p ON r.runner_id = p.id
			WHERE tr.id = %d
			ORDER BY tr.position, trr.order',
            $teamResultId
        );

        return $this->executeResultQuery(__METHOD__, $sql);
    }

    public function insertTeamResult(string $name, string $category, string $result, int $position, int $meetingId, $resultIds)
    {
        $sql = $this->resultsDatabase->prepare(
            'INSERT INTO team_results (`team_name`, `category`, `result`, `position`, `meeting_id`) VALUES(%s, %s, %s, %d, %d);',
            $name,
            $category,
            $result,
            $position,
            $meetingId
        );

        $result = $this->executeQuery(__METHOD__, $sql);

        if (is_wp_error($result)) {
            return $result;
        }

        $teamResultId = $this->resultsDatabase->insert_id;

        $values = array();
        $order = 1;
        foreach ($resultIds as $resultId) {
            $values[] = "($teamResultId, $resultId, $order)";
            $order++;
        }

        $sql =
            "INSERT INTO team_results_runners (`team_result_id`, `result_id`, `order`)
                VALUES " . implode(",", $values);

        $result = $this->executeQuery(__METHOD__, $sql);

        if (is_wp_error($result)) {
            return $result;
        }

        return $this->getTeamResult($teamResultId);
    }
}

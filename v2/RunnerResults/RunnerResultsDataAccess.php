<?php

namespace IpswichJAFFARunningClubAPI\V2\RunnerResults;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Constants/Rules.php';

use IpswichJAFFARunningClubAPI\V2\Constants\Rules as Rules;
use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class RunnerResultsDataAccess extends DataAccess
{
    public function getRunnerResults()
    {
        $sql = 'SELECT id, code, description, sex_id as sexId, default_category as isDefault					 
			    FROM category
                WHERE id > 0
                ORDER BY sex_id, default_category desc, age_greater_equal';

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getHeadToHeadResults($runnerIds)
    {
        $sql = "select
					  e.id as eventId,
					  e.name as eventName,
					  ra.distance_id as distanceId,
					  ra.date as date,
					  ra.description as raceName,
					  ra.id as raceId,";

        $selectSql = "";
        $fromSql = " from results r1";
        $whereSql = "where ";
        for ($i = 1; $i <= count($runnerIds); $i++) {
            $selectSql .= "r$i.position as position$i, ";
            $selectSql .= "r$i.result as time$i, ";
            $selectSql .= "r$i.percentage_grading_2015 as percentageGrading$i";

            if ($i != count($runnerIds)) {
                $selectSql .= ", ";
            }

            if ($i > 1) {
                $fromSql .= " inner join results r$i on r1.race_id = r$i.race_id ";
            }

            $whereSql .= "r$i.runner_id = " . $runnerIds[$i - 1] . " AND r$i.position > 0";
            if ($i != count($runnerIds)) {
                $whereSql .= " AND ";
            }
        }

        $joinSql = "inner join race ra
						on ra.id = r1.race_id ";
        $joinSql .= "inner join events e
					    on ra.event_id = e.id ";

        $orderSql = " order by date";

        $sql = $sql . $selectSql . $fromSql . $joinSql . $whereSql . $orderSql;

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getMemberResults(int $runnerId)
    {
        $sql = $this->jdb->prepare("select
					  e.id as eventId,
					  e.name as eventName,
					  ra.distance_id as distanceId,
					  r.id as id,
					  ra.date as date,
					  ra.description as raceName,
					  ra.id as raceId,
					  r.position as position,
					  r.result as time,
					  r.result as result,
					  r.personal_best as isPersonalBest,
					  r.season_best as isSeasonBest,
					  st.name as standard,
					  r.info as info,
					  CASE
					   WHEN ra.date >= '%s' THEN r.percentage_grading_2015
					   ELSE r.percentage_grading
					  END as percentageGrading,
					  r.percentage_grading_best as percentageGradingBest,
					  ra.course_type_id AS courseTypeId
					from
					  runners p
					INNER JOIN results r
					  ON r.runner_id = p.id
					INNER JOIN race ra
					  ON ra.id = r.race_id
					INNER JOIN events e
					  ON ra.event_id = e.id
					LEFT JOIN standard_type st
					  ON r.standard_type_id = st.id
					where
					  r.runner_id = %d
					ORDER BY date DESC", Rules::START_OF_2015_AGE_GRADING, $runnerId);

        return $this->executeResultsQuery(__METHOD__, $sql);
    } 

    public function getMemberPBResults(int $runnerId)
    {

        $sql = $this->jdb->prepare("select
					  e.id as eventId,
					  e.name as eventName,
					  ra.distance_id as distanceId,
					  d.distance as distance,
					  r.id as id,
					  ra.date as date,
					  ra.description as raceName,
					  ra.id as raceId,
					  r.position as position,
					  r.result as time,
					  r.result as result,
					  r.info as info,
					  CASE
					   WHEN ra.date >= '%s' THEN r.percentage_grading_2015
					   ELSE r.percentage_grading
					  END as percentageGrading
					from
					  runners p
					INNER JOIN results r
					  ON r.runner_id = p.id
					INNER JOIN race ra
					  ON ra.id = r.race_id
					INNER JOIN events e
					  ON ra.event_id = e.id
					INNER JOIN distance d
					  ON ra.distance_id = d.id
					INNER JOIN(
						select ra.distance_id as distanceId, MIN(r.result) as pb
						from
						  results r
						inner join race ra on ra.id = r.race_id
						where
						  r.runner_id = %d
						  and r.personal_best = 1
						  and r.result != '00:00:00'
						  and r.result != ''
						group by ra.distance_id
					) t on r.result = t.pb and ra.distance_id = t.distanceId
					where
					  r.runner_id = %d and r.personal_best = 1
					ORDER BY r.result ASC", Rules::START_OF_2015_AGE_GRADING, $runnerId, $runnerId);

        return $this->executeResultsQuery(__METHOD__, $sql);
    }
}

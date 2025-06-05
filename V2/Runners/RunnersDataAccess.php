<?php

namespace IpswichJAFFARunningClubAPI\V2\Runners;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . "V2/DataAccess.php";

use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class RunnersDataAccess extends DataAccess
{
    public function getRunners(bool $loggedIn = true)
    {
        if ($loggedIn === true) {
            $sql = "SELECT
				r.id,
				r.name,
				r.sex_id as 'sexId',
				r.dob as 'dateOfBirth',
				s.sex
				FROM `runners` r
				INNER JOIN 	`sex` s ON r.sex_id = s.id
				ORDER BY r.name";
        } else {
            $sql = "SELECT
				r.id,
				r.name,
				r.sex_id as 'sexId',
				s.sex
				FROM `runners` r
				INNER JOIN 	`sex` s ON r.sex_id = s.id
				ORDER BY r.name";
        }

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getRunner(int $runnerId)
    {
        $sql = $this->resultsDatabase->prepare(
            "SELECT 
                r.id,
                r.name,
                r.sex_id AS 'sexId',
                s.sex,
                c.code AS 'ageCategory',
                IFNULL(TIMESTAMPDIFF(YEAR, r.dob, last_race.last_race_date), 0) AS ageAtLastRace
                FROM 
                runners r
                INNER JOIN sex s ON r.sex_id = s.id
                LEFT JOIN (
                    SELECT 
                    res.runner_id,
                    MAX(race.date) AS last_race_date
                    FROM 
                    results res
                    INNER JOIN race ON res.race_id = race.id
                    GROUP BY 
                    res.runner_id
                ) last_race ON last_race.runner_id = r.id
                INNER JOIN category c 
                    ON r.sex_id = c.sex_id 
                    AND (
                    (TIMESTAMPDIFF(YEAR, r.dob, CURDATE()) >= c.age_greater_equal 
                    AND TIMESTAMPDIFF(YEAR, r.dob, CURDATE()) < c.age_less_than)
                    OR r.dob = '0000-00-00'
                    )
                WHERE 
                r.id = %d
                LIMIT 1;
                ",
            $runnerId
        );

        return $this->executeResultQuery(__METHOD__, $sql);
    }

    public function insertRunner($runner)
    {
        $sql = $this->resultsDatabase->prepare(
            "INSERT INTO runners (`name`, `dob`, `sex_id`) VALUES(%s, %s, %d);",
            $runner["name"],
            $runner["dateOfBirth"],
            $runner["sexId"]
        );

        return $this->insertEntity(__METHOD__, $sql, function ($id) {
            return $this->getRunner($id);
        });
    }

    public function deleteRunner(int $id)
    {
        // Check whether their are any results for this runner already.
        $sql = $this->resultsDatabase->prepare(
            "SELECT COUNT(`id`) FROM results WHERE runner_id = %d LIMIT 1",
            $id
        );

        $exists = $this->resultsDatabase->get_var($sql);

        if ($exists != 0) {
            return new \WP_Error(
                __METHOD__,
                "Runner cannot be deleted; a number results are associated with this runner. Delete the existing results for this runner and try again.",
                ["status" => 409]
            );
        }

        $sql = $this->resultsDatabase->prepare(
            "DELETE FROM runners WHERE id = %d",
            $id
        );

        return $this->executeQuery(__METHOD__, $sql);
    }

    public function updateRunner(int $runnerId, string $field, string $value)
    {
        return $this->updateEntity(
            __METHOD__,
            "runners",
            $field,
            $value,
            $runnerId,
            function ($id) {
                return $this->getRunner($id);
            }
        );
    }

    public function getStandardCertificates($runnerId)
    {
        $sql = $this->resultsDatabase->prepare(
            "SELECT st.name, e.name as 'event', d.distance, r.result, r.performance, DATE_FORMAT( ra.date, '%%M %%e, %%Y' ) as 'date'
								  FROM standard_certificates sc
								  INNER JOIN results r ON sc.result_id = r.id
								  INNER JOIN standard_type st ON r.standard_type_id = st.id
								  INNER JOIN race ra ON ra.id = r.race_id
								  INNER JOIN events e ON e.id = ra.event_id
								  INNER JOIN distance d ON d.id = ra.distance_id
								  where r.runner_id = %d and ra.date > '2010-01-01'
								  order by st.name desc",
            $runnerId
        );

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getRunnerRankings($runnerId, $sexId, $distances)
    {
        $results = [];

        foreach ($distances as $distanceId) {
            $this->resultsDatabase->query("SET @cnt := 0");

            $rawSql = "
		SELECT * FROM (
		    SELECT * FROM (
		        SELECT @cnt := IF(@cnt IS NULL, 1, @cnt + 1) AS rank, Ranking.*
		        FROM (
		            SELECT @cnt := NULL,		      
	                        p.id as runnerId,
		                ra3.distance_id as distanceId,
		                e.Name as event,
		                ra3.date,
		                r.result,
		                r.performance as performance
		            FROM results AS r
		            JOIN (
		                SELECT r1.runner_id, r1.performance, MIN(ra1.date) AS earliest
		                FROM results AS r1
		                INNER JOIN race ra1 ON r1.race_id = ra1.id
		                INNER JOIN distance d ON ra1.distance_id = d.id
		                JOIN (
		                    SELECT r2.runner_id, MIN(r2.performance) as best
		                    FROM results r2
		                    INNER JOIN race ra2 ON ra2.id = r2.race_id
		                    INNER JOIN runners p2 ON r2.runner_id = p2.id
		                    INNER JOIN distance d ON ra2.distance_id = d.id
		                    WHERE r2.performance > 0
		                      AND ra2.distance_id = %d
		                      AND (ra2.course_type_id NOT IN (2, 4, 5, 7) OR ra2.course_type_id IS NULL)
		                      AND p2.sex_id = %d
		                    GROUP BY r2.runner_id
		                ) AS rt
		                ON r1.runner_id = rt.runner_id AND r1.performance = rt.best
		                GROUP BY r1.runner_id, r1.performance
		                ORDER BY r1.performance
		                LIMIT 100
		            ) as rd
		            ON r.runner_id = rd.runner_id AND r.performance = rd.performance
		            INNER JOIN race ra3 ON r.race_id = ra3.id AND ra3.date = rd.earliest
		            INNER JOIN runners p ON r.runner_id = p.id
		            INNER JOIN events e ON ra3.event_id = e.id
		            ORDER BY rd.performance ASC
		            LIMIT 100
		        ) Ranking
		    ) RankedWithRank
		    WHERE runnerId = %d
		) FinalRankedResults;
	        ";

            $sql = $this->resultsDatabase->prepare(
                $rawSql,
                $distanceId,
                $sexId,
                $runnerId
            );

            $ranking = $this->executeResultQuery(__METHOD__, $sql);

            if (!is_wp_error($ranking)) {
                $results[] = $ranking;
            }
        }

        return $results;
    }
}

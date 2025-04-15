<?php

namespace IpswichJAFFARunningClubAPI\V2\Runners;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';

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
        $sql = $this->resultsDatabase->prepare("select r.id, r.name, r.sex_id as 'sexId', r.dob as 'dateOfBirth', 0 as 'isCurrentMember', s.sex, c.code as 'ageCategory'
				FROM
				runners r, category c, sex s
				WHERE r.id = %d
				AND r.sex_id = s.id
				AND r.sex_id = c.sex_id
				AND (
					(TIMESTAMPDIFF(YEAR, r.dob, CURDATE()) >= c.age_greater_equal AND TIMESTAMPDIFF(YEAR, r.dob, CURDATE()) < c.age_less_than)
					OR r.dob= '0000-00-00'
				)
				LIMIT 1", $runnerId);

        return $this->executeResultQuery(__METHOD__, $sql);
    }

    public function insertRunner($runner)
    {
        $sql = $this->resultsDatabase->prepare('INSERT INTO runners (`name`, `dob`, `sex_id`) VALUES(%s, %s, %d);', $runner['name'], $runner['dateOfBirth'], $runner['sexId']);

        return $this->insertEntity(__METHOD__, $sql, function ($id) {
			return $this->getRunner($id);
		});
    }

    public function deleteRunner(int $id)
    {
        // Check whether their are any results for this runner already.
        $sql = $this->resultsDatabase->prepare('SELECT COUNT(`id`) FROM results WHERE runner_id = %d LIMIT 1', $id);

        $exists = $this->resultsDatabase->get_var($sql);

        if ($exists != 0) {
            return new \WP_Error(__METHOD__,
                'Runner cannot be deleted; a number results are associated with this runner. Delete the existing results for this runner and try again.', array('status' => 409));
        }

        $sql = $this->resultsDatabase->prepare('DELETE FROM runners WHERE id = %d', $id);

        return $this->executeQuery(__METHOD__, $sql);
    }

    public function updateRunner(int $runnerId, string $field, string $value)
    {
        return $this->updateEntity(__METHOD__, 'runners', $field, $value, $runnerId, function ($id) {
			return $this->getRunner($id);
		});
    }

    public function getStandardCertificates($runnerId)
    {
        $sql = $this->resultsDatabase->prepare("SELECT st.name, e.name as 'event', d.distance, r.result, r.performance, DATE_FORMAT( ra.date, '%%M %%e, %%Y' ) as 'date'
								  FROM standard_certificates sc
								  INNER JOIN results r ON sc.result_id = r.id
								  INNER JOIN standard_type st ON r.standard_type_id = st.id
								  INNER JOIN race ra ON ra.id = r.race_id
								  INNER JOIN events e ON e.id = ra.event_id
								  INNER JOIN distance d ON d.id = ra.distance_id
								  where r.runner_id = %d and ra.date > '2010-01-01'
								  order by st.name desc", $runnerId);

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

   public function getRunnerRankings($runnerId, $sexId, $distances)
   {
	    $results = [];
	
	    foreach ($distances as $distanceId) {
	        // Reset the rank counter for MySQL session variable
	        $this->resultsDatabase->query("SET @cnt := 0");
	
	        $rawSql = "
	            SELECT * FROM (
	                SELECT 
	                    @cnt := @cnt + 1 AS rank,
	                    r.id AS resultId,
	                    e.name AS event,
	                    r.position,
	                    r.result,
	                    r.info,
	                    ra.date,
	                    c.code,
	                    p.name AS name,
	                    r.runner_id,
	                    ra.distance_id AS distanceId
	                FROM results r
	                JOIN (
	                    SELECT r2.runner_id, MIN(r2.result) AS quickest
	                    FROM results r2
	                    INNER JOIN race ra2 ON r2.race_id = ra2.id
	                    INNER JOIN events e ON ra2.event_id = e.id
	                    INNER JOIN distance d ON ra2.distance_id = d.id
	                    INNER JOIN runners p2 ON r2.runner_id = p2.id
	                    WHERE r2.result NOT IN ('00:00:00', '')
	                      AND d.id = %d
	                      AND p2.sex_id = %d
	                    GROUP BY r2.runner_id
	                ) AS best_times 
	                    ON r.runner_id = best_times.runner_id 
	                    AND r.result = best_times.quickest
	                INNER JOIN race ra ON ra.id = r.race_id
	                INNER JOIN events e ON ra.event_id = e.id
	                INNER JOIN runners p ON r.runner_id = p.id
	                INNER JOIN category c ON r.category_id = c.id
	                WHERE r.result NOT IN ('00:00:00', '')
	                  AND ra.distance_id = %d
	                ORDER BY r.result ASC
	                LIMIT 100
	            ) AS RankedResults
	            WHERE runner_id = %d
	        ";
	
	        // Now use WordPress-style prepare
	        $sql = $this->resultsDatabase->prepare($rawSql, $distanceId, $sexId, $distanceId, $runnerId);
	
	        // Assuming you have a helper to run and return results
	        $ranking = $this->executeResultQuery(__METHOD__, $sql);
	
	        if (!is_wp_error($ranking)) {
	            $results[] = $ranking;
	        }
	    }
	
	    return $results;
	}
}

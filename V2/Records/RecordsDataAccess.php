<?php

namespace IpswichJAFFARunningClubAPI\V2\Records;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';

use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class RecordsDataAccess extends DataAccess
{
    public function getOverallClubRecords(string $distanceIds)
    {		
        $sql = $this->resultsDatabase->prepare("
				SELECT d.distance, r.runner_id as runnerId, p.Name as runnerName, s.sex, e.id as eventId, e.Name as eventName, ra.date, r.result, r.performance, ra.id as raceId, ra.description, ra.venue
				FROM results AS r
                INNER JOIN race ra ON r.race_id = ra.id                
				JOIN (
				  SELECT r1.runner_id, r1.performance, MIN(ra1.date) AS earliest
				  FROM results AS r1
                  INNER JOIN race ra1 on r1.race_id = ra1.id
                  INNER JOIN runners p1 ON r1.runner_id = p1.id
				  JOIN (
					SELECT 
                    CASE
                        WHEN d.result_unit_type_id = 1 THEN MAX(r2.performance)
                        ELSE MIN(r2.performance)
                    END as best,
                    p2.sex_id,
                    ra.distance_id
					FROM results r2
					INNER JOIN race ra
					ON r2.race_id = ra.id
					INNER JOIN events e
					ON ra.event_id = e.id
					INNER JOIN `distance` d
					ON ra.distance_id = d.id
					INNER JOIN `runners` p2
					ON r2.runner_id = p2.id
					WHERE r2.performance > 0 and d.id IN ($distanceIds) and r2.category_id <> 0
          				AND (ra.course_type_id NOT IN (2, 4, 5, 7, 9) OR ra.course_type_id IS NULL)
					GROUP BY p2.sex_id, ra.distance_id
				   ) AS rt
				   ON r1.performance = rt.best and p1.sex_id = rt.sex_id AND rt.distance_id = ra1.distance_id
				   GROUP BY r1.runner_id, r1.performance, p1.sex_id, ra1.distance_id
				   ORDER BY r1.performance asc
				) as rd
				ON r.runner_id = rd.runner_id AND r.performance = rd.performance AND ra.date = rd.earliest
				INNER JOIN events e ON ra.event_id = e.id
				INNER JOIN runners p ON r.runner_id = p.id
                INNER JOIN sex s ON p.sex_id = s.id
                INNER JOIN distance d ON ra.distance_id = d.id
				WHERE ra.distance_id IN ($distanceIds)
				ORDER BY ra.distance_id, p.sex_id");

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getClubRecordsByCategoryAndDistance(int $distanceId)
    {
        $sql = $this->resultsDatabase->prepare("
				SELECT d.distance, r.runner_id as runnerId, p.Name as runnerName, e.id as eventId, e.Name as eventName, ra.date, r.performance, r.result, c.code as categoryCode, ra.id as raceId, ra.description, ra.venue
				FROM results AS r
                INNER JOIN race ra ON r.race_id = ra.id
				JOIN (
				  SELECT r1.runner_id, r1.performance, MIN(ra1.date) AS earliest
				  FROM results AS r1
                  INNER JOIN race ra1 on r1.race_id = ra1.id
				  JOIN (
					SELECT 
                    CASE
                        WHEN d.result_unit_type_id = 1 THEN MAX(r2.performance)
                        ELSE MIN(r2.performance)
                    END as best,
                    r2.category_id
					FROM results r2
					INNER JOIN race ra
					ON r2.race_id = ra.id
					INNER JOIN events e
					ON ra.event_id = e.id
					INNER JOIN `distance` d
					ON ra.distance_id = d.id
					INNER JOIN `runners` p2
					ON r2.runner_id = p2.id
					WHERE r2.performance > 0 and d.id = %d and r2.category_id <> 0
                    AND (ra.course_type_id NOT IN (2, 4, 5, 7, 9) OR ra.course_type_id IS NULL)
					GROUP BY r2.category_id
				   ) AS rt
				   ON r1.performance = rt.best and r1.category_id = rt.category_id
				   GROUP BY r1.runner_id, r1.performance, r1.category_id
				   ORDER BY r1.performance asc
				) as rd
				ON r.runner_id = rd.runner_id AND r.performance = rd.performance AND ra.date = rd.earliest
				INNER JOIN events e ON ra.event_id = e.id
				INNER JOIN runners p ON r.runner_id = p.id
				INNER JOIN category c ON r.category_id = c.id
                INNER JOIN distance d ON ra.distance_id = d.id
				WHERE c.age_less_than is NOT NULL and ra.distance_id = %d
				ORDER BY c.age_less_than, c.sex_id", $distanceId, $distanceId);

        return $this->executeResultsQuery(__METHOD__, $sql);
    }
}

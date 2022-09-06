<?php

namespace IpswichJAFFARunningClubAPI\V2\Rankings;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Constants/Rules.php';

use IpswichJAFFARunningClubAPI\V2\Constants\Rules as Rules;
use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class RankingsDataAccess extends DataAccess
{
	public function getResultRankings(int $distanceId, ?int $year, ?int $sexId, ?int $categoryId)
	{
		if ($year != 0) {
			$dateQuery1 = "  WHERE ra1.date >= '$year-01-01' and ra1.date <= '$year-12-31'";
			$dateQuery2 = "  AND ra2.date >= '$year-01-01' and ra2.date <= '$year-12-31'";
		} else {
			$dateQuery1 = "";
			$dateQuery2 = "";
		}

		if ($sexId != 0) {
			$sexQuery = " AND p2.sex_id = $sexId";
		} else {
			$sexQuery = "";
		}

		if ($categoryId != 0) {
			$categoryQuery = " AND r2.category_id = $categoryId";
		} else {
			$categoryQuery = "";
		}

		$sql = "SET @cnt := 0;";

		$this->resultsDatabase->query($sql);

		$sql = "
				SELECT @cnt := @cnt + 1 AS rank, Ranking.* FROM (
					SELECT r.runner_id as runnerId, p.Name as name, ra3.id as raceId, e.Name as event, ra3.date, r.result, r.performance as 'performance', d.result_unit_type_id as resultUnitTypeId
					FROM results AS r
					JOIN (
					  SELECT r1.runner_id, r1.performance, MIN(ra1.date) AS earliest
					  FROM results AS r1
					  INNER JOIN race ra1 ON r1.race_id = ra1.id
					  INNER JOIN distance d ON ra1.distance_id = d.id
					  JOIN (
						SELECT r2.runner_id, 
						CASE
							WHEN d.result_unit_type_id = 3 THEN MAX(r2.performance)
							ELSE MIN(r2.performance)
                    	END as best
						FROM results r2
						INNER JOIN race ra2 ON ra2.id = r2.race_id
						INNER JOIN runners p2	ON r2.runner_id = p2.id
						INNER JOIN distance d ON ra2.distance_id = d.id
						WHERE r2.performance > 0
						AND ra2.distance_id = $distanceId
                        AND (ra2.course_type_id NOT IN (2, 4, 5, 7) OR ra2.course_type_id IS NULL)
						$sexQuery
						$dateQuery2
                        $categoryQuery
						GROUP BY r2.runner_id
					   ) AS rt
					   ON r1.runner_id = rt.runner_id AND r1.performance = rt.best
					   $dateQuery1
					   GROUP BY r1.runner_id, r1.performance
					   ORDER BY 
					   	CASE
							WHEN d.result_unit_type_id = 3 THEN r1.performance							
                    	END DESC,
                        CASE
							WHEN d.result_unit_type_id != 3 THEN r1.performance						
                    	END ASC
					   LIMIT 100
					) as rd
					ON r.runner_id = rd.runner_id AND r.performance = rd.performance
					INNER JOIN race ra3 ON r.race_id = ra3.id AND ra3.date = rd.earliest
					INNER JOIN distance d ON ra3.distance_id = d.id
					INNER JOIN runners p ON r.runner_id = p.id
					INNER JOIN events e ON ra3.event_id = e.id
					LIMIT 100) Ranking";

		return $this->executeResultsQuery(__METHOD__, $sql);
	}

	public function getWMAPercentageRankings(?int $sexId, ?int $distanceId, ?int $year, ?bool $distinct)
	{
		if ($distanceId) {
			$distanceQuery1 = " AND ra1.distance_id = $distanceId";
			$distanceQuery2 = " AND ra2.distance_id = $distanceId";
		} else {
			$distanceQuery2 = "";
			$distanceQuery1 = "";
		}

		if ($sexId) {
			$sexQuery0 = " AND p.sex_id = $sexId";
			$sexQuery1 = " AND p2.sex_id = $sexId";
		} else {
			$sexQuery0 = "";
			$sexQuery1 = "";
		}

		if ($year) {
			$yearQuery1 = " AND YEAR(ra1.date) >= $year AND YEAR(ra1.date) < ($year +1)";
			$yearQuery2 = " AND YEAR(ra2.date) >= $year AND YEAR(ra2.date) < ($year +1)";
		} else {
			$yearQuery1 = "";
			$yearQuery2 = "";
			$year = 0; // if null is explicitly passed then default value is not set.
		}

		$sql = "SET @cnt := 0;";

		$this->resultsDatabase->query($sql);

		if (!$distinct) {
			$sql = "
					select @cnt := @cnt + 1 as rank, ranking.* from (
						select r.runner_id as runnerId,
						p.name,
						e.id as eventId,
						e.name as event,
						ra2.id as raceId,
						ra2.date,
						r.performance,						
						r.result,
						d.result_unit_type_id as resultUnitTypeId,
						CASE
							WHEN ra2.date >= '" . Rules::START_OF_2015_AGE_GRADING . "' OR $year = 0 THEN r.percentage_grading_2015
							ELSE r.percentage_grading
						END as percentageGrading
						from results as r
						inner join runners p on p.id = r.runner_id
						inner join race ra2 on ra2.id = r.race_id
						INNER JOIN `distance` d ON ra2.distance_id = d.id
						inner join events e on e.id = ra2.event_id
						where ((r.percentage_grading_2015 > 0 AND (ra2.date > '" . Rules::START_OF_2015_AGE_GRADING . "' OR $year = 0)) OR r.percentage_grading > 0)
						$sexQuery0
						$distanceQuery2
						$yearQuery2
						order by percentageGrading desc
						limit 500) ranking";
		} else {
			if ($year >= 2017) {
				$sql = "
					SELECT @cnt := @cnt + 1 AS rank, Ranking.* FROM (
						SELECT r.runner_id as runnerId, p.Name as name, e.id as eventId, e.Name as event,
						ra.id as raceId,
						ra.date,
						r.result,
						r.performance,
						d.result_unit_type_id as resultUnitTypeId,
						r.percentage_grading_2015 as percentageGrading
						FROM results AS r
						JOIN (
						  SELECT r1.runner_id, r1.performance, MIN(ra1.date) AS earliest
						  FROM results AS r1
						  JOIN (
							SELECT r2.runner_id, MAX(r2.percentage_grading_2015) AS highest
							FROM results r2
							INNER JOIN race ra2
							ON r2.race_id = ra2.id
							INNER JOIN `runners` p2
							ON r2.runner_id = p2.id
							WHERE r2.percentage_grading_2015 > 0
							$distanceQuery2
							$sexQuery1
							$yearQuery2
							GROUP BY r2.runner_id
						   ) AS rt
						   ON r1.runner_id = rt.runner_id AND r1.percentage_grading_2015 = rt.highest
						   INNER JOIN race ra1 ON r1.race_id = ra1.id
						   $distanceQuery1
						   $yearQuery1
						   GROUP BY r1.runner_id, r1.performance
						   ORDER BY r1.percentage_grading_2015 desc
						   LIMIT 100
						) as rd
						ON r.runner_id = rd.runner_id AND r.performance = rd.performance
						INNER JOIN race ra ON r.race_id = ra.id AND ra.date = rd.earliest
						INNER JOIN events e ON ra.event_id = e.id
						INNER JOIN `distance` d ON ra.distance_id = d.id
						INNER JOIN runners p ON r.runner_id = p.id
						ORDER BY percentageGrading desc
						LIMIT 100) Ranking";
			} else {
				$sql = "
					SELECT @cnt := @cnt + 1 AS rank, Ranking.* FROM (
						SELECT r.runner_id as runnerId, p.Name as name, e.id as eventId, e.Name as event,
						ra.date,
						ra.id as raceId,
						r.result,
						r.performance,
						d.result_unit_type_id as resultUnitTypeId,
						r.percentage_grading as percentageGrading
						FROM results AS r
						JOIN (
						  SELECT r1.runner_id, r1.performance, MIN(ra1.date) AS earliest
						  FROM results AS r1
						  JOIN (
							SELECT r2.runner_id, MAX(r2.percentage_grading) AS highest
							FROM results r2
							INNER JOIN race ra2
							ON r2.race_id = ra2.id
							INNER JOIN `runners` p2
							ON r2.runner_id = p2.id
							WHERE r2.percentage_grading > 0
							$distanceQuery2
							$sexQuery1
							$yearQuery2
							GROUP BY r2.runner_id
						   ) AS rt
						   ON r1.runner_id = rt.runner_id AND r1.percentage_grading = rt.highest
						   INNER JOIN race ra1 ON r1.race_id = ra1.id
						   $distanceQuery1
						   $yearQuery1
						   GROUP BY r1.runner_id, r1.performance
						   ORDER BY r1.percentage_grading desc
						   LIMIT 100
						) as rd
						ON r.runner_id = rd.runner_id AND r.performance = rd.performance
						INNER JOIN race ra ON r.race_id = ra.id AND ra.date = rd.earliest
						INNER JOIN events e ON ra.event_id = e.id
						INNER JOIN runners p ON r.runner_id = p.id
						INNER JOIN `distance` d ON ra.distance_id = d.id
						ORDER BY percentageGrading desc
						LIMIT 100) Ranking";
			}
		}

		return $this->executeResultsQuery(__METHOD__, $sql);
	}

	public function getAveragePercentageRankings(?int $sexId, ?int $year, ?int $numberOfRaces, ?int $numberOfResults)
	{

		$sql = "set @cnt := 0, @runnerId := 0, @rank := 0;";

		$this->resultsDatabase->query($sql);
		// If no year specificed the query is across all years.
		// Prior to 2015 it is for calendar year results
		// In 2015 the membership year changed to be from 1st March
		// In 2021 the membership year changed to be from 1st April
		if ($year == 0) {
			$yearQuery = "";
		} elseif ($year < 2015) {
			$yearQuery = "AND YEAR(ra.date) = $year";
		} elseif ($year == 2015) {
			$yearQuery = "AND ra.date >= '2015-01-01' AND ra.date < '2016-03-01'";
		} else if ($year < 2020) {
			$nextYear = $year + 1;
			$yearQuery = "AND ra.date >= '$year-03-01' AND ra.date < '$nextYear-03-01'";
		} else if ($year == 2020) {
			$yearQuery = "AND ra.date >= '2020-03-01' AND ra.date < '2021-04-01'";
		} else {
			$nextYear = $year + 1;
			$yearQuery = "AND ra.date >= '$year-04-01' AND ra.date < '$nextYear-03-01'";
		}

		$sql = "select @rank := @rank + 1 AS rank, Results.* FROM (
					select runner_id as runnerId, name, ROUND(avg(ranktopX.percentage_grading_2015),2) as topXAvg from (
					select * from (
					select @cnt := if (@runnerId = ranking.runner_id, @cnt + 1, 1) as rank, @runnerId := ranking.runner_id, ranking.* from (

										select r.runner_id, p.name, e.id, e.name as event, ra.date, r.result, r.performance, r.percentage_grading_2015
										from results as r
										inner join runners p on p.id = r.runner_id
										inner join race ra on ra.id = r.race_id
										inner join events e on e.id = ra.event_id
										where r.percentage_grading_2015 > 0
										AND p.sex_id = $sexId
										$yearQuery
										order by r.runner_id asc, r.percentage_grading_2015 desc) ranking
					) as rank2
					where rank2.rank <= $numberOfRaces
					) ranktopX
					group by ranktopX.runner_id
					having count(*) = $numberOfRaces
					order by topXAvg desc
					LIMIT $numberOfResults) Results";

		return $this->executeResultsQuery(__METHOD__, $sql);
	}
}

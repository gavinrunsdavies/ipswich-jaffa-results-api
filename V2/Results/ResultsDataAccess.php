<?php

namespace IpswichJAFFARunningClubAPI\V2\Results;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Constants/Rules.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/CourseTypes/CourseTypes.php';

use IpswichJAFFARunningClubAPI\V2\Constants\Rules as Rules;
use IpswichJAFFARunningClubAPI\V2\CourseTypes\CourseTypes as CourseTypes;
use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class ResultsDataAccess extends DataAccess
{
    public function getRaceResults(int $raceId)
    {
        $sql = $this->resultsDatabase->prepare("
            SELECT
                r.id,
                r.runner_id AS runnerId,
                r.position,
                r.result AS result,
                r.result AS time,
                r.performance AS performance,
                r.info,
                s.name AS standardType,
                c.code AS categoryCode,
                r.personal_best AS isPersonalBest,
                r.season_best AS isSeasonBest,
                r.scoring_team AS team,                
                r.age_grading AS percentageGrading,
                r.percentage_grading_best AS percentageGradingBest,
                p.name AS runnerName,
                r.race_id AS raceId,
                c.id AS categoryId,
                race.date AS date,
                rt.runnerTotalResults,
                GROUP_CONCAT(DISTINCT b.name ORDER BY b.name SEPARATOR ',') AS runnerBadges
            FROM results r
            INNER JOIN race race ON r.race_id = race.id
            INNER JOIN runners p ON r.runner_id = p.id
            LEFT JOIN standard_type s ON s.id = r.standard_type_id
            LEFT JOIN category c ON c.id = r.category_id
            INNER JOIN (
                SELECT runner_id, COUNT(*) AS runnerTotalResults
                FROM results
                GROUP BY runner_id
            ) rt ON rt.runner_id = r.runner_id
            LEFT JOIN runner_badges rb ON rb.runner_id = r.runner_id
            LEFT JOIN badges b ON b.id = rb.badge_id
            WHERE r.race_id = %d
            GROUP BY r.id
            ORDER BY r.position ASC, r.result ASC
        ", $raceId);

        $results = $this->executeResultsQuery(__METHOD__, $sql);

        // Convert runnerBadges to an array
        foreach ($results as &$result) {
            $result->runnerBadges = $result->runnerBadges
                ? explode(',', $result->runnerBadges)
                : [];
        }

        return $results;
    }

    public function getPreviousPersonalBest($runnerIds, int $newRaceId)
    {
        $sql = "SELECT 
            r1.runner_id as runnerId, 
            CASE WHEN (d.result_unit_type_id != 3) THEN 
                MIN(r2.result)
            ELSE 
                MAX(r2.result)  
            END as previousBest,
            CASE WHEN (d.result_unit_type_id != 3) THEN 
                MIN(r2.performance)
            ELSE 
                MAX(r2.performance)    
            END as previousBestPerformance
        FROM `results` r1
        INNER JOIN `race` ra1 ON r1.race_id = ra1.id              
        inner join `results` r2 on r1.runner_id = r2.runner_id   
        INNER JOIN `race` ra2 ON r2.race_id = ra2.id 
        INNER JOIN distance d ON d.id = ra1.distance_id         
        where r1.race_id = $newRaceId
        AND ra1.date > ra2.date AND r2.personal_best = 1 
        and r1.personal_best = 1
        AND ra1.distance_id = ra2.distance_id
        AND r1.runner_id in ($runnerIds)
        GROUP BY r1.runner_id";

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getResult(int $resultId)
    {
        $sql =  $this->resultsDatabase->prepare(
            "SELECT 
            r.id, 
            0 as 'eventId',
            r.runner_id as 'runnerId',
            r.position,
            ra.date as 'date',
            r.performance as 'performance',
            r.result as 'result',
            r.result as 'time',
            r.info,           
            r.standard_type_id as 'standardTypeId',
            r.category_id as 'categoryId',
            r.personal_best as 'isPersonalBest',
            r.season_best as 'isSeasonBest',
			r.scoring_team as 'team', 
            ra.id as 'raceId', 
            p.sex_id, 
            e.name as 'eventName',
			r.age_grading as percentageGrading,
			r.percentage_grading_best as percentageGradingBest,
			ra.course_number as 'courseNumber', 
            p.name as 'runnerName', 
            e.name as 'eventName', 
            ra.description as 'raceDescription'
			FROM results r
			INNER JOIN runners p on p.id = r.runner_id
			INNER JOIN race ra ON r.race_id = ra.id
			INNER JOIN events e ON ra.event_id = e.id
			WHERE r.id = %d
			ORDER BY ra.date DESC, ra.id, r.position ASC, r.result ASC",
            $resultId
        );

        return $this->executeResultQuery(__METHOD__, $sql);
    }

    public function getCountyResultCategory(int $resultId)
    {
        $sql =  $this->resultsDatabase->prepare(
            "SELECT county_record_category_override as categoryCode
            FROM results r
            INNER JOIN county_champion_results ccr ON r.id = ccr.result_id
            INNER JOIN category c ON r.category_id = c.id
			WHERE r.id = %d",
            $resultId
        );

        return $this->executeResultQuery(__METHOD__, $sql);
    }

    public function updateCountyResultCategory(int $resultId, string $categoryOverride)
    {
        return $this->updateEntity(__METHOD__, 'county_champion_results', 'county_record_category_override', $categoryOverride, $resultId, function ($id) {
            return $this->getResult($id);
        });
    }

    public function deleteCountyResult(int $resultId)
    {
        $sql = $this->resultsDatabase->prepare('DELETE FROM county_champion_results WHERE result_id = %d', $resultId);

        return $this->executeQuery(__METHOD__, $sql);
    }

    public function insertCountyResultCategory(int $resultId, ?string $categoryCodeOverride = null)
    {
        $sql = $this->resultsDatabase->prepare(
            'INSERT INTO county_champion_results (`result_id`, `county_record_category_override`)
			VALUES(%d, %s)',
            $resultId,
            $categoryCodeOverride
        );

        $result = $this->resultsDatabase->query($sql);

        if (is_null($result) || !empty($this->resultsDatabase->last_error)) {
            return new \WP_Error(
                __METHOD__,
                'Unknown error in inserting entity in to the database',
                array(
                    'status' => 500,
                    'last_query' => $this->resultsDatabase->last_query,
                    'last_error' => $this->resultsDatabase->last_error
                )
            );
        }

        return $this->resultsDatabase->insert_id;
    }

    public function updateResult(int $resultId, string $field, string $value)
    {
        return $this->updateEntity(__METHOD__, 'results', $field, $value, $resultId, function ($id) {
            return $this->getResult($id);
        });
    }

    public function deleteResult(int $resultId)
    {
        $sql = $this->resultsDatabase->prepare('DELETE FROM results WHERE id = %d', $resultId);

        return $this->executeQuery(__METHOD__, $sql);
    }

    public function insertResult($resultRequest, float $performance, int $categoryId, bool $isPersonalBest, bool $isSeasonBest, int $standardTypeId, ?float $ageGrading)
    {
        $sql = $this->resultsDatabase->prepare(
            'INSERT INTO results (`result`, `performance`, `info`, `runner_id`, `position`, `category_id`, `personal_best`, `season_best`, `standard_type_id`, `scoring_team`, `race_id`, `age_grading`)
			VALUES(%s, %f, %s, %d, %d, %d, %d, %d, %d, %d, %d, %f)',
            $resultRequest['result'],
            $performance,
            $resultRequest['info'],
            $resultRequest['runnerId'],
            $resultRequest['position'],
            $categoryId,
            $isPersonalBest ? 1 : 0,
            $isSeasonBest ? 1 : 0,
            $standardTypeId,
            $resultRequest['team'] != null ? $resultRequest['team'] : 0,
            $resultRequest['raceId'],
            $ageGrading ?? 0
        );

        $result = $this->resultsDatabase->query($sql);

        if (is_null($result) || !empty($this->resultsDatabase->last_error)) {
            return new \WP_Error(
                __METHOD__,
                'Unknown error in inserting entity in to the database',
                array(
                    'status' => 500,
                    'last_query' => $this->resultsDatabase->last_query,
                    'last_error' => $this->resultsDatabase->last_error
                )
            );
        }

        return $this->resultsDatabase->insert_id;
    }

    public function getResults(int $eventId = null, string $fromDate = null, string $toDate = null, int $numberOfResults = null)
    {
        if (empty($eventId)) {
            $whereEvent = '';
        } else {
            $whereEvent = ' AND ra.event_id = ' . $eventId;
        }

        if (empty($fromDate)) {
            $whereFrom = '';
        } else {
            $whereFrom = " AND ra.date >= '$fromDate'";
        }

        if (empty($toDate)) {
            $whereTo = '';
        } else {
            $whereTo = " AND ra.date <= '$toDate'";
        }

        $limit = abs(intval($numberOfResults));

        if ($limit <= 0) {
            $limit = 100;
        }

        $sql = $this->resultsDatabase->prepare("
            SELECT r.id, 
            ra.event_id as 'eventId',
            r.runner_id as 'runnerId',
            r.position,
            ra.date as 'date',
            r.result as 'time',
            r.result as 'result',
            r.performance as 'performance',
            r.info,
            r.standard_type_id as 'standardTypeId',
            r.category_id as 'categoryId',
            r.personal_best as 'isPersonalBest',
            r.season_best as 'isSeasonBest',
			r.scoring_team as 'team',
			r.age_grading as percentageGrading,
			p.name as 'runnerName',
			e.name as 'eventName', ra.description as 'raceDescription'
			FROM results r
			INNER JOIN runners p on p.id = r.runner_id
			INNER JOIN race ra ON r.race_id = ra.id
			INNER JOIN events e ON ra.event_id = e.id
			WHERE 1=1 $whereEvent $whereFrom $whereTo
			ORDER BY ra.date DESC, ra.id, r.position ASC, r.result ASC LIMIT %d", $limit);

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getCountyChampions()
    {
        $sql = "SELECT r.id as resultId, r.runner_id as runnerId, p.Name as runnerName, e.id as eventId, e.Name as eventName, ra.date, r.result as result, r.performance as performance, 
                CASE
                    WHEN ccr.county_record_category_override IS NOT NULL THEN ccr.county_record_category_override
                    ELSE c.code
                END as categoryCode,
                ra.id as raceId, ra.description, d.id as distanceId, d.distance
				FROM results AS r
                INNER JOIN county_champion_results ccr ON r.id = ccr.result_id
                INNER JOIN race ra ON r.race_id = ra.id
                LEFT JOIN distance d ON ra.distance_id = d.id
				INNER JOIN events e ON ra.event_id = e.id
				INNER JOIN runners p ON r.runner_id = p.id
				INNER JOIN category c ON r.category_id = c.id
				ORDER BY ra.date desc, categoryCode asc";

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getResultStandardTypeId(int $catgeoryId, string $resultInHourMinutesSeconds, int $raceId, float $ageGrading, string $resultDate): int
    {
        if ($resultDate >= Rules::START_OF_2015_AGE_GRADING) {
            if ($ageGrading >= 86) {
                return 14;
            }

            if ($ageGrading >= 80) {
                return 15;
            }

            if ($ageGrading >= 74) {
                return 16;
            }

            if ($ageGrading >= 68) {
                return 17;
            }

            if ($ageGrading >= 62) {
                return 18;
            }

            if ($ageGrading >= 56) {
                return 19;
            }

            if ($ageGrading >= 50) {
                return 20;
            } else {
                return 0;
            }
        }

        return $this->getStarStandardTypeIdBefore2015($catgeoryId, $resultInHourMinutesSeconds, $raceId);
    }

    private function getStarStandardTypeIdBefore2015(int $catgeoryId, string $resultInHourMinutesSeconds, int $raceId)
    {
        // Get standard type for results before 2015
        $sql = $this->resultsDatabase->prepare("SELECT
									s.standard_type_id
									FROM
									standard_type st,
									standards s,
									race ra
									WHERE
									s.standard_type_id = st.id AND
									s.category_id = %d AND
									s.distance_id = ra.distance_id AND
									ra.id = %d AND
									'%s' <= s.standard AND
									st.obsolete = 0
									ORDER BY
									s.standard
									LIMIT 1", $catgeoryId, $raceId, $resultInHourMinutesSeconds);

        $standard = $this->resultsDatabase->get_var($sql);

        if (empty($standard)) {
            $standard = 0;
        }

        return $standard;
    }

    public function getRoadRaceAgeGrading(float $performance, int $runnerId, int $raceId, int $dataSetYear) : float
    {
        $gradeTable = "wma_age_grading_" . $dataSetYear;
        $recordTable = "wma_records_" . $dataSetYear;

        $sql = sprintf("
            SELECT
                ROUND((record.record * 100) / (%f * grade.grading_percentage), 2) AS percentageGrading
            FROM
                %s grade
                INNER JOIN %s record ON grade.distance_id = record.distance_id
                INNER JOIN distance d ON record.distance_id = d.id
                INNER JOIN race race ON d.id = race.distance_id, 
                runners p
            WHERE
                race.id = %d
                AND p.id = %d
                AND p.dob <> '0000-00-00'
                AND grade.age = (YEAR(race.date) - YEAR(p.dob) - IF(DATE_FORMAT(p.dob, '%%j') > DATE_FORMAT(race.date, '%%j'), 1, 0))
                AND grade.sex_id = p.sex_id
                AND grade.sex_id = record.sex_id
        ", $performance, $gradeTable, $recordTable, $raceId, $runnerId);

        $stmt = $this->resultsDatabase->prepare($sql);
        $result = $this->resultsDatabase->get_var($stmt);

        if ($result === false) {
            return 0;
        }

        return $result;
    }

    public function getTrackAgeGrading(float $performance, int $runnerId, int $raceId, int $dataSetYear) : float
    {
        $gradeTable = "wma_age_grading_" . $dataSetYear;
        $recordTable = "wma_records_" . $dataSetYear;

        $sql = sprintf("
            SELECT
                ROUND((record.record * 100) / (%f * grade.grading_percentage), 2) AS percentageGrading
            FROM
                %s grade
                INNER JOIN %s record ON grade.distance_id = record.distance_id
                INNER JOIN distance d ON record.distance_id = d.id
                INNER JOIN race race ON d.id = race.distance_id 
                    AND race.course_type_id = grade.course_type_id
                    AND race.course_type_id = record.course_type_id,
                runners p
            WHERE
                race.id = %d
                AND p.id = %d
                AND p.dob <> '0000-00-00'
                AND grade.age = (YEAR(race.date) - YEAR(p.dob) - IF(DATE_FORMAT(p.dob, '%%j') > DATE_FORMAT(race.date, '%%j'), 1, 0))
                AND grade.sex_id = p.sex_id
                AND grade.sex_id = record.sex_id
        ", $performance, $gradeTable, $recordTable, $raceId, $runnerId);

        $stmt = $this->resultsDatabase->prepare($sql);
        $result = $this->resultsDatabase->get_var($stmt);

        if ($result === false) {
            return 0;
        }

        return $result;
    }


    public function isPersonalBest(int $raceId, int $runnerId, float $performance): bool
    {
        $sql = $this->resultsDatabase->prepare(
            "select  
                count(CASE WHEN (d.result_unit_type_id != 3 AND r.performance <= %f) OR (d.result_unit_type_id = 3 AND r.performance >= %f) THEN 1 END ) as betterResults
                from
                race existingRaces,
                race thisRace,
                distance d,
                results r
                where
                existingRaces.id = r.race_id AND
                existingRaces.distance_id = thisRace.distance_id AND
                d.id = thisRace.distance_id AND
                thisRace.id = %d AND
                existingRaces.distance_id != 0 AND
                r.performance != 0 AND
                r.performance IS NOT NULL AND
                r.runner_id = %d AND
                r.race_id != thisRace.id AND
                existingRaces.date < thisRace.date AND
                existingRaces.course_type_id IN (%d, %d, %d) AND
                thisRace.course_type_id IN (%d, %d, %d)",
            $performance,
            $performance,
            $raceId,
            $runnerId,
            CourseTypes::ROAD,
            CourseTypes::TRACK,
            CourseTypes::INDOOR,
            CourseTypes::ROAD,
            CourseTypes::TRACK,
            CourseTypes::INDOOR
        );

        $betterResultsCount = $this->resultsDatabase->get_var($sql);

        return (is_null($betterResultsCount) || $betterResultsCount == 0);
    }

    public function checkAndUpdatePersonalBestResults(int $resultId)
    {
        // If no later PBs, nothing to do
        // If a later PB (at distance) reset
        $sql = $this->resultsDatabase->prepare("SELECT
			count(allResults.id)
			FROM
			results pbResult
			INNER JOIN race pbRace ON pbRace.id = pbResult.race_id,
			results allResults
			INNER JOIN race allRaces ON allRaces.id = allResults.race_id
			where
			pbResult.id = %d AND
			allRaces.distance_id = pbRace.distance_id AND
			pbResult.runner_id = allResults.runner_id AND
			allRaces.date > pbRace.date AND
			allResults.personal_best = 1
			LIMIT 1", $resultId);

        $count = $this->resultsDatabase->get_var($sql);

        if ($count == 0) {
            return;
        }

        // Not the latest result. Reset PB status for later results.
        $sql = $this->resultsDatabase->prepare(
            "
			UPDATE
			results pbResult,
			race pbRace,
			results laterResults,
			race laterRaces
			SET laterResults.personal_best = 0
			WHERE
			pbResult.id = %d AND
			pbResult.runner_id = laterResults.runner_id AND
			pbResult.race_id = pbRace.id AND
			laterResults.race_id = laterRaces.id AND
			laterRaces.date > pbRace.date AND
			pbRace.distance_id = laterRaces.distance_id",
            $resultId
        );

        $this->resultsDatabase->query($sql);

        $sql = "SET @pbTime = '99:99:99'";
        $this->resultsDatabase->query($sql);

        $sql = $this->resultsDatabase->prepare(
            "
			UPDATE results r,
			(
				SELECT
				existingResultsWithPB.id
				FROM
				(
					SELECT
					@pbTime := IF (existingResults.result < @pbTime, existingResults.result, @pbTime) as PBTime,
					existingResults.*
					FROM
						(
							SELECT
							laterResults.id,
							laterResults.result
							FROM
							results pbResult INNER JOIN race pbRace ON pbResult.race_id = pbRace.id,
							results laterResults INNER JOIN race laterRaces ON laterResults.race_id = laterRaces.id
							WHERE
							pbResult.id = %d AND
							pbResult.runner_id = laterResults.runner_id AND
							laterRaces.date > pbRace.date AND
							pbRace.distance_id = laterRaces.distance_id AND
							laterResults.result > '00:00:00' AND
							laterRaces.course_type_id IN (%d, %d, %d)
							ORDER BY laterRaces.date ASC
						) existingResults
				) existingResultsWithPB
				WHERE
				existingResultsWithPB.PBTime = existingResultsWithPB.result
			) pbResults
			set r.personal_best = 1
			where pbResults.id = r.id
			",
            $resultId,
            CourseTypes::ROAD,
            CourseTypes::TRACK,
            CourseTypes::INDOOR
        );

        return $this->executeQuery(__METHOD__, $sql);
    }

    public function updatePercentageGradingPersonalBest(int $resultId, int $runnerId, string $date)
    {
        // IF the latest result check all results
        // ELSE reset all for valid result (e.g. grading > 0)
        $sql = $this->resultsDatabase->prepare(
            "
			SELECT
			count(r.id)
			FROM results r
			INNER JOIN race race on race.id = r.race_id
			WHERE race.date >= '%s'
			AND r.runner_id = %d
			AND r.age_grading > 0",
            $date,
            $runnerId
        );

        $count = $this->resultsDatabase->get_var($sql);

        if ($count == 0) {
            // No later results with a grading percentage.
            // Check for personal best and update
            $sql = $this->resultsDatabase->prepare("
				UPDATE results
				SET percentage_grading_best = 1
				WHERE id = %d AND
				(
					SELECT
					count(r1.id)
					FROM results r1, results r2
					WHERE r1.runner_id = %d
					AND r2.id = %d
					AND (r1.age_grading > r2.age_grading)
				) = 0
				", $resultId, $runnerId, $resultId);

            return $this->executeQuery(__METHOD__, $sql);
        } else {
            // Not the latest result. Reset grading.
            $sql =  $this->resultsDatabase->prepare("UPDATE results SET percentage_grading_best = 0 WHERE runner_id = %d;", $runnerId);
            $this->executeQuery(__METHOD__, $sql);

            $sql = "SET @pgpb = 0;";
            $this->executeQuery(__METHOD__, $sql);

            $sql = $this->resultsDatabase->prepare("
                    UPDATE results r,
                    (
                    SELECT
                        b.id
                    FROM
                    (
                        SELECT
                            @pgpb := IF (a.percentageGrading > @pgpb, a.percentageGrading, @pgpb) as PGPB,
                            a.*
                        FROM
                            (
                                SELECT
                                    r.id,                                    
                                    r.age_grading as percentageGrading
                                FROM results r
                                INNER JOIN race a ON a.id = r.race_id
                                WHERE r.runner_id = %d
                                ORDER BY a.date asc
                            ) a
                    ) b
                    WHERE
                    b.PGPB > 0 AND b.PGPB = b.percentageGrading
                    ) c
                    set r.percentage_grading_best = 1
                    where c.id = r.id
				", $runnerId);

            $this->executeQuery(__METHOD__, $sql);
        }

        return true;
    }

    public function isSeasonBest(int $raceId, int $runnerId, string $performance, string $date): bool
    {
        $sql = $this->resultsDatabase->prepare(
            "select  
            count(CASE WHEN (d.result_unit_type_id != 3 AND r.performance <= %f) OR (d.result_unit_type_id = 3 AND r.performance >= %f) THEN 1 END ) as betterResults
                from
                race existingRaces,
                race thisRace,
                distance d,
                results r
                where
                existingRaces.id = r.race_id AND
                existingRaces.distance_id = thisRace.distance_id AND
                d.id = thisRace.distance_id AND
                thisRace.id = %d AND
                existingRaces.distance_id != 0 AND
                r.performance != 0 AND
                r.performance IS NOT NULL AND
                r.runner_id = %d AND
                r.race_id != thisRace.id AND
                YEAR(existingRaces.date) = YEAR('%s') AND
                existingRaces.date < thisRace.date AND
                existingRaces.course_type_id IN (%d, %d, %d) AND
                thisRace.course_type_id IN (%d, %d, %d)",
            $performance,
            $performance,
            $raceId,
            $runnerId,
            $date,
            CourseTypes::ROAD,
            CourseTypes::TRACK,
            CourseTypes::INDOOR,
            CourseTypes::ROAD,
            CourseTypes::TRACK,
            CourseTypes::INDOOR
        );

        $betterResultsCount = $this->resultsDatabase->get_var($sql);

        return (is_null($betterResultsCount) || $betterResultsCount == 0);
    }

    public function isNewStandard(int $resultId): bool
    {
        // 7 Star standards - from January 1st 2017
        // -- Match results of the same runner
        // -- Match results of the same distance
        // -- Date is after existing races
        // -- Find results with the same standard or better (small ID)
        $sql = $this->resultsDatabase->prepare(
            "SELECT count(existingResult.id)
            FROM results newResult, results existingResult, race newRace, race existingRace
            WHERE newResult.id = %d
            AND newResult.id != existingResult.id
            AND newResult.runner_id = existingResult.runner_id
            AND newResult.race_id = newRace.id
            AND existingResult.race_id = existingRace.id
            AND newRace.distance_id = existingRace.distance_id
            AND newRace.date >= existingRace.date
            AND existingRace.date >= '2017-01-01'
            AND existingResult.standard_type_id <= newResult.standard_type_id
            AND newResult.standard_type_id IN (14, 15, 16, 17, 18, 19, 20)
            AND existingResult.standard_type_id IN (14, 15, 16, 17, 18, 19, 20)",
            $resultId
        );

        $count = $this->resultsDatabase->get_var($sql);

        return ($count == 0);
    }

    public function saveStandardCertificate(int $resultId)
    {
        $sql = $this->resultsDatabase->prepare("insert into standard_certificates set result_id=%d, issued = 0", $resultId);

        return $this->executeQuery(__METHOD__, $sql);
    }

    public function getCategoryId(int $runnerId, string $date): ?int
    {
        $sql = $this->resultsDatabase->prepare("select c.id
					FROM
					runners p, category c
					WHERE p.id = %d
					AND p.sex_id = c.sex_id
					AND TIMESTAMPDIFF(YEAR, p.dob, '%s') >= c.age_greater_equal
					AND TIMESTAMPDIFF(YEAR, p.dob, '%s') < c.age_less_than
					LIMIT 1", $runnerId, $date, $date);

        return $this->resultsDatabase->get_var($sql);
    }

    public function getRace(int $raceId)
    {

        $sql = $this->resultsDatabase->prepare(
            'SELECT
				ra.id,
				e.id AS eventId,
				e.Name as eventName,
				ra.description as description,
				ra.date,
				ra.course_type_id AS courseTypeId,
				c.description AS courseType,
				ra.area, ra.county,
				ra.country_code AS countryCode,
				ra.conditions,
				ra.venue,
				d.distance,
    				d.id as distanceId,
				ra.grand_prix as isGrandPrixRace,
				ra.course_number as courseNumber,
				ra.league_id as leagueId,
				ra.meeting_id as meetingId,
				d.result_measurement_unit_type_id as resultMeasurementUnitTypeId,
                ra.report as report
				FROM `events` e
				INNER JOIN `race` ra ON ra.event_id = e.id
				LEFT JOIN `distance` d ON ra.distance_id = d.id
				LEFT JOIN `course_type` c ON ra.course_type_id = c.id
				WHERE ra.id = %d',
            $raceId
        );

        return $this->executeResultQuery(__METHOD__, $sql);
    }

    public function addRunnerBadges(int $runnerId, array $badgeIds): void
    {
        if (empty($badgeIds)) {
            return;
        }

        // Fetch existing badge IDs for this runner
        $sql = $this->resultsDatabase->prepare("SELECT badge_id FROM runner_badges WHERE runner_id = %d", $runnerId);
        $results = $this->executeResultQuery(__METHOD__, $sql);
        $existingRunnerBadges = [];
        foreach ($results as $row) {
            $existingRunnerBadges[] = $row->badge_id;
        }

        // Determine which badge IDs are not already assigned
        $newBadgeIds = array_diff($badgeIds, $existingRunnerBadges);

        if (empty($newBadgeIds)) {
            return;
        }

        $values = [];
        $params = [];

        foreach ($newBadgeIds as $badgeId) {
            $values[] = "(%d, %d)";
            $params[] = $runnerId;
            $params[] = $badgeId;
        }

        $placeholders = implode(', ', $values);
        $insertStmt = $this->resultsDatabase->prepare("INSERT IGNORE INTO runner_badges (runner_id, badge_id) VALUES $placeholders", ...$params);
        $this->executeQuery(__METHOD__, $insertStmt);
    }
}

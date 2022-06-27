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
        $sql = $this->resultsDatabase->prepare("SELECT
			r.id, r.runner_id as 'runnerId',
			r.position, 
            r.result as 'result',
			r.performance as 'performance',
			r.info, s.name as standardType,
			c.code as categoryCode,
			r.personal_best as 'isPersonalBest',
			r.season_best as 'isSeasonBest',
			r.scoring_team as 'team',
			CASE
			   WHEN race.date >= '%s' THEN r.percentage_grading_2015
			   ELSE r.percentage_grading
			END as percentageGrading,
			r.percentage_grading_best as percentageGradingBest,
			p.name as 'runnerName',
			r.race_id as raceId,
			c.id as categoryId,
			race.date as 'date'
			FROM results r
			INNER JOIN race race ON r.race_id = race.id
			INNER JOIN runners p on r.runner_id = p.id
			LEFT JOIN standard_type s on s.id = r.standard_type_id
			LEFT JOIN category c ON c.id = r.category_id
			WHERE r.race_id = %d
			ORDER BY r.position ASC, r.result ASC", Rules::START_OF_2015_AGE_GRADING, $raceId);

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getPreviousPersonalBest($runnerIds, int $newRaceId)
    {
        $sql = "SELECT r1.runner_id as runnerId, MIN(r2.result) as previousBest
              FROM `results` r1
              INNER JOIN `race` ra1 ON r1.race_id = ra1.id              
              inner join `results` r2 on r1.runner_id = r2.runner_id   
              INNER JOIN `race` ra2 ON r2.race_id = ra2.id          
              where r1.race_id = $newRaceId
              AND ra1.date > ra2.date AND r2.personal_best = 1 
              and r1.personal_best = 1
              AND ra1.distance_id = ra2.distance_id
              AND r1.runner_id in ($runnerIds)
              GROUP BY r1.runner_id";

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getMemberInsightsRaceDistance(int $distanceId)
    {
        $sql = $this->resultsDatabase->prepare("
        SELECT FLOOR(TIME_TO_SEC(cast(result as TIME))/60) as timeBand, count(r.id) as count 
        FROM results r 
        INNER JOIN race a ON a.id = r.race_id 
        WHERE a.distance_id = %d AND r.result != '00:00:00' AND r.result != '' 
        GROUP BY TimeBand 
        ORDER BY TimeBand Asc", $distanceId);

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getRunnerDistanceResultMinMaxAverage(int $runnerId, int $distanceId)
    {
        $sql = $this->resultsDatabase->prepare("
        select 
            MIN(result) as fastest, 
            MAX(result) as slowest, 
            SUBSTR(SEC_TO_TIME(AVG(substring(result, 1, 2) * 3600) + (substring(result, 4, 2) * 60) + (substring(result, 7, 2))), 1, 8) as mean 
        from results r 
            inner join race a on a.id = r.race_id 
        where 
            runner_id = %d 
            and a.distance_id = %d 
            and result != '00:00:00'", $runnerId, $distanceId);

        return $this->executeResultQuery(__METHOD__, $sql);
    }

    public function getResult(int $resultId)
    {
        $sql =  $this->resultsDatabase->prepare("SELECT r.id, 0 as 'eventId', r.runner_id as 'runnerId', r.position, ra.date as 'date', r.result as 'time', r.result as 'result', r.info, r.event_division_id as 'eventDivisionId', r.standard_type_id as 'standardTypeId', r.category_id as 'categoryId', r.personal_best as 'isPersonalBest', r.season_best as 'isSeasonBest', r.grandprix as 'isGrandPrixResult',
			r.scoring_team as 'team', ra.id as 'raceId', p.sex_id, e.name as 'eventName',
			CASE
			   WHEN ra.date >= '%s' THEN r.percentage_grading_2015
			   ELSE r.percentage_grading
			END as percentageGrading,
			r.percentage_grading_best as percentageGradingBest,
			ra.course_number as 'courseNumber', p.name as 'runnerName', e.name as 'eventName', ra.description as 'raceDescription'
			FROM results r
			INNER JOIN runners p on p.id = r.runner_id
			INNER JOIN race ra ON r.race_id = ra.id
			INNER JOIN events e ON ra.event_id = e.id
			WHERE r.id = %d
			ORDER BY ra.date DESC, ra.id, r.position ASC, r.result ASC", Rules::START_OF_2015_AGE_GRADING, $resultId);

        return $this->executeResultQuery(__METHOD__, $sql);
    }

    public function updateResult(int $resultId, string $field, string $value)
    {
        // TODO Changing raceId could mean new results generation for PBs etc

        if ($field == 'info' || $field == 'position' || $field == "scoring_team" || $field == 'race_id') {
            return $this->updateEntity(__METHOD__, 'results', $field, $value, $resultId, function ($id) {
                return $this->getResult($id);
            });
        } else if ($field == 'result') {
            // Update result, percentage grading and standard
            $existingResult = $this->getResult($resultId);
            $newResult = $value;
            $pb = 0;
            $seasonBest = 0;
            $standardType = 0;
            $ageGrading = 0;
            $ageGrading2015 = 0;

            if ($this->isCertificatedCourseAndResult($existingResult->raceId, $newResult)) {
                $pb = $this->isPersonalBest($existingResult->raceId, $existingResult->runnerId, $newResult, $existingResult->date);

                $seasonBest = $this->isSeasonBest($existingResult->raceId, $existingResult->runnerId, $newResult, $existingResult->date);

                $ageGrading = $this->getAgeGrading($newResult, $existingResult->runnerId, $existingResult->raceId);

                if ($existingResult->date >= Rules::START_OF_2015_AGE_GRADING) {
                    $ageGrading2015 = $this->get2015FactorsAgeGrading($newResult, $existingResult->runnerId, $existingResult->raceId);
                }

                $standardType = $this->getResultStandardTypeId($existingResult->categoryId, $newResult, $existingResult->raceId, $ageGrading2015, $existingResult->date);
            }

            $result = $this->resultsDatabase->update(
                'results',
                array(
                    'result' => $value,
                    'personal_best' => $pb,
                    'season_best' => $seasonBest,
                    'standard_type_id' => $standardType,
                    'percentage_grading' => $ageGrading,
                    'percentage_grading_2015' => $ageGrading2015,
                ),
                array('id' => $resultId),
                array(
                    '%s',
                    '%d',
                    '%d',
                    '%d',
                    '%f',
                    '%f',
                ),
                array('%d')
            );

            if ($result !== false) {
                if ($ageGrading > 0) {
                    // TODO check response for number of results
                    $response = $this->updatePercentageGradingPersonalBest($resultId, $existingResult->runnerId, $existingResult->date);
                    if (!$response) {
                        return $response;
                    }

                    $isNewStandard = $this->isNewStandard($resultId);

                    if ($isNewStandard) {
                        $this->saveStandardCertificate($resultId);
                    }
                }

                return $this->getResult($resultId);
            }

            return new \WP_Error(
                __METHOD__,
                'Unknown error in updating result in to the database',
                array('status' => 500, 'code' => 002)
            );
        }

        return new \WP_Error(
            __METHOD__,
            'Field in result may not be updated',
            array('status' => 500, 'code' => 003)
        );
    }

    public function deleteResult(int $resultId)
    {
        $sql = $this->resultsDatabase->prepare('DELETE FROM results WHERE id = %d', $resultId);

        return $this->executeQuery(__METHOD__, $sql);
    }

    public function insertResult($result)
    {
        $categoryId = $this->getCategoryId($result['runnerId'], $result['date']);
        $pb = 0;
        $seasonBest = 0;
        $standardType = 0;
        $ageGrading = 0;
        $ageGrading2015 = 0;

        if ($this->isCertificatedCourseAndResult($result['raceId'], $result['result'])) {
            $pb = $this->isPersonalBest($result['raceId'], $result['runnerId'], $result['result'], $result['date']);

            $seasonBest = $this->isSeasonBest($result['raceId'], $result['runnerId'], $result['result'], $result['date']);

            $ageGrading = $this->getAgeGrading($result['result'], $result['runnerId'], $result['raceId']);

            if ($result['date'] >= Rules::START_OF_2015_AGE_GRADING) {
                $ageGrading2015 = $this->get2015FactorsAgeGrading($result['result'], $result['runnerId'], $result['raceId']);
            }

            $standardType = $this->getResultStandardTypeId($categoryId, $result['result'], $result['raceId'], $ageGrading2015, $result['date']);
        }

        $sql = $this->resultsDatabase->prepare(
            '
			INSERT INTO results (`result`, `info`, `runner_id`, `position`, `category_id`, `personal_best`, `season_best`, `standard_type_id`, `grandprix`, `scoring_team`, `race_id`, `percentage_grading`, `percentage_grading_2015`)
			VALUES(%s, %s, %d, %d, %d, %d, %d, %d, %d, %d, %d, %f, %f)',
            $result['result'],
            $result['info'],
            $result['runnerId'],
            $result['position'],
            $categoryId,
            $pb,
            $seasonBest,
            $standardType,
            $result['isGrandPrixResult'],
            $result['team'] != null ? $result['team'] : 0,
            $result['raceId'],
            $ageGrading,
            $ageGrading2015
        );

        $success = $this->resultsDatabase->query($sql);

        if ($success === FALSE) {
            return new \WP_Error(
                __METHOD__,
                'Unknown error in inserting results in to the database : ',
                array('status' => 500, 'sql' => $sql)
            );
        }

        // Get the ID of the inserted event
        $resultId = $this->resultsDatabase->insert_id;

        if ($ageGrading > 0) {
            // TODO check response for number of results
            $this->updatePercentageGradingPersonalBest($resultId, $result['runnerId'], $result['date']);

            $isNewStandard = $this->isNewStandard($resultId);

            if ($isNewStandard) {
                $this->saveStandardCertificate($resultId);
            }
        }

        // If a PB query to see whether a new certificate is required and if we need to re-evaluate later PB
        if ($pb) {
            $this->checkAndUpdatePersonalBest($result['runnerId']);
        }

        return $this->getResult($resultId);
    }

    public function getResults(int $eventId, string $fromDate, string $toDate, int $numberOfResults)
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

        $sql = $this->resultsDatabase->prepare("SELECT r.id, ra.event_id as 'eventId', r.runner_id as 'runnerId', r.position, ra.date as 'date', r.result as 'result', r.performance as 'performance', r.info, r.event_division_id as 'eventDivisionId', r.standard_type_id as 'standardTypeId', r.category_id as 'categoryId', r.personal_best as 'isPersonalBest', r.season_best as 'isSeasonBest', r.grandprix as 'isGrandPrixResult',
			r.scoring_team as 'team',
			CASE
			   WHEN ra.date >= '%s' THEN r.percentage_grading_2015
			   ELSE r.percentage_grading
			END as percentageGrading,
			p.name as 'runnerName',
			e.name as 'eventName', ra.description as 'raceDescription'
			FROM results r
			INNER JOIN runners p on p.id = r.runner_id
			INNER JOIN race ra ON r.race_id = ra.id
			INNER JOIN events e ON ra.event_id = e.id
			WHERE 1=1 $whereEvent $whereFrom $whereTo
			ORDER BY ra.date DESC, ra.id, r.position ASC, r.result ASC LIMIT %d", Rules::START_OF_2015_AGE_GRADING, $limit);

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getCountyChampions()
    {
        $sql = "SELECT r.runner_id as runnerId, p.Name as runnerName, e.id as eventId, e.Name as eventName, ra.date, r.result as result, r.performance as performance, c.code as categoryCode, ra.id as raceId, ra.description, d.id as distanceId, d.distance
				FROM results AS r
                INNER JOIN race ra ON r.race_id = ra.id
                LEFT JOIN distance d ON ra.distance_id = d.id
				INNER JOIN events e ON ra.event_id = e.id
				INNER JOIN runners p ON r.runner_id = p.id
				INNER JOIN category c ON r.category_id = c.id
				WHERE r.county_champion = 1
				ORDER BY ra.date desc, categoryCode asc";

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    private function getResultStandardTypeId($catgeoryId, $result, $raceId, $percentageGrading2015, $resultDate)
    {
        if ($percentageGrading2015 > 0 && $resultDate >= Rules::START_OF_2015_AGE_GRADING) {
            if ($percentageGrading2015 >= 86) {
                return 14;
            }

            if ($percentageGrading2015 >= 80) {
                return 15;
            }

            if ($percentageGrading2015 >= 74) {
                return 16;
            }

            if ($percentageGrading2015 >= 68) {
                return 17;
            }

            if ($percentageGrading2015 >= 62) {
                return 18;
            }

            if ($percentageGrading2015 >= 56) {
                return 19;
            }

            if ($percentageGrading2015 >= 50) {
                return 20;
            } else {
                return 0;
            }
        }

        return $this->getStarStandardTypeIdBefore2015($catgeoryId, $result, $raceId);
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

    private function getAgeGrading(string $result, int $runnerId, int $raceId)
    {

        $sql = $this->resultsDatabase->prepare("
			select
			CASE
				WHEN d.result_measurement_unit_type_id >= 2 THEN
				 (ROUND((record.record * 100) / ('%s' * grade.grading_percentage), 2))
				ELSE
				  (ROUND((record.record * 100) / (((substring('%s', 1, 2) * 3600) +  (substring('%s', 4, 2) * 60) + (substring('%s', 7, 2))) * grade.grading_percentage), 2))
			END as percentageGrading
			FROM
			 wma_age_grading grade
			 INNER JOIN wma_records record ON grade.distance_id = record.distance_id
			 INNER JOIN distance d ON record.distance_id = d.id
			 INNER JOIN race race ON d.id = race.distance_id,
			 runners p
            WHERE
            race.id = %d 
            AND p.id = %d
			AND p.dob <> '0000-00-00'
			AND p.dob IS NOT NULL
			AND grade.age = (YEAR(race.date) - YEAR(p.dob) - IF(DATE_FORMAT(p.dob, '%%j') > DATE_FORMAT(race.date, '%%j'), 1, 0))
			AND grade.sex_id = p.sex_id
			AND grade.sex_id = record.sex_id
			", $result, $result, $result, $result, $raceId, $runnerId);

        $result = $this->resultsDatabase->get_var($sql);

        if ($result === false) {
            return 0;
        }

        return $result;
    }

    private function get2015FactorsAgeGrading(string $result, int $runnerId, int $raceId)
    {

        $sql = $this->resultsDatabase->prepare("
			select
			CASE
				WHEN d.result_measurement_unit_type_id >= 2 THEN
				 (ROUND((record.record * 100) / ('%s' * grade.grading_percentage), 2))
				ELSE
				  (ROUND((record.record * 100) / (((substring('%s', 1, 2) * 3600) +  (substring('%s', 4, 2) * 60) + (substring('%s', 7, 2))) * grade.grading_percentage), 2))
			END as percentageGrading
			FROM
			 wma_age_grading_2015 grade
			 INNER JOIN wma_records_2015 record ON grade.distance_id = record.distance_id
			 INNER JOIN distance d ON record.distance_id = d.id
			 INNER JOIN race race ON d.id = race.distance_id AND race.course_type_id = grade.course_type_id	AND race.course_type_id = record.course_type_id,
			 runners p
			WHERE
			race.id = %d
			AND p.id = %d
			AND p.dob <> '0000-00-00'
			AND p.dob IS NOT NULL
			AND grade.age = (YEAR(race.date) - YEAR(p.dob) - IF(DATE_FORMAT(p.dob, '%%j') > DATE_FORMAT(race.date, '%%j'), 1, 0))
			AND grade.sex_id = p.sex_id
			AND grade.sex_id = record.sex_id
			", $result, $result, $result, $result, $raceId, $runnerId);

        $result = $this->resultsDatabase->get_var($sql);

        if ($result === false) {
            return 0;
        }

        return $result;
    }

    private function isCertificatedCourseAndResult($raceId, $result)
    {
        // TODO
        // First determine if a valid event and result to get a PB
        if ($result == "00:00:00" || $result == "00:00" || $result == "" || $result == null) {
            return false;
        }

        $race = $this->getRace($raceId);

        return $race && $race->distance != null && in_array($race->courseTypeId, array(1, 3, 6));
    }

    private function isPersonalBest($raceId, $runnerId, $result, $date)
    {
        // TODO
        // IF the latest result check all (previous) results
        // ELSE reset all for valid result (e.g. course type, result)
        $sql = $this->resultsDatabase->prepare(
            "select
                count(r.id)
                from
                race ra1,
                race ra2,
                results r
                where
                ra1.id = r.race_id AND
                ra1.distance_id = ra2.distance_id AND
                ra2.id = %d AND
                ra1.distance_id <> 0 AND
								r.result != '00:00:00' AND
                				r.result != '' AND
								r.result <= '%s' AND
                r.runner_id = %d AND
                r.race_id <> %d AND
                ra1.date < '%s' AND
                ra1.course_type_id IN (%d, %d, %d) AND
                ra2.course_type_id IN (%d, %d, %d)
								ORDER BY result
								LIMIT 1",
            $raceId,
            $result,
            $runnerId,
            $raceId,
            $date,
            CourseTypes::ROAD,
            CourseTypes::TRACK,
            CourseTypes::INDOOR,
            CourseTypes::ROAD,
            CourseTypes::TRACK,
            CourseTypes::INDOOR
        );

        $count = $this->resultsDatabase->get_var($sql);

        return ($count == 0);
    }

    private function checkAndUpdatePersonalBest(int $resultId)
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
			ralaterRacesce2.date > pbRace.date AND
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
			set pbResults.personal_best = 1
			where pbResults.id = r.id
			",
            $resultId,
            CourseTypes::ROAD,
            CourseTypes::TRACK,
            CourseTypes::INDOOR
        );

        return $this->executeQuery(__METHOD__, $sql);
    }

    private function updatePercentageGradingPersonalBest($resultId, $runnerId, $date)
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
			AND ((r.percentage_grading_2015 > 0 AND race.date >= '%') OR
				 (r.percentage_grading > 0 AND race.date < '%s'))",
            $date,
            $runnerId,
            Rules::START_OF_2015_AGE_GRADING,
            Rules::START_OF_2015_AGE_GRADING
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
					AND ((r1.percentage_grading_2015 > r2.percentage_grading_2015 AND '%s' >= '%s') OR
						(r1.percentage_grading > r2.percentage_grading AND '%s' < '%s'))
				) = 0
				", $resultId, $runnerId, $resultId, $date, Rules::START_OF_2015_AGE_GRADING, $date, Rules::START_OF_2015_AGE_GRADING);

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
                                    CASE
                                    WHEN a.date >= '%s' THEN r.percentage_grading_2015
                                    ELSE r.percentage_grading
                                    END as percentageGrading
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
				", Rules::START_OF_2015_AGE_GRADING, $runnerId);

            $this->executeQuery(__METHOD__, $sql);
        }

        return true;
    }

    private function isSeasonBest(int $raceId, int $runnerId, string $result, string $date)
    {
        $sql = $this->resultsDatabase->prepare(
            "select
                count(r.id)
                from
                race ra,
                race ra2,
                results r
                where
                ra.id = r.race_id AND
                ra.distance_id = ra2.distance_id AND
                ra2.id = %d AND
                ra.distance_id <> 0 AND
								r.result != '00:00:00' AND
                                r.result != '' AND
								r.result <= %s AND
                r.runner_id = %d AND
                YEAR(ra.date) = YEAR('%s') AND
                ra.date < '%s' AND
                r.race_id <> %d AND
                ra.course_type_id IN (%d, %d, %d) AND
                ra2.course_type_id IN (%d, %d, %d)
                ORDER BY result
                LIMIT 1",
            $raceId,
            $result,
            $runnerId,
            $date,
            $date,
            $raceId,
            CourseTypes::ROAD,
            CourseTypes::TRACK,
            CourseTypes::INDOOR,
            CourseTypes::ROAD,
            CourseTypes::TRACK,
            CourseTypes::INDOOR
        );

        $count = $this->resultsDatabase->get_var($sql);

        return ($count == 0);
    }

    private function isNewStandard(int $resultId)
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

    private function saveStandardCertificate(int $resultId)
    {
        $sql = $this->resultsDatabase->prepare("insert into standard_certificates set result_id=%d, issued = 0", $resultId);

        return $this->executeQuery(__METHOD__, $sql);
    }

    private function getCategoryId(int $runnerId, string $date)
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

    private function getRace(int $raceId)
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
}
<?php

namespace IpswichJAFFARunningClubAPI\V2\Races;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';

use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class RacesDataAccess extends DataAccess
{
    public function getRaces(int $eventId)
    {
        $sql = $this->resultsDatabase->prepare(
            'SELECT ra.id, 
            e.id AS eventId, 
            e.Name as name, 
            ra.date, 
            ra.description, 
            ra.course_type_id AS courseTypeId, 
            c.description AS courseType, 
            ra.area, 
            ra.county, 
            ra.country_code AS countryCode, 
            ra.conditions, 
            ra.venue, 
            d.id as distanceId, 
            d.distance, 
            ra.grand_prix as isGrandPrixRace, 
            ra.course_number as courseNumber, 
            ra.meeting_id as meetingId, 
            m.name as meetingName, 
            d.result_measurement_unit_type_id as resultMeasurementUnitTypeId, 
            d.result_unit_type_id as resultUnitTypeId,
            l.name as leagueName, 
            l.starting_year as leagueStartingYear, 
            count(r.id) as count, 
            ra.report as report
            FROM `events` e
            INNER JOIN `race` ra ON ra.event_id = e.id
            LEFT JOIN `results` r ON ra.id = r.race_id
            LEFT JOIN `distance` d ON ra.distance_id = d.id
            LEFT JOIN `course_type` c ON ra.course_type_id = c.id
            LEFT JOIN `leagues` l ON ra.league_id = l.id
            LEFT JOIN `meeting` m ON ra.meeting_id = m.id
            WHERE e.id = %d
            GROUP BY ra.id, eventId, name, ra.date, ra.description, courseTypeId, courseType, ra.area, ra.county, countryCode, ra.conditions, ra.venue, d.distance, isGrandPrixRace
            ORDER BY ra.date DESC, ra.description',
            $eventId
        );

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getRace(int $raceId)
    {
        $sql = $this->resultsDatabase->prepare(
            'SELECT
				ra.id,
				 e.id AS eventId,
				  e.name as eventName,
				   ra.description as description,
				    ra.date,
					 ra.course_type_id AS courseTypeId,
					  c.description AS courseType,
					   ra.area, 
                       ra.county,
					    ra.country_code AS countryCode,
						 ra.conditions,
						  ra.venue,
						   d.distance,
						    ra.grand_prix as isGrandPrixRace,
							 ra.course_number as courseNumber,
							  ra.league_id as leagueId,
							   ra.meeting_id as meetingId,
							    d.result_measurement_unit_type_id as resultMeasurementUnitTypeId,
                                 d.result_unit_type_id as resultUnitTypeId,
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

    public function insertRace($race)
    {
        $sql = $this->resultsDatabase->prepare(
            '
		INSERT INTO `race`(`event_id`, `date`, `course_number`, `venue`, `description`, `conditions`, `distance_id`, `course_type_id`, `county`, `country_code`, `area`, `grand_prix`)
		VALUES(%d, %s, %s, %s, %s, %s, %d, %d, %s, %s, %s, %d)',
            $race['eventId'],
            $race['date'],
            $race['courseNumber'],
            $race['venue'],
            $race['description'],
            $race['conditions'],
            $race['distanceId'],
            $race['courseTypeId'],
            $race['county'],
            $race['countryCode'],
            $race['area'],
            $race['isGrandPrixRace']
        );

        return $this->insertEntity(__METHOD__, $sql, function ($id) {
			return $this->getRace($id);
		});
    }

    public function deleteRace(int $raceId)
    {
        $sql = $this->resultsDatabase->prepare('DELETE FROM race WHERE id = %d;', $raceId);

        return $this->executeQuery(__METHOD__, $sql);
    }

    public function updateRace(int $raceId, string $field, string $value)
    {
        // Race date and distance can not be changed - affected PBs etc
        if (
            $field == 'event_id' ||
            $field == 'description' ||
            $field == 'course_type_id' ||
            $field == 'course_number' ||
            $field == 'area' ||
            $field == 'county' ||
            $field == 'country_code' ||
            $field == 'venue' ||
            $field == 'conditions' ||
            $field == 'meeting_id' ||
            $field == 'league_id' ||
            $field == 'grand_prix'
        ) {
            if ($field == 'country_code' && $value != 'GB') {
                $result = $this->resultsDatabase->update(
                    'race',
                    array(
                        $field => $value, 'county' => null, 'area' => null
                    ),
                    array('id' => $raceId),
                    array(
                        '%s', '%s', '%s'
                    ),
                    array('%d')
                );
            } else {
                $result = $this->resultsDatabase->update(
                    'race',
                    array(
                        $field => $value
                    ),
                    array('id' => $raceId),
                    array(
                        '%s'
                    ),
                    array('%d')
                );
            }

            if ($result) {
                // Get updated race
                return $this->getRace($raceId);
            }

            return new \WP_Error(
                __METHOD__,
                'Unknown error in updating event in to the database',
                array('status' => 500)
            );
        }

        return new \WP_Error(
            __METHOD__,
            'Field in event may not be updated',
            array('status' => 500, 'Field' => $field, 'Value' => $value)
        );
    }

    // TODO
    public function updateRaceDistance($raceId, $distanceId)
    {
        $results = $this->getRaceResults($raceId);

        // Update race distance
        $success = $this->resultsDatabase->update(
            'race',
            array(
                'distance_id' => $distanceId,
            ),
            array('id' => $raceId),
            array(
                '%d'
            ),
            array('%d')
        );

        // For each race result
        for ($i = 0; $i < count($results); $i++) {
            // Update result, percentage grading and standard
            $existingResult = $results[$i]->result;

            $pb = 0;
            $seasonBest = 0;
            $standardType = 0;
            $ageGrading = 0;
            $ageGrading2015 = 0;

            if ($this->isCertificatedCourseAndResult($results[$i]->raceId, '', $existingResult)) {
                $pb = $this->isPersonalBest($results[$i]->raceId, $results[$i]->runnerId, $existingResult, $results[$i]->date);

                $seasonBest = $this->isSeasonBest($results[$i]->raceId, $results[$i]->runnerId, $existingResult, $results[$i]->date);

                $ageGrading = $this->getAgeGrading($existingResult, $results[$i]->runnerId, $results[$i]->raceId);

                if ($results[$i]->date >= self::START_OF_2015_AGE_GRADING) {
                    $ageGrading2015 = $this->get2015FactorsAgeGrading($existingResult, $results[$i]->runnerId, $results[$i]->raceId);
                }

                $standardType = $this->getResultStandardTypeId($results[$i]->categoryId, $existingResult, $results[$i]->raceId, $ageGrading2015, $results[$i]->date);
            }

            $success = $this->resultsDatabase->update(
                'results',
                array(
                    'personal_best' => $pb,
                    'season_best' => $seasonBest,
                    'standard_type_id' => $standardType,
                    'percentage_grading' => $ageGrading,
                    'percentage_grading_2015' => $ageGrading2015,
                ),
                array('id' => $results[$i]->id),
                array(
                    '%d',
                    '%d',
                    '%d',
                    '%f',
                    '%f',
                ),
                array('%d')
            );

            if ($success) {
                if ($ageGrading > 0) {
                    // TODO check response for number of results
                    $response = $this->updatePercentageGradingPersonalBest($results[$i]->id, $results[$i]->runnerId, $results[$i]->date);
                    if ($response != true) {
                        return $response;
                    }

                    $isNewStandard = $this->isNewStandard($results[$i]->id);

                    if ($isNewStandard) {
                        $this->saveStandardCertificate($results[$i]->id);
                    }
                }
            }
        }

        if ($success) {
            // Get updated race
            return $this->getRace($raceId);
        }

        return new \WP_Error(__METHOD__,
            'Unknown error in updating race in to the database', array('status' => 500));
    }
}

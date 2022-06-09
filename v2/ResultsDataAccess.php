<?php
/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

namespace IpswichJAFFARunningClubAPI\V2;

require_once plugin_dir_path( __DIR__ ) .'/Constants/CourseTypes.php';

class ResultsDataAccess
{

    private $jdb;
    const START_OF_2015_AGE_GRADING = '2017-01-01';
    const GENERIC_ERROR_MESSAGE = 'Unknown error in reading results from the database';

    public function __construct($db)
    {
        $this->jdb = $db;
    }

    public function getEventRaceInsightsByYear($eventId)
    {
        $sql = $this->jdb->prepare("
        SELECT YEAR(race.date) as year, d.distance, count(r.id) as count, MIN(NULLIF(NULLIF(r.result, '00:00:00'), '')) as min, MAX(r.result) as max, 
        SUBSTR(SEC_TO_TIME(AVG((substring(r.result, 1, 2) * 3600) + (substring(r.result, 4, 2) * 60) + substring(r.result, 7, 2))), 1, 8) as mean
        FROM `results` r
        INNER JOIN race race ON r.race_id = race.id
        LEFT JOIN distance d ON d.id = race.distance_id
        WHERE race.event_id = %d
        GROUP BY year, distance        
        ORDER BY year", $eventId);

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE,
                array(
                    'status' => 500
                ));
        }

        return $results;
    }

    public function getEventRaceInsightsByDistance($eventId)
    {
        $sql = $this->jdb->prepare("
        SELECT d.distance, count(r.id) as count,  MIN(NULLIF(NULLIF(r.result, '00:00:00'), '')) as min, MAX(r.result) as max, SUBSTR(SEC_TO_TIME(AVG((substring(r.result, 1, 2) * 3600) + (substring(r.result, 4, 2) * 60) + substring(r.result, 7, 2))), 1, 8) as mean 
        FROM `race` race 
        INNER JOIN `distance` d on race.distance_id = d.id 
        INNER JOIN `results` r ON race.id = r.race_id 
        WHERE race.event_id = %d 
        GROUP BY distance", $eventId);

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE,
                array(
                    'status' => 500
                ));
        }

        return $results;
    }

    public function getCategories()
    {
        $sql = 'SELECT id, code, description, sex_id as sexId, default_category as isDefault					 
			    FROM category
                WHERE id > 0
                ORDER BY sex_id, default_category desc, age_greater_equal';

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE,
                array(
                    'status' => 500
                ));
        }

        return $results;
    }

    public function getDistances()
    {
        $sql = 'SELECT
			         id, distance as text,
					 result_measurement_unit_type_id as resultMeasurementUnitTypeId,
					 miles
			         FROM distance';

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE,
                array(
                    'status' => 500
                ));
        }

        return $results;
    }

    public function getCourseTypes()
    {
        $sql = 'SELECT
					id,
					description
					FROM course_type
					ORDER BY id ASC';

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

      return $results;
    }
	
	public function getMeanPercentageGradingByMonth() 
	{
		$sql = "SELECT DATE_FORMAT(race.date, '%Y-%m-01') as date, c.code as categoryCode, ROUND(AVG(r.percentage_grading_2015), 2) as meanGrading
				FROM race race
				inner join results r on r.race_id = race.id
				INNER join category c on c.id = r.category_id
				where r.percentage_grading_2015 > 0
				group by date, categoryCode
				ORDER BY date, categoryCode";

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
	}
	
	public function getMemberInsightsRaceDistance($distanceId) 
	{
		$sql = $this->jdb->prepare("
        SELECT FLOOR(TIME_TO_SEC(cast(result as TIME))/60) as timeBand, count(r.id) as count 
        FROM results r 
        INNER JOIN race a ON a.id = r.race_id 
        WHERE a.distance_id = %d AND r.result != '00:00:00' AND r.result != '' 
        GROUP BY TimeBand 
        ORDER BY TimeBand Asc", $distanceId);

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
	}

    public function getRunnerDistanceResultMinMaxAverage($runnerId, $distanceId)
    {
        $sql = $this->jdb->prepare("
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

        $results = $this->jdb->get_row($sql, OBJECT);

        if ($results === FALSE) {
            return new \WP_Error('getRunnerDistanceResultMinMaxAverage',
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getEventTopAttendees($eventId) 
	{
		$sql = $this->jdb->prepare("
        select t1.name,
        t1.year,        
        sum(t2.count) as runningTotal
        FROM
        (
            SELECT p.name as name, YEAR(race.date) as year, count(r.id) as count
            FROM race race  
            INNER JOIN results r ON r.race_id = race.id
            INNER JOIN runners p ON p.id = r.runner_id
            wHERE race.event_id = %d
            GROUP BY name, year) as t1
        INNER JOIN
        (
            SELECT p.name as name, YEAR(race.date) as year, count(r.id) as count
            FROM race race  
            INNER JOIN results r ON r.race_id = race.id
            INNER JOIN runners p ON p.id = r.runner_id
            wHERE race.event_id = %d
            GROUP BY name, year) as t2
        on t1.name=t2.name and t1.year >= t2.year
        group by t1.name, t1.year  
        ORDER BY t1.year ASC", $eventId, $eventId);

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
	}

    public function getEvents()
    {

        $sql = 'SELECT e.id as id, e.name as name, e.website, MAX(ra.date) as lastRaceDate, count( r.id ) AS count
			FROM `events` e
			LEFT JOIN `race` ra on ra.event_id = e.id
			LEFT JOIN `results` r ON ra.id = r.race_id
			GROUP BY e.id, e.name, e.website
			ORDER BY lastRaceDate DESC, e.name ASC ';

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function insertEvent($event)
    {
        $sql = $this->jdb->prepare('INSERT INTO events (`name`, `website`) VALUES(%s, %s);', $event['name'], $event['website']);

        $result = $this->jdb->query($sql);

        if ($result) {
            return $this->getEvent($this->jdb->insert_id);
        }

        return new \WP_Error(__METHOD__,
            'Unknown error in inserting event in to the database', array('status' => 500));
    }

    public function getRace($raceId)
    {

        $sql = $this->jdb->prepare(
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
            $raceId);

        $results = $this->jdb->get_row($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                'Unknown error in reading race from the database', array('status' => 500));
        }

        return $results;
    }

    public function insertRace($race)
    {
        $sql = $this->jdb->prepare('
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
            $race['isGrandPrixRace']);

        $result = $this->jdb->query($sql);

        if ($result) {
            return $this->getRace($this->jdb->insert_id);
        }

        return new \WP_Error(__METHOD__,
            'Unknown error in inserting race in to the database', array('status' => 500));
    }

    public function getEvent($eventId)
    {
        // Get updated event
        $sql = $this->jdb->prepare("
		SELECT
		e.id,
		 e.name,
		  e.website
		FROM `events` e
		WHERE e.id = %d",
            $eventId);

        $result = $this->jdb->get_row($sql, OBJECT);

        if ($result) {
            return $result;
        }

        return new \WP_Error(__METHOD__,
            'Unknown error in getting the event in to the database', array('status' => 500));
    }

    public function updateEvent($eventId, $field, $value)
    {
        // Only name and website may be changed.
        if ($field == 'name' || $field == 'website') {
            $result = $this->jdb->update(
                'events',
                array(
                    $field => $value,
                ),
                array('id' => $eventId),
                array(
                    '%s',
                ),
                array('%d')
            );

            if ($result) {
                // Get updated event
                return $this->getEvent($eventId);
            }

            return new \WP_Error(__METHOD__,
                'Unknown error in updating event in to the database', array('status' => 500));
        }

        return new \WP_Error(__METHOD__,
            'Field in event may not be updated', array('status' => 500));
    }

    public function getRaces($eventId)
    {
        $sql = $this->jdb->prepare(
            'SELECT ra.id, e.id AS eventId, e.Name as name, ra.date, ra.description, ra.course_type_id AS courseTypeId, c.description AS courseType, ra.area, ra.county, ra.country_code AS countryCode, ra.conditions, ra.venue, d.id as distanceId, d.distance, ra.grand_prix as isGrandPrixRace, ra.course_number as courseNumber, ra.meeting_id as meetingId, m.name as meetingName, d.result_measurement_unit_type_id as resultMeasurementUnitTypeId, l.name as leagueName, l.starting_year as leagueStartingYear, count(r.id) as count, ra.report as report
				FROM `events` e
				INNER JOIN `race` ra ON ra.event_id = e.id
                LEFT JOIN `results` r ON ra.id = r.race_id
				LEFT JOIN `distance` d ON ra.distance_id = d.id
				LEFT JOIN `course_type` c ON ra.course_type_id = c.id
                LEFT JOIN `leagues` l ON ra.league_id = l.id
                LEFT JOIN `meeting` m ON ra.meeting_id = m.id
				WHERE e.id = %d
				GROUP BY ra.id, eventId, name, ra.date, ra.description, courseTypeId, courseType, ra.area, ra.county, countryCode, ra.conditions, ra.venue, d.distance, isGrandPrixRace
				ORDER BY ra.date DESC, ra.description', $eventId);

        return $this->jdb->get_results($sql, OBJECT);
    }

    public function updateRaceDistance($raceId, $distanceId)
    {
        $results = $this->getRaceResults($raceId);

        // Update race distance
        $success = $this->jdb->update(
            'race',
            array(
                'distance_id' => $distanceId,
            ),
            array('id' => $raceId),
            array(
                '%d',
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

            $success = $this->jdb->update(
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

    public function updateRace($raceId, $field, $value)
    {
        // Race date and distance can not be changed - affected PBs etc
        if ($field == 'event_id' ||
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
            $field == 'grand_prix') {
            if ($field == 'country_code' && $value != 'GB') {
                $result = $this->jdb->update(
                    'race',
                    array(
                        $field => $value, 'county' => null, 'area' => null,
                    ),
                    array('id' => $raceId),
                    array(
                        '%s', '%s', '%s',
                    ),
                    array('%d')
                );
            } else {
                $result = $this->jdb->update(
                    'race',
                    array(
                        $field => $value,
                    ),
                    array('id' => $raceId),
                    array(
                        '%s',
                    ),
                    array('%d')
                );
            }

            if ($result) {
                // Get updated race
                return $this->getRace($raceId);
            }

            return new \WP_Error(__METHOD__,
                'Unknown error in updating event in to the database', array('status' => 500));
        }

        return new \WP_Error(__METHOD__,
            'Field in event may not be updated', array('status' => 500, 'Field' => $field, 'Value' => $value));
    }

    public function updateRunner($runnerId, $field, $value)
    {
        if ($field == 'name') {
            $result = $this->jdb->update(
                'runners',
                array(
                    $field => $value,
                ),
                array('id' => $runnerId),
                array(
                    '%s',
                ),
                array('%d')
            );

            if ($result) {
                return $this->getRunner($runnerId);
            }

            return new \WP_Error(__METHOD__,
                'Unknown error in updating runner in to the database', array('status' => 500));
        }
        return new \WP_Error(__METHOD__,
            'Field in event may not be updated', array('status' => 500, 'Field' => $field, 'Value' => $value));
    }

    public function updateResult($resultId, $field, $value)
    {
        // TODO Changing raceId could mean new results generation for PBs etc

        if ($field == 'info' || $field == 'position' || $field == "scoring_team" || $field == 'race_id') {
            $result = $this->jdb->update(
                'results',
                array(
                    $field => $value,
                ),
                array('id' => $resultId),
                array(
                    '%s',
                ),
                array('%d')
            );

            if ($result !== false) {
                return $this->getResult($resultId);
            }

            return new \WP_Error(__METHOD__,
                'Unknown error in updating result in to the database.', array('status' => 500, 'code' => 001));
        } else if ($field == 'result') {
            // Update result, percentage grading and standard
            $existingResult = $this->getResult($resultId);
            $newResult = $value;
            $pb = 0;
            $seasonBest = 0;
            $standardType = 0;
            $ageGrading = 0;
            $ageGrading2015 = 0;

            if ($this->isCertificatedCourseAndResult($existingResult->raceId, '', $newResult)) {
                $pb = $this->isPersonalBest($existingResult->raceId, $existingResult->runnerId, $newResult, $existingResult->date);

                $seasonBest = $this->isSeasonBest($existingResult->raceId, $existingResult->runnerId, $newResult, $existingResult->date);

                $ageGrading = $this->getAgeGrading($newResult, $existingResult->runnerId, $existingResult->raceId);

                if ($existingResult->date >= self::START_OF_2015_AGE_GRADING) {
                    $ageGrading2015 = $this->get2015FactorsAgeGrading($newResult, $existingResult->runnerId, $existingResult->raceId);
                }

                $standardType = $this->getResultStandardTypeId($existingResult->categoryId, $newResult, $existingResult->raceId, $ageGrading2015, $existingResult->date);
            }

            $result = $this->jdb->update(
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
                    if ($response != true) {
                        return $response;
                    }

                    $isNewStandard = $this->isNewStandard($resultId);

                    if ($isNewStandard) {
                        $this->saveStandardCertificate($resultId);
                    }
                }

                return $this->getResult($resultId);
            }

            return new \WP_Error(__METHOD__,
                'Unknown error in updating result in to the database', array('status' => 500, 'code' => 002));
        }

        return new \WP_Error(__METHOD__,
            'Field in result may not be updated', array('status' => 500, 'code' => 003));
    }

    public function deleteEvent($eventId, $deleteResults)
    {

        $sql = $this->jdb->prepare('SELECT COUNT(r.id) FROM results r INNER JOIN race ra ON ra.id = r.race_id WHERE ra.event_id = %d LIMIT 1;', $eventId);

        $exists = $this->jdb->get_var($sql); // $jdb->get_var returns a single value from the database. In this case 1 if the find term exists and 0 if it does not.

        if ($exists != 0) {
            if (empty($deleteResults)) {
                return new \WP_Error(__METHOD__,
                    'Event cannot be deleted; a number results are associated with this event. Delete the existing results for this event and try again.', array('status' => 500));
            }

            // Delete all associated results
            $result = $this->deleteEventResults($eventId);
            if ($result != true) {
                return $result;
            }

        }

        $sql = $this->jdb->prepare('DELETE FROM events WHERE id = %d;', $eventId);

        $result = $this->jdb->query($sql);

        if (!$result) {
            return new \WP_Error(__METHOD__,
                'Unknown error in deleting event from the database', array('status' => 500, 'sql' => $sql));
        }

        return $result;
    }

    // TODO - change
    private function deleteEventResults($eventId)
    {
        $sql = $this->jdb->prepare('DELETE FROM results WHERE event_id = %d;', $eventId);

        $result = $this->jdb->query($sql);

        if (!$result) {
            return new \WP_Error(__METHOD__,
                'Unknown error in deleting results from the database', array('status' => 500));
        }

        return true;
    }

    public function deleteResult($resultId)
    {
        $sql = $this->jdb->prepare('DELETE FROM results WHERE id = %d;', $resultId);

        $result = $this->jdb->query($sql);

        if (!$result) {
            return new \WP_Error(__METHOD__,
                'Unknown error in deleting results from the database', array('status' => 500));
        }

        return true;
    }

    public function deleteRace($raceId)
    {
        $sql = $this->jdb->prepare('DELETE FROM race WHERE id = %d;', $raceId);

        $result = $this->jdb->query($sql);

        if (!$result) {
            return new \WP_Error(__METHOD__,
                'Unknown error in deleting race from the database', array('status' => 500));
        }

        return true;
    }

    public function getRunners($loggedIn = true)
    {

        if ($loggedIn === true) {
            $sql = "SELECT
				r.id,
				r.name,
				r.sex_id as 'sexId',
				r.dob as 'dateOfBirth',
				0 as 'isCurrentMember',
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

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getRunner($runnerId)
    {
        $sql = $this->jdb->prepare("select r.id, r.name, r.sex_id as 'sexId', r.dob as 'dateOfBirth', 0 as 'isCurrentMember', s.sex, c.code as 'ageCategory'
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

        $results = $this->jdb->get_row($sql, OBJECT);
        if (!$results) {
            return new \WP_Error(__METHOD__,
                'Unknown error in reading runner from the database', array('status' => 500));
        }
        return $results;
    }

    public function insertRunner($runner)
    {

        $sql = $this->jdb->prepare('INSERT INTO runners (`name`, `dob`, `sex_id`) VALUES(%s, %s, %d);', $runner['name'], $runner['dateOfBirth'], $runner['sexId']);

        $result = $this->jdb->query($sql);

        if ($result) {
            return $this->getRunner($this->jdb->insert_id);
        }

        return new \WP_Error(__METHOD__,
            'Unknown error in inserting runner in to the database', array('status' => 500));
    } // end function addRunner

    public function deleteRunner($id)
    {
        // Check whether their are any results for this runner already.
        $sql = $this->jdb->prepare('SELECT COUNT(`id`) FROM results WHERE runner_id = %d LIMIT 1;', $id);

        $exists = $this->jdb->get_var($sql);

        if ($exists != 0) {
            // Runners cannot be deleted; a number results are associated with this runner. Delete these results first and then try again.

            return new \WP_Error(__METHOD__,
                'Runner cannot be deleted; a number results are associated with this runner. Delete the existing results for this runner and try again.', array('status' => 409));
        }

        $sql = $this->jdb->prepare('DELETE FROM runners WHERE id = %d;', $id);

        $result = $this->jdb->query($sql);

        if (!$result) {
            return new \WP_Error(__METHOD__,
                'Unknown error in deleting runner from the database', array('status' => 500));
        }

        return $result;
    } // end function deleteRunner

    public function getGenders()
    {

        $sql = 'SELECT * FROM sex ORDER BY sex';

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    } // end function getGenders

    public function insertResult($result)
    {
        $categoryId = $this->getCategoryId($result['runnerId'], $result['date']);
        $pb = 0;
        $seasonBest = 0;
        $standardType = 0;
        $ageGrading = 0;
        $ageGrading2015 = 0;

        if ($this->isCertificatedCourseAndResult($result['raceId'], $result['courseId'], $result['result'])) {
            $pb = $this->isPersonalBest($result['raceId'], $result['runnerId'], $result['result'], $result['date']);

            $seasonBest = $this->isSeasonBest($result['raceId'], $result['runnerId'], $result['result'], $result['date']);

            $ageGrading = $this->getAgeGrading($result['result'], $result['runnerId'], $result['raceId']);

            if ($result['date'] >= self::START_OF_2015_AGE_GRADING) {
                $ageGrading2015 = $this->get2015FactorsAgeGrading($result['result'], $result['runnerId'], $result['raceId']);
            }

            $standardType = $this->getResultStandardTypeId($categoryId, $result['result'], $result['raceId'], $ageGrading2015, $result['date']);
        }

        $sql = $this->jdb->prepare('
			INSERT INTO results (`result`, `event_id`, `info`, `runner_id`, `position`, `category_id`, `personal_best`, `season_best`, `standard_type_id`, `grandprix`, `scoring_team`, `race_id`, `percentage_grading`, `percentage_grading_2015`)
			VALUES(%s, %d, %s, %d, %d, %d, %d, %d, %d, %d, %d, %d, %f, %f)',
            $result['result'], $result['eventId'], $result['info'], $result['runnerId'], $result['position'], $categoryId, $pb, $seasonBest, $standardType, $result['isGrandPrixResult'], $result['team'] != null ? $result['team'] : 0, $result['raceId'], $ageGrading, $ageGrading2015);

        $success = $this->jdb->query($sql);

        if ($success === FALSE) {
            return new \WP_Error(__METHOD__,
                'Unknown error in inserting results in to the database : ', array('status' => 500, 'sql' => $sql));
        }

        // Get the ID of the inserted event
        $resultId = $this->jdb->insert_id;

        if ($ageGrading > 0) {
            // TODO check response for number of results
            $this->updatePercentageGradingPersonalBest($resultId, $result['runnerId'], $result['date']);

            $isNewStandard = $this->isNewStandard($resultId);

            if ($isNewStandard) {
                $this->saveStandardCertificate($resultId);
            }
        }

        // If a PB query to see whether a new certificate is required and if we need to re-evaluate later PB
        if ($pb == true) {
            $this->checkAndUpdatePersonalBest($result['runnerId']);            
        }

        return $this->getResult($resultId);
    }

    public function getResults($eventId, $fromDate, $toDate, $numberOfResults)
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

        $sql = $this->jdb->prepare("SELECT r.id, ra.event_id as 'eventId', r.runner_id as 'runnerId', r.position, ra.date as 'date', r.result as 'time', r.result as 'result', r.info, r.event_division_id as 'eventDivisionId', r.standard_type_id as 'standardTypeId', r.category_id as 'categoryId', r.personal_best as 'isPersonalBest', r.season_best as 'isSeasonBest', r.grandprix as 'isGrandPrixResult',
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
			ORDER BY ra.date DESC, ra.id, r.position ASC, r.result ASC LIMIT %d", self::START_OF_2015_AGE_GRADING, $limit);

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getRaceResults($raceId)
    {
        $sql = $this->jdb->prepare("SELECT
			r.id, r.runner_id as 'runnerId',
			r.position, r.result as 'time',
			r.result as 'result',
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
			ORDER BY r.position ASC, r.result ASC", self::START_OF_2015_AGE_GRADING, $raceId);

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getPreviousPersonalBest($runnerIds, $newRaceId)
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

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getResult($resultId)
    {

        $sql =  $this->jdb->prepare("SELECT r.id, r.event_id as 'eventId', r.runner_id as 'runnerId', r.position, ra.date as 'date', r.result as 'time', r.result as 'result', r.info, r.event_division_id as 'eventDivisionId', r.standard_type_id as 'standardTypeId', r.category_id as 'categoryId', r.personal_best as 'isPersonalBest', r.season_best as 'isSeasonBest', r.grandprix as 'isGrandPrixResult',
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
			ORDER BY ra.date DESC, ra.id, r.position ASC, r.result ASC", self::START_OF_2015_AGE_GRADING, $resultId);

        $results = $this->jdb->get_row($sql, OBJECT);

        if ($results === FALSE) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function insertRunnerOfTheMonthWinners($runnerId, $category, $month, $year)
    {
        $sql = $this->jdb->prepare("insert into runner_of_the_month_winners set runner_id=%d, category='%s', month=%d, year=%d",
            $runnerId, $category, $month, $year);

        $result = $this->jdb->query($sql);

        if ($result) {
            return true;
        }

        return new \WP_Error(__METHOD__,
            'Unknown error in inserting runner in to the database', array('status' => 500));
    }

    public function insertRunnerOfTheMonthVote($vote)
    {
        $sql = $this->jdb->prepare("insert into runner_of_the_month_votes
										set
										runner_id=%d,
										reason='%s',
										category='%s',
										month=%d,
										year=%d,
										voter_id=%d,
										ip_address='%s',
										created='%s'",
            $vote['runnerId'], $vote['reason'], $vote['category'], $vote['month'], $vote['year'], $vote['voterId'], $vote['ipAddress'], $vote['created']);

        $result = $this->jdb->query($sql);

        if ($result) {
            return true;
        }

        return new \WP_Error(__METHOD__,
            'Unknown error in inserting runner in to the database', array('status' => 500));
    }

    public function getResultsByYearAndCounty()
    {
        $sql = "SELECT YEAR(ra.date) as year, ra.county, count(r.id) as count 
        FROM `race` ra 
        INNER join results r on ra.id = r.race_id 
        WHERE ra.county IS NOT NULL 
        GROUP BY YEAR(ra.date), ra.county 
        ORDER BY `year` ASC";

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getResultsByYearAndCountry()
    {
        $sql = "SELECT YEAR(ra.date) as year, ra.country_code, count(r.id) as count FROM `race` ra INNER join results r on ra.id = r.race_id WHERE ra.country_code IS NOT NULL GROUP BY YEAR(ra.date), ra.country_code ORDER BY `year` ASC";

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getResultsCountByYear()
    {
        $sql = "SELECT YEAR(ra.date) as year, count(r.id) as count FROM results r INNER JOIN race ra ON ra.id = r.race_id GROUP BY YEAR(ra.date) ORDER BY `year` DESC";

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getPersonalBestTotals()
    {
        $sql = "SELECT p.id as runnerId, p.name, count(r.id) as count, MIN(ra.date) AS firstPB, MAX(ra.date) AS lastPB FROM `results` r inner join runners p on r.runner_id = p.id INNER JOIN race ra ON ra.id = r.race_id where r.personal_best = 1 group by runnerId, p.name order by count DESC limit 50";

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getPersonalBestTotalByYear()
    {
        $sql = "SELECT count(*) AS count, YEAR(ra.date) as year from results r INNER JOIN race ra ON ra.id = r.race_id where r.personal_best = 1 GROUP by year order by year desc";

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getTopAttendedRaces()
    {
        $sql = "SELECT e.id as eventId, e.name, ra.date, count(r.id) as count
					FROM `results` r
					INNER JOIN race ra ON ra.id = r.race_id
					inner join events e on ra.event_id = e.id
					group by eventId, e.name, ra.date
					order by count desc limit 50";

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getTopMembersRacing()
    {
        $sql = "SELECT p.id as runnerId, p.name, count(r.id) as count FROM `results` r inner join runners p on r.runner_id = p.id group by runnerId, p.name order by count desc limit 50";

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__, GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getGroupedRunnerResultsCount($groupSize, $minimumResultCount)
    {
        $sql = "SELECT b.range,
        COALESCE(SUM(CASE WHEN gender = 2 THEN count END), 0) AS male,
        COALESCE(SUM(CASE WHEN gender = 3 THEN count END), 0) AS female
        FROM (
            SELECT
            concat($groupSize * floor(count / $groupSize) + 1, '-', $groupSize * floor(count / $groupSize) + $groupSize) AS `range`,
            a.gender,
            count(*) AS `count`
                FROM (
                SELECT r.runner_id AS runnerId, p.sex_id AS gender, count(r.id) AS count 
                FROM `results` r 
                INNER JOIN runners p ON p.id = r.runner_id
                WHERE p.sex_id <> 1
                GROUP BY runnerId, gender
                HAVING count > $minimumResultCount) a
                GROUP BY 1, 2) b
        GROUP BY 1
        ORDER BY CAST(b.range as SIGNED)";

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__, GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getResultCountByRunnerByYear($year, $limit)
    {
        if ($year > 0) {
            $sql = "SELECT p.id as runnerId, p.name, count(r.id) as count 
                    FROM results r
                    INNER JOIN race race ON race.id = r.race_id
                    inner join runners p on r.runner_id = p.id 
                    where YEAR(race.date) = $year
                    group by runnerId, p.name 
                    order by count desc limit $limit";
        } else {
            $sql = "SELECT p.id as runnerId, p.name, count(r.id) as count 
                    FROM results r
                    inner join runners p on r.runner_id = p.id 
                    group by runnerId, p.name 
                    order by count desc limit $limit";
        }

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return array();
        }

        if (!$results) {
            return new \WP_Error(__METHOD__, GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getClubResultsCount($year, $limit)
    {
        if ($year > 0) {
            $sql = "SELECT e.id as eventId, e.name, race.date, race.description, count(r.id) AS count
                    FROM results r
                    INNER JOIN race race ON race.id = r.race_id
                    INNER JOIN events e ON race.event_id = e.id
                    WHERE year(race.date) = $year
                    GROUP BY eventId, e.name, race.date, race.description
                    HAVING count > 0
                    ORDER BY race.date ASC
                    LIMIT $limit";
        } else {
            $sql = "SELECT 
                    YEAR(race.date) as year, 
                    MONTH(race.date) as month, 
                    DATE_FORMAT(race.date, '%%Y-%%m-01') as monthYear, 
                    count(r.id) AS count, 
                    COALESCE(sum(case when race.course_type_id IS NULL OR race.course_type_id = 0 then 1 end), 0) as unknown,
                    COALESCE(sum(case when race.course_type_id = 1 then 1 end), 0) as road,
                    COALESCE(sum(case when race.course_type_id = 2 then 1 end), 0) as 'multi-terrain',
                    COALESCE(sum(case when race.course_type_id = 3 then 1 end), 0) as track,
                    COALESCE(sum(case when race.course_type_id = 5 then 1 end), 0) as xc,
                    COALESCE(sum(case when race.course_type_id = 9 then 1 end), 0) as 'virtual',
                    COALESCE(sum(case when race.course_type_id = 4 OR race.course_type_id = 6 OR race.course_type_id = 7 OR race.course_type_id = 8 then 1 end), 0) as other
                    FROM results r
                    INNER JOIN race race ON race.id = r.race_id                    
                    GROUP BY year, month, monthYear
                    ORDER BY date ASC
                    LIMIT $limit";
        }

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return array();
        }

        if (!$results) {
            return new \WP_Error(__METHOD__, GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getTopMembersRacingByYear()
    {
        $sql = "select YEAR(ra.date) AS year, count(r.id) AS count, p.id as runnerId, p.name from results r inner join runners p on p.id = r.runner_id INNER JOIN race ra ON ra.id = r.race_id group by year, runnerId, p.name order by count DESC, year ASC LIMIT 10";

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    private function getResultStandardTypeId($catgeoryId, $result, $raceId, $percentageGrading2015, $resultDate)
    {
        if ($percentageGrading2015 > 0 && $resultDate >= self::START_OF_2015_AGE_GRADING) {
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

    private function getStarStandardTypeIdBefore2015($catgeoryId, $result, $raceId)
    {
        // Get standard type for results before 2015
        $sql = $this->jdb->prepare("SELECT
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
									LIMIT 1", $catgeoryId, $raceId, $result);

        $standard = $this->jdb->get_var($sql);

        if (empty($standard)) {
            $standard = 0;
        }

        return $standard;
    }

    private function updateResultStandardType($resultId)
    {

        $sql = $this->jdb->prepare("update results r, race race set standard_type_id =
			CASE
				WHEN percentage_grading_2015 >= 86 THEN 14
				WHEN percentage_grading_2015 >= 80 THEN 15
				WHEN percentage_grading_2015 >= 74 THEN 16
				WHEN percentage_grading_2015 >= 68 THEN 17
				WHEN percentage_grading_2015 >= 62 THEN 18
				WHEN percentage_grading_2015 >= 56 THEN 19
				WHEN percentage_grading_2015 >= 50 THEN 20
				ELSE 0
			END
			WHERE id = %d
			AND race.date >= '%s'
			AND race.id = r.race_id",
            $resultId, self::START_OF_2015_AGE_GRADING);

        $result = $this->jdb->query($sql);

        if (!$result) {
            return new \WP_Error(__METHOD__,
                'Unknown error in updating standard type results from the database', array('status' => 500));
        }

        return true;
    }

    private function getAgeGrading($result, $runnerId, $raceId)
    {

        $sql = $this->jdb->prepare("
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

        $result = $this->jdb->get_var($sql);

        if ($result === false) {
            return 0;
        }

        return $result;
    }

    private function get2015FactorsAgeGrading($result, $runnerId, $raceId)
    {

        $sql = $this->jdb->prepare("
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

        $result = $this->jdb->get_var($sql);

        if ($result === false) {
            return 0;
        }

        return $result;
    }

    public function getCategoryId($runnerId, $date)
    {
        $sql = $this->jdb->prepare("select c.id
					FROM
					runners p, category c
					WHERE p.id = %d
					AND p.sex_id = c.sex_id
					AND TIMESTAMPDIFF(YEAR, p.dob, '%s') >= c.age_greater_equal
					AND TIMESTAMPDIFF(YEAR, p.dob, '%s') < c.age_less_than
					LIMIT 1", $runnerId, $date, $date);

        $id = $this->jdb->get_var($sql);

        return $id;
    }

    private function isCertificatedCourseAndResult($raceId, $courseNumber = '', $result)
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
        $sql = $this->jdb->prepare("select
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
								LIMIT 1", $raceId, $result, $runnerId, $raceId, $date, 
                                \IpswichJAFFARunningClubAPI\Constants\CourseTypes::ROAD,
                                \IpswichJAFFARunningClubAPI\Constants\CourseTypes::TRACK,
                                \IpswichJAFFARunningClubAPI\Constants\CourseTypes::INDOOR,
                                \IpswichJAFFARunningClubAPI\Constants\CourseTypes::ROAD,
                                \IpswichJAFFARunningClubAPI\Constants\CourseTypes::TRACK,
                                \IpswichJAFFARunningClubAPI\Constants\CourseTypes::INDOOR);                                

        $count = $this->jdb->get_var($sql);

        return ($count == 0);
    }

    private function checkAndUpdatePersonalBest($resultId)
    {
        // If no later PBs, nothing to do
        // If a later PB (at distance) reset
        $sql = $this->jdb->prepare("SELECT
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

        $count = $this->jdb->get_var($sql);

        if ($count == 0) {
            return;
        }

        // Not the latest result. Reset PB status for later results.
        $sql = $this->jdb->prepare("
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
			pbRace.distance_id = laterRaces.distance_id"
            , $resultId);

        $this->jdb->query($sql);

        $sql = "SET @pbTime = '99:99:99'";
        $this->jdb->query($sql);

        $sql = $this->jdb->prepare("
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
			", $resultId,
            \IpswichJAFFARunningClubAPI\Constants\CourseTypes::ROAD,
            \IpswichJAFFARunningClubAPI\Constants\CourseTypes::TRACK,
            \IpswichJAFFARunningClubAPI\Constants\CourseTypes::INDOOR);

        $this->jdb->query($sql);
    }

    private function updatePercentageGradingPersonalBest($resultId, $runnerId, $date)
    {
        // IF the latest result check all results
        // ELSE reset all for valid result (e.g. grading > 0)
        $sql = $this->jdb->prepare("
			SELECT
			count(r.id)
			FROM results r
			INNER JOIN race race on race.id = r.race_id
			WHERE race.date >= '%s'
			AND r.runner_id = %d
			AND ((r.percentage_grading_2015 > 0 AND race.date >= '%') OR
				 (r.percentage_grading > 0 AND race.date < '%s'))",
                  $date, $runnerId, self::START_OF_2015_AGE_GRADING, self::START_OF_2015_AGE_GRADING);
        
        $count = $this->jdb->get_var($sql);

        if ($count == 0) {
            // No later results with a grading percentage.
            // Check for personal best and update
            $sql = $this->jdb->prepare("
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
				", $resultId, $runnerId, $resultId, $date, self::START_OF_2015_AGE_GRADING, $date, self::START_OF_2015_AGE_GRADING);

            $this->jdb->query($sql);

        } else {
            // Not the latest result. Reset grading.
            $sql =  $this->jdb->prepare("UPDATE results SET percentage_grading_best = 0 WHERE runner_id = %d;", $runnerId);
            $this->jdb->query($sql);

            $sql = "SET @pgpb = 0;";
            $this->jdb->query($sql);

            $sql = $this->jdb->prepare("
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
				", self::START_OF_2015_AGE_GRADING, $runnerId);

            $this->jdb->query($sql);
        }

        return true;
    }

    private function isSeasonBest($raceId, $runnerId, $result, $date)
    {
        $sql = $this->jdb->prepare("select
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
								LIMIT 1", $raceId, $result, $runnerId, $date, $date, $raceId,
                                \IpswichJAFFARunningClubAPI\Constants\CourseTypes::ROAD,
                                \IpswichJAFFARunningClubAPI\Constants\CourseTypes::TRACK,
                                \IpswichJAFFARunningClubAPI\Constants\CourseTypes::INDOOR,
                                \IpswichJAFFARunningClubAPI\Constants\CourseTypes::ROAD,
                                \IpswichJAFFARunningClubAPI\Constants\CourseTypes::TRACK,
                                \IpswichJAFFARunningClubAPI\Constants\CourseTypes::INDOOR);   

        $count = $this->jdb->get_var($sql);

        return ($count == 0);
    }

    private function isNewStandard($resultId)
    {
        // -- Match results of the same runner
        // -- Match results of the same distance
        // -- Find results with the same standard or better
        // -- Find results in the same age category
        // -- Only use the new standards - those 5+
        $sql = $this->jdb->prepare("SELECT  count(r2.id)
                                    FROM results r1, results r2, race ra1, race ra2, runners p, category c1, category c2
                                    WHERE r1.id = %d
                                    AND r1.id != r2.id
                                    AND r1.runner_id = r2.runner_id
                                    AND r1.race_id = ra1.id
                                    AND r2.race_id = ra2.id
                                    AND ra1.distance_id = ra2.distance_id
                                    AND r2.standard_type_id < r1.standard_type_id
                                    AND r2.standard_type_id > 4
                                    AND r2.runner_id = p.id
                                    AND p.sex_id = c1.sex_id
                                    AND (year(from_days(to_days(ra1.date)-to_days(p.dob) + 1)) >= c1.age_greater_equal
                                            AND  year(from_days(to_days(ra1.date)-to_days(p.dob) + 1)) <  c1.age_less_than)
                                    AND p.sex_id = c2.sex_id
                                    AND (year(from_days(to_days(ra2.date)-to_days(p.dob) + 1)) >= c2.age_greater_equal
                                            AND  year(from_days(to_days(ra2.date)-to_days(p.dob) + 1)) <  c2.age_less_than)
                                    AND c1.id = c2.id",
            $resultId);

        $count = $this->jdb->get_var($sql);

        return ($count == 0);
    }

    private function saveStandardCertificate($resultId)
    {
        $sql = $this->jdb->prepare("insert into standard_certificates set result_id=%d, issued = 0", $resultId);

        $result = $this->jdb->query($sql);

        if (!$result) {
            return new \WP_Error(__METHOD__,
                'Unknown error in inserting standard certificate in to the database', array('status' => 500));
        }

        return true;
    }

    public function getClubRecords($distanceId)
    {
        $sql = $this->jdb->prepare("
				SELECT d.distance, r.runner_id as runnerId, p.Name as runnerName, e.id as eventId, e.Name as eventName, ra.date, r.result, c.code as categoryCode, ra.id as raceId, ra.description, ra.venue
				FROM results AS r
        INNER JOIN race ra ON r.race_id = ra.id
				JOIN (
				  SELECT r1.runner_id, r1.result, MIN(ra1.date) AS earliest
				  FROM results AS r1
                  INNER JOIN race ra1 on r1.race_id = ra1.id
				  JOIN (
					SELECT 
                    CASE
                        WHEN d.result_measurement_unit_type_id = 3 OR  d.result_measurement_unit_type_id = 4  OR  d.result_measurement_unit_type_id = 5 THEN MAX(r2.result)
                        ELSE MIN(r2.result)
                    END as quickest,
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
					WHERE r2.result != '00:00:00' and r2.result != '' and d.id = %d and r2.category_id <> 0
          AND (ra.course_type_id NOT IN (2, 4, 5, 7, 9) OR ra.course_type_id IS NULL)
					GROUP BY r2.category_id
				   ) AS rt
				   ON r1.result = rt.quickest and r1.category_id = rt.category_id
				   GROUP BY r1.runner_id, r1.result, r1.category_id
				   ORDER BY r1.result asc
				) as rd
				ON r.runner_id = rd.runner_id AND r.result = rd.result AND ra.date = rd.earliest
				INNER JOIN events e ON ra.event_id = e.id
				INNER JOIN runners p ON r.runner_id = p.id
				INNER JOIN category c ON r.category_id = c.id
                INNER JOIN distance d ON ra.distance_id = d.id
				WHERE c.age_less_than is NOT NULL and ra.distance_id = %d
				ORDER BY c.age_less_than, c.sex_id", $distanceId, $distanceId);

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getOverallClubRecords()
    {
        $sql = $this->jdb->prepare("
				SELECT d.distance, r.runner_id as runnerId, p.Name as runnerName, s.sex, e.id as eventId, e.Name as eventName, ra.date, r.result, ra.id as raceId, ra.description, ra.venue
				FROM results AS r
                INNER JOIN race ra ON r.race_id = ra.id                
				JOIN (
				  SELECT r1.runner_id, r1.result, MIN(ra1.date) AS earliest
				  FROM results AS r1
                  INNER JOIN race ra1 on r1.race_id = ra1.id
                  INNER JOIN runners p1 ON r1.runner_id = p1.id
				  JOIN (
					SELECT 
                    CASE
                        WHEN d.result_measurement_unit_type_id = 3 OR d.result_measurement_unit_type_id = 4 OR d.result_measurement_unit_type_id = 5 THEN MAX(r2.result)
                        ELSE MIN(r2.result)
                    END as quickest,
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
					WHERE r2.result != '00:00:00' and r2.result != '' and d.id IN (1,2,3,4,5,6,7,8) and r2.category_id <> 0
          AND (ra.course_type_id NOT IN (2, 4, 5, 7, 9) OR ra.course_type_id IS NULL)
					GROUP BY p2.sex_id, ra.distance_id
				   ) AS rt
				   ON r1.result = rt.quickest and p1.sex_id = rt.sex_id AND rt.distance_id = ra1.distance_id
				   GROUP BY r1.runner_id, r1.result, p1.sex_id, ra1.distance_id
				   ORDER BY r1.result asc
				) as rd
				ON r.runner_id = rd.runner_id AND r.result = rd.result AND ra.date = rd.earliest
				INNER JOIN events e ON ra.event_id = e.id
				INNER JOIN runners p ON r.runner_id = p.id
                INNER JOIN sex s ON p.sex_id = s.id
                INNER JOIN distance d ON ra.distance_id = d.id
				WHERE ra.distance_id IN (1,2,3,4,5,6,7,8)
				ORDER BY ra.distance_id, p.sex_id");

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getCountyChampions()
    {
        $sql = "SELECT r.runner_id as runnerId, p.Name as runnerName, e.id as eventId, e.Name as eventName, ra.date, r.result, c.code as categoryCode, ra.id as raceId, ra.description, d.id as distanceId, d.distance
				FROM results AS r
                INNER JOIN race ra ON r.race_id = ra.id
                LEFT JOIN distance d ON ra.distance_id = d.id
				INNER JOIN events e ON ra.event_id = e.id
				INNER JOIN runners p ON r.runner_id = p.id
				INNER JOIN category c ON r.category_id = c.id
				WHERE r.county_champion = 1
				ORDER BY ra.date desc, categoryCode asc";

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getResultRankings($distanceId, $year = 0, $sexId = 0, $categoryId = 0)
    {

        // Get the results
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

        $this->jdb->query($sql);

        $sql = "
				SELECT @cnt := @cnt + 1 AS rank, Ranking.* FROM (
					SELECT r.runner_id as runnerId, p.Name as name, ra3.id as raceId, e.Name as event, ra3.date, r.result
					FROM results AS r
					JOIN (
					  SELECT r1.runner_id, r1.result, MIN(ra1.date) AS earliest
					  FROM results AS r1
					  INNER JOIN race ra1 ON r1.race_id = ra1.id
					  JOIN (
						SELECT r2.runner_id, MIN(r2.result) AS quickest
						FROM results r2
						INNER JOIN `race` ra2
						ON ra2.id = r2.race_id
						INNER JOIN `runners` p2
						ON r2.runner_id = p2.id
						WHERE r2.result != '00:00:00'
						AND r2.result != ''
						AND ra2.distance_id = $distanceId
                        AND (ra2.course_type_id NOT IN (2, 4, 5, 7) OR ra2.course_type_id IS NULL)
						$sexQuery
						$dateQuery2
                        $categoryQuery
						GROUP BY r2.runner_id
					   ) AS rt
					   ON r1.runner_id = rt.runner_id AND r1.result = rt.quickest
					   $dateQuery1
					   GROUP BY r1.runner_id, r1.result
					   ORDER BY r1.result asc
					   LIMIT 100
					) as rd
					ON r.runner_id = rd.runner_id AND r.result = rd.result
					INNER JOIN race ra3 ON r.race_id = ra3.id AND ra3.date = rd.earliest
					INNER JOIN runners p ON r.runner_id = p.id
					INNER JOIN events e ON ra3.event_id = e.id
					ORDER BY r.result asc
					LIMIT 100) Ranking";

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return array();
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getMemberResults($runnerId)
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
					ORDER BY date DESC", self::START_OF_2015_AGE_GRADING, $runnerId);

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    } // end function GetMemberResults

    public function getMemberPBResults($runnerId)
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
					ORDER BY r.result ASC", self::START_OF_2015_AGE_GRADING, $runnerId, $runnerId);

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
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

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500, 'sql' => $sql));
        }

        return $results;

    }

    public function getStandardCertificates($runnerId)
    {
        $sql = $this->jdb->prepare("SELECT st.name, e.name as 'event', d.distance, r.result, DATE_FORMAT( ra.date, '%%M %%e, %%Y' ) as 'date'
								  FROM standard_certificates sc
								  INNER JOIN results r ON sc.result_id = r.id
								  INNER JOIN standard_type st ON r.standard_type_id = st.id
								  INNER JOIN race ra ON ra.id = r.race_id
								  INNER JOIN events e ON e.id = ra.event_id
								  INNER JOIN distance d ON d.id = ra.distance_id
								  where r.runner_id = %d and ra.date > '2010-01-01'
								  order by st.name desc", $runnerId);

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getRunnerRankings($runnerId, $sexId, $distances, $year = '')
    {
        $results = array();

        foreach ($distances as $distanceId) {
            $sql = "SET @cnt := 0;";

            $this->jdb->query($sql);

            if (empty($year)) {
                $sql = $this->jdb->prepare(
                    "SELECT * FROM (
						SELECT @cnt := @cnt + 1 AS rank, Ranking.* FROM (
							SELECT r.id as resultId, e.Name as event, r.position, r.result, r.info, ra.date, c.code, p.Name as 'name', r.runner_id, ra.distance_id as distanceId
							FROM results AS r
							JOIN (
								SELECT r1.runner_id, r1.result, MIN(race.date) AS earliest
								FROM results AS r1
								JOIN (
									SELECT r2.runner_id, MIN(r2.result) AS quickest
									FROM results r2
                  					INNER JOIN race ra2
									ON r2.race_id = ra2.id
									INNER JOIN events e
									ON ra2.event_id = e.id
									INNER JOIN `distance` d
									ON ra2.distance_id = d.id
									INNER JOIN `runners` p2
									ON r2.runner_id = p2.id
									WHERE r2.result != '00:00:00'
                  					AND r2.result != ''
									AND d.id = %d
									AND p2.sex_id = %d
									GROUP BY r2.runner_id
								   ) AS rt
								ON r1.runner_id = rt.runner_id AND r1.result = rt.quickest
								INNER JOIN race race ON r1.race_id = race.id
								GROUP BY r1.runner_id, r1.result
								ORDER BY r1.result asc
								LIMIT 100
							) as rd
							ON r.runner_id = rd.runner_id AND r.result = rd.result 
							INNER JOIN race ra ON ra.id = r.race_id AND ra.date = rd.earliest
              				INNER JOIN events e ON ra.event_id = e.id
							INNER JOIN runners p ON r.runner_id = p.id
							INNER JOIN category c ON r.category_id = c.id
							ORDER BY r.result asc
							LIMIT 100) Ranking
						) Results
					HAVING Results.runner_id = %d", $distanceId, $sexId, $runnerId);
            } else {
                $sql = $this->jdb->prepare(
                    "SELECT * FROM (
						SELECT @cnt := @cnt + 1 AS rank, Ranking.* FROM (
							SELECT r.id as resultId, e.Name as event, r.position, r.result, r.info, ra.date, c.code, p.Name as name, r.runner_id, ra.distance_id as distanceId
							FROM results AS r
							JOIN (
								SELECT r1.runner_id, r1.result, MIN(race.date) AS earliest
								FROM results AS r1
								JOIN (
									SELECT r2.runner_id, MIN(r2.result) AS quickest
									FROM results r2
                  					INNER JOIN race ra2
									ON r2.race_id = ra2.id
									INNER JOIN events e
									ON ra2.event_id = e.id
									INNER JOIN `distance` d
									ON ra2.distance_id = d.id
									INNER JOIN `runners` p2
									ON r2.runner_id = p2.id
									WHERE r2.result != '00:00:00'
                  					AND r2.result != ''
									AND d.id = %d
									AND p2.sex_id = %d
									AND year(ra2.date) = %d
									GROUP BY r2.runner_id
								   ) AS rt
								ON r1.runner_id = rt.runner_id AND r1.result = rt.quickest
								INNER JOIN race race ON r1.race_id = race.id 
								GROUP BY r1.runner_id, r1.result
								ORDER BY r1.result asc
								LIMIT 100
							) as rd
							ON r.runner_id = rd.runner_id AND r.result = rd.result 
              				INNER JOIN race ra ON ra.id = r.race_id AND ra.date = rd.earliest
							INNER JOIN events e ON ra.event_id = e.id
							INNER JOIN runners p ON r.runner_id = p.id
							INNER JOIN category c ON r.category_id = c.id
							WHERE year(ra.date) = %d
							ORDER BY r.result asc
							LIMIT 100) Ranking
						) Results
					HAVING Results.runner_id = %d", $distanceId, $sexId, $year, $year, $runnerId);
            }

            $ranking = $this->jdb->get_row($sql, OBJECT);

            if ($ranking) {
                $results[] = $ranking;
            }
        }

        return $results;
    }

    public function getWMAPercentageRankings($sexId = 0, $distanceId = 0, $year = 0, $distinct = false)
    {

        // Get the results
        if ($distanceId != 0) {
            $distanceQuery1 = " AND ra1.distance_id = $distanceId";
            $distanceQuery2 = " AND ra2.distance_id = $distanceId";
        } else {
            $distanceQuery2 = "";
            $distanceQuery1 = "";
        }

        if ($sexId != 0) {
            $sexQuery0 = " AND p.sex_id = $sexId";
            $sexQuery1 = " AND p2.sex_id = $sexId";
        } else {
            $sexQuery0 = "";
            $sexQuery1 = "";
        }

        if ($year != 0) {
            $yearQuery1 = " AND YEAR(ra1.date) >= $year AND YEAR(ra1.date) < ($year +1)";
            $yearQuery2 = " AND YEAR(ra2.date) >= $year AND YEAR(ra2.date) < ($year +1)";
        } else {
            $yearQuery1 = "";
            $yearQuery2 = "";
        }

        $sql = "SET @cnt := 0;";

        $this->jdb->query($sql);

        if ($distinct == false || $distinct == "false") {
            $sql = "
					select @cnt := @cnt + 1 as rank, ranking.* from (
						select r.runner_id as runnerId,
						p.name,
						e.id as eventId,
						e.name as event,
						ra2.id as raceId,
						ra2.date,
						r.result,
						CASE
							WHEN ra2.date >= '".self::START_OF_2015_AGE_GRADING."' OR $year = 0 THEN r.percentage_grading_2015
							ELSE r.percentage_grading
						END as percentageGrading
						from results as r
						inner join runners p on p.id = r.runner_id
						inner join race ra2 on ra2.id = r.race_id
						inner join events e on e.id = ra2.event_id
						where ((r.percentage_grading_2015 > 0 AND (ra2.date > '".self::START_OF_2015_AGE_GRADING."' OR $year = 0)) OR r.percentage_grading > 0)
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
						r.percentage_grading_2015 as percentageGrading
						FROM results AS r
						JOIN (
						  SELECT r1.runner_id, r1.result, MIN(ra1.date) AS earliest
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
						   GROUP BY r1.runner_id, r1.result
						   ORDER BY r1.percentage_grading_2015 desc
						   LIMIT 100
						) as rd
						ON r.runner_id = rd.runner_id AND r.result = rd.result
						INNER JOIN race ra ON r.race_id = ra.id AND ra.date = rd.earliest
						INNER JOIN events e ON ra.event_id = e.id
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
						r.percentage_grading as percentageGrading
						FROM results AS r
						JOIN (
						  SELECT r1.runner_id, r1.result, MIN(ra1.date) AS earliest
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
						   GROUP BY r1.runner_id, r1.result
						   ORDER BY r1.percentage_grading desc
						   LIMIT 100
						) as rd
						ON r.runner_id = rd.runner_id AND r.result = rd.result
						INNER JOIN race ra ON r.race_id = ra.id AND ra.date = rd.earliest
						INNER JOIN events e ON ra.event_id = e.id
						INNER JOIN runners p ON r.runner_id = p.id
						ORDER BY percentageGrading desc
						LIMIT 100) Ranking";
            }
        }

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }
    public function getAveragePercentageRankings($sexId, $year = 0, $numberOfRaces = 5, $numberOfResults = 200)
    {

        $sql = "set @cnt := 0, @runnerId := 0, @rank := 0;";

        $this->jdb->query($sql);
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

										select r.runner_id, p.name, e.id, e.name as event, ra.date, r.result, r.percentage_grading_2015
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

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getGrandPrixPoints($year, $sexId)
    {
        $nextYear = $year + 1;

        $sql = "SELECT
                  p.id as runnerId,
                  p.name,
				  p.dob as dateOfBirth,
                  ra.id as raceId,
				  e.id as eventId,
				  e.name as eventName,
				  ra.description,
				  ra.distance_id as distanceId,
				  r.position as position,
				  r.result as result
                FROM
                  results r,
                  race ra,
                  runners p,
				  events e
                WHERE
                  ra.date >= '$year-04-01' and ra.date < '$nextYear-04-01'
                  AND r.runner_id = p.id
                  AND $sexId = p.sex_id
                  AND ra.id = r.race_id
                  AND ra.grand_prix = 1
				  AND e.id = ra.event_id
                  AND year(FROM_DAYS(TO_DAYS(ra.date) - TO_DAYS(p.dob))) >= 16
                ORDER BY ra.date, ra.id, r.position asc, r.result asc";

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500));
        }

        return $results;
    }

    public function getMeetings($eventId)
    {

        $sql = $this->jdb->prepare(
            'SELECT m.id as id, m.name as name, m.from_date as fromDate, m.to_date as toDate
					FROM `meeting` m
					WHERE m.event_id = %d
					ORDER BY m.from_date DESC', $eventId);

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                'Unknown error in reading meeting from the database', array('status' => 500));
        }

        return $results;
    }

    public function getMeeting($meetingId)
    {

        $sql = $this->jdb->prepare(
            'SELECT m.id as id, m.name as name, m.from_date as fromDate, m.to_date as toDate, r.id as raceId, r.description as description
					FROM `meeting` m
					LEFT JOIN `race` r on r.meeting_id = m.id
					WHERE m.id = %d', $meetingId);

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                'Unknown error in reading meeting from the database', array('status' => 500));
        }

        return $results;
    }

    public function getMeetingV2($meetingId)
    {

        $sql = $this->jdb->prepare(
            'SELECT m.id as id, m.name as name, m.from_date as fromDate, m.to_date as toDate, m.report as report
					FROM `meeting` m
					WHERE m.id = %d', $meetingId);

        $meeting = $this->jdb->get_row($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$meeting) {
            return new \WP_Error(__METHOD__,
                'Unknown error in getting meeting from the database', array('status' => 500));
        }

        return $meeting;
    }

    public function getMeetingTeams($meetingId)
    {

        $sql = $this->jdb->prepare(
            'SELECT tr.id as teamId, tr.county_championship as countyChampionshipResult, tr.team_name as teamName, tr.category as teamCategory, tr.position as teamPosition, tr.result as teamResult
					FROM `team_results` tr
					WHERE tr.meeting_id = %d', $meetingId);

        $teams = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$teams) {
            return new \WP_Error(__METHOD__,
                'Unknown error in getting meeting teams from the database', array('status' => 500));
        }

        return $teams;
    }

    public function getMeetingResults($meetingId)
    {

        $sql = $this->jdb->prepare(
            'SELECT tr.id as teamId, p.name as runnerName, p.id as runnerId, r.result as runnerResult,
				r.position as runnerPosition, trr.order as teamOrder
				FROM `team_results` tr
				INNER JOIN `team_results_runners` trr ON tr.id = trr.team_result_id
				INNER JOIN `results` r on trr.result_id = r.id
				INNER JOIN `runners` p ON r.runner_id = p.id
				WHERE meeting_id = %d
				ORDER BY tr.position, trr.order', $meetingId);

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                'Unknown error in getting meeting results from the database', array('status' => 500));
        }

        return $results;
    }

    public function insertMeeting($meeting, $eventId)
    {
        $sql = $this->jdb->prepare('insert into `meeting`(`event_id`, `from_date`, `to_date`, `name`) values(%d, %s, %s, %s)', $eventId, $meeting['fromDate'], $meeting['toDate'], $meeting['name']);

        $result = $this->jdb->query($sql);

        if ($result) {
            return $this->getMeeting($this->jdb->insert_id);
        }

        return new \WP_Error(__METHOD__,
            'Unknown error in inserting meeting in to the database', array('status' => 500));
    }

    public function updateMeeting($meetingId, $field, $value)
    {

        // Only name and website may be changed.
        if ($field == 'name' || $field == 'from_date' || $field == 'to_date') {
            $result = $this->jdb->update(
                'meeting',
                array(
                    $field => $value,
                ),
                array('id' => $meetingId),
                array(
                    '%s',
                ),
                array('%d')
            );

            if ($result) {
                return $this->getMeeting($meetingId);
            }

            return new \WP_Error(__METHOD__,
                'Unknown error in updating meeting in to the database' . $sql, array('status' => 500));
        }

        return new \WP_Error(__METHOD__,
            'Field in meeting may not be updated', array('status' => 500));
    }

    public function deleteMeeting($meetingId)
    {
        $sql = $this->jdb->prepare('DELETE FROM meeting WHERE id = %d;', $meetingId);

        $result = $this->jdb->query($sql);

        if (!$result) {
            return new \WP_Error(__METHOD__,
                'Unknown error in deleting meeting from the database', array('status' => 500));
        }

        return true;
    }

    public function getMeetingRaces($meetingId)
    {
        $sql = $this->jdb->prepare(
            'SELECT ra.id, ra.date, ra.description, ra.course_type_id as courseTypeId, ra.report as report
					FROM `race` ra
					WHERE ra.meeting_id = %d
					ORDER BY ra.date, ra.description', $meetingId);

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                'Unknown error in getting meeting races from the database', array('status' => 500));
        }

        return $results;
    }

    public function getAllRaceResults($distanceId)
    {
        $sql = $this->jdb->prepare(
            "SELECT p.name, 
            p.id, 
            ra.date, 
            ra.id as 'raceId',
            ra.description as 'raceDescription',
            e.name as 'eventName',
            c.id as 'categoryId',
            c.code as 'categoryCode',
            r.result,
            r.position,
            ra.course_type_id as 'courseTypeId',
            d.result_measurement_unit_type_id as resultMeasurementUnitTypeId
            FROM `results` r
            inner join race ra on ra.id = r.race_id
            INNER JOIN runners p ON p.id = r.runner_id
            INNER JOIN events e ON e.id = ra.event_id
            INNER JOIN category c ON c.id = r.category_id
            INNER JOIN distance d ON d.id = ra.distance_id
            WHERE ra.distance_id = %d AND c.id > 0 AND r.result <> '00:00:00' AND r.result <> ''
            order by category_id asc, ra.date asc, r.result asc", $distanceId);

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                'Unknown error in getting all race results from the database', array('status' => 500));
        }

        return $results;
    }

    public function getAllRaceResultsByCategory($categoryId)
    {
        $sql = $this->jdb->prepare(
            "SELECT p.name, 
            p.id, 
            ra.date, 
            ra.id as 'raceId',
            ra.description as 'raceDescription',
            e.name as 'eventName',
            c.id as 'categoryId',
            c.code as 'categoryCode',
            r.result,
            r.position,
            ra.course_type_id as 'courseTypeId',
            d.distance,
            d.id as 'distanceId',
            d.result_measurement_unit_type_id as resultMeasurementUnitTypeId
            FROM `results` r
            INNER join race ra on ra.id = r.race_id
            INNER JOIN runners p ON p.id = r.runner_id
            INNER JOIN events e ON e.id = ra.event_id
            INNER JOIN category c ON c.id = r.category_id
            INNER JOIN distance d ON d.id = ra.distance_id
            WHERE r.category_id = %d AND r.result <> '00:00:00' AND r.result <> ''
            order by d.id asc, ra.date asc, r.result asc", $categoryId);

        $results = $this->jdb->get_results($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$results) {
            return new \WP_Error(__METHOD__,
                'Unknown error in getting all race results from the database', array('status' => 500));
        }

        return $results;
    }

    public function getRaceDetails($raceIds)
    {

        $raceIdString = join(",", $raceIds);

        $sql = "SELECT ra.id, e.id AS eventId, e.Name as eventName, ra.description as description, ra.date, ra.course_type_id AS courseTypeId, c.description AS courseType, ra.area, ra.county, ra.country_code AS countryCode, ra.conditions, ra.venue, d.distance, ra.grand_prix as isGrandPrixRace, ra.course_number as courseNumber, ra.meeting_id as meetingId, d.result_measurement_unit_type_id as resultMeasurementUnitTypeId
			FROM `events` e
			INNER JOIN `race` ra ON ra.event_id = e.id
			LEFT JOIN `distance` d ON ra.distance_id = d.id
			LEFT JOIN `course_type` c ON ra.course_type_id = c.id
			WHERE ra.id in ($raceIdString)
			ORDER BY ra.date";

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                'Unknown error in reading race from the database', array('status' => 500));
        }

        return $results;
    }

    public function getRunnerOfTheMonthWinnners($year = 0, $month = 0)
    {
        if ($year > 0 || $month > 0) {
            $sql = "SELECT romw.category, romw.month, romw.year, r.name, r.id
				from runners r, runner_of_the_month_winners romw
				where r.id = romw.runner_id
				AND romw.year = $year
				AND romw.month = $month
				order by romw.year desc , romw.month desc";
        } else {
            $sql = "SELECT romw.category, romw.month, romw.year, r.name, r.id
				from runners r, runner_of_the_month_winners romw
				where r.id = romw.runner_id
				order by romw.year desc, romw.month desc";
        }

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                GENERIC_ERROR_MESSAGE, array('status' => 500, 'sql' => $sql));
        }

        return $results;
    }

    public function mergeEvents($fromEventId, $toEventId)
    {
        $sql = $this->jdb->prepare("update results set event_id = %d WHERE event_id = %d",
            $toEventId, $fromEventId);

        $result = $this->jdb->query($sql);

        if ($result === false) {
            return new \WP_Error(__METHOD__,
                'Unknown error in merging events from the database', array('status' => 500));
        }

        $sql = $this->jdb->prepare("update race set event_id = %d WHERE event_id = %d",
            $toEventId, $fromEventId);

        $result = $this->jdb->query($sql);

        if ($result === false) {
            return new \WP_Error(__METHOD__,
                'Unknown error in merging events from the database', array('status' => 500));
        }

        $this->deleteEvent($fromEventId, false);

        return true;
    }

    public function getLeagues()
    {

        $sql = 'SELECT l.id, l.name, l.starting_year as startingYear, l.course_type_id as courseTypeId, count( ra.id ) AS numberOfRaces, l.final_position as finalPosition
			FROM `leagues` l
			LEFT JOIN `race` ra on  ra.league_id = l.id
			GROUP BY l.id, l.name, l.starting_year
			ORDER BY startingYear DESC, name ASC';

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                'Unknown error in reading leagues from the database', array('status' => 500));
        }

        return $results;
    }

    public function getLeague($id)
    {

        $sql = $this->jdb->prepare("SELECT l.id as id, l.name as name, l.starting_year as startingYear, l.course_type_id as courseTypeId,
		l.final_position as leagueFinalPosition, e.id as eventId, e.name as eventName, ra.id as raceId, ra.description as raceName, ra.date as raceDate, ra.venue as raceVenue,
		ra.meeting_id as meetingId,
		count( r.id ) AS numberOfResults
			FROM `leagues` l
			LEFT JOIN `race` ra on  ra.league_id = l.id
			LEFT JOIN `events` e on ra.event_id = e.id
			LEFT JOIN `results` r on r.race_id = ra.id
			WHERE l.id = %d
			GROUP BY l.id, l.name, l.starting_year, e.id, e.name, ra.id, ra.description, ra.date, ra.venue
			ORDER BY ra.date, ra.description ASC", $id);

        $results = $this->jdb->get_results($sql, OBJECT);

        if (!$results) {
            return new \WP_Error(__METHOD__,
                'Unknown error in reading league from the database', array('status' => 500, 'id' => $id));
        }

        return $results;
    }

    public function insertLeague($league)
    {
        $sql = $this->jdb->prepare('INSERT INTO leagues (`name`, `starting_year`, `course_type_id`) VALUES(%s, %s, %d);',
            $league['name'], $league['startingYear'], $league['courseTypeId']);

        $result = $this->jdb->query($sql);

        if ($result) {
            return $this->getLeague($this->jdb->insert_id);
        }

        return new \WP_Error(__METHOD__,
            'Unknown error in inserting league in to the database', array('status' => 500));
    }

    public function updateLeague($leagueId, $field, $value)
    {

        // Only name and website may be changed.
        if ($field == 'name' || $field == 'starting_year') {
            $result = $this->jdb->update(
                'leagues',
                array(
                    $field => $value,
                ),
                array('id' => $leagueId),
                array(
                    '%s',
                ),
                array('%d')
            );

            if ($result) {
                // Get updated event
                return $this->getLeague($leagueId);
            }

            return new \WP_Error(__METHOD__,
                'Unknown error in updating league in to the database' . $sql, array('status' => 500));
        }

        return new \WP_Error(__METHOD__,
            'Field in league may not be updated', array('status' => 500));
    }

    public function deleteLeague($leagueId, $deleteRaceAssociations)
    {
        $sql = $this->jdb->prepare('SELECT COUNT(r.id) FROM race r WHERE r.league_id = %d LIMIT 1;', $leagueId);

        $exists = $this->jdb->get_var($sql); // $jdb->get_var returns a single value from the database. In this case 1 if the find term exists and 0 if it does not.

        if ($exists != 0) {
            if (empty($deleteRaceAssociations)) {
                return new \WP_Error(__METHOD__,
                    'League cannot be deleted; a number of races are associated with this league. Delete the existing races for this league and try again.', array('status' => 403));
            }

            // Delete all associated results
            $sql = $this->jdb->prepare('UPDATE race r SET r.league_id = NULL WHERE r.league_id = %d;', $leagueId);

            $result = $this->jdb->query($sql);

            if (!$result) {
                return new \WP_Error(__METHOD__,
                    'Unknown error in deleting league races from the database', array('status' => 500));
            }
        }

        $sql = $this->jdb->prepare('DELETE FROM leagues WHERE id = %d;', $leagueId);

        $result = $this->jdb->query($sql);

        if (!$result) {
            return new \WP_Error(__METHOD__,
                'Unknown error in deleting league from the database', array('status' => 500));
        }

        return true;
    }

    public function deleteTeamResult($teamResultId)
    {

        $sql = $this->jdb->prepare('
			DELETE FROM team_results WHERE id = %d;
			DELETE FROM team_results_runners WHERE team_result_id = %d;',
            $teamResultId, $teamResultId);

        $result = $this->jdb->query($sql);

        if (!$result) {
            return new \WP_Error(__METHOD__,
                'Unknown error in deleting team result from the database', array('status' => 500));
        }

        return true;
    }

    public function getTeamResult($teamResultId)
    {

        $sql = $this->jdb->prepare(
            'SELECT tr.id as teamId, p.name as runnerName, p.id as runnerId, r.result as runnerResult,
			r.position as runnerPosition, trr.order as teamOrder
			FROM `team_results` tr
			INNER JOIN `team_results_runners` trr ON tr.id = trr.team_result_id
			INNER JOIN `results` r on trr.result_id = r.id
			INNER JOIN `runners` p ON r.runner_id = p.id
			WHERE tr.id = %d
			ORDER BY tr.position, trr.order', $teamResultId);

        $result = $this->jdb->get_row($sql, OBJECT);

        if ($this->jdb->num_rows == 0) {
            return null;
        }

        if (!$result) {
            return new \WP_Error(__METHOD__,
                'Unknown error in getting team result from the database', array('status' => 500));
        }

        return $result;
    }

    public function insertTeamResult($teamResult)
    {
        $sql = $this->jdb->prepare('INSERT INTO team_results (`team_name`, `category`, `result`, `position`, `meeting_id`) VALUES(%s, %s, %s, %d, %d);',
            $teamResult['name'], $teamResult['category'], $teamResult['result'], $teamResult['position'], $teamResult['meetingId']);

        $result = $this->jdb->query($sql);

        if ($result) {
            $teamResultId = $this->jdb->insert_id;

            $values = array();
            $order = 1;
            foreach ($teamResult['resultIds'] as $resultId) {
                $values[] = "($teamResultId, $resultId, $order)";
                $order++;
            }

            $sql =
            "INSERT INTO team_results_runners (`team_result_id`, `result_id`, `order`)
				 VALUES " . implode(",", $values);

            $result = $this->jdb->query($sql);

            if ($result) {
                return $this->getTeamResult($teamResultId);
            }
        }

        return new \WP_Error(__METHOD__,
            'Unknown error in inserting team result in to the database', array('status' => 500, 'sql' => $sql));
    }

    public function correctStandardTypesForResultsAfter2015()
    {
        // Delete all current standard certifcates for results 2017 onwards
        // DELETE sc FROM `standard_certificates` sc INNER JOIN results r ON r.id = sc.result_id INNER JOIN race race ON race.id = r.race_id WHERE race.date >= '2017-01-01'

        // Get results where
        // 1. Result date 2017 or afterwards
        // 2. 2015 age grading is positive
        // 3. Order by date ascending
        $sql = "SELECT r.id as id FROM `standard_certificates` sc INNER JOIN results r ON r.id = sc.result_id INNER JOIN race race ON race.id = r.race_id WHERE race.date >= '2017-01-01' AND r.percentage_grading_2015 > 0 ORDER BY race.date ASC";
        $newStandardResultIds = $this->jdb->get_results($sql, OBJECT);

          // Find if new certifcate
        $resultIds = "";
        for ($i = 0; $i < count($newStandardResultIds); $i++) {
            $isNewStandard = $this->isNewStandard($newStandardResultIds[$i]->id);
            if ($isNewStandard) {
                $resultIds .= "(".$newStandardResultIds[$i]->id."),";
            }
        }        

        // Insert new standard certifcates
        //$resultIds = trim($resultIds, ",");
        //$sql = $this->jdb->prepare("insert into standard_certificates (result_id) VALUES %s", $resultIds);

        //$result = $this->jdb->query($sql);

        return $resultIds;
    }
}
?>
<?php

namespace IpswichJAFFARunningClubAPI\V2\Races;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';

use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class RacesDataAccess extends DataAccess
{
    public function getRaces(int $eventId, ?string $date)
    {
        $sql = $this->resultsDatabase->prepare(
            "SELECT ra.id, 
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
            WHERE e.id = %d AND (%d = 1 OR '%s' = ra.date)
            GROUP BY ra.id, eventId, name, ra.date, ra.description, courseTypeId, courseType, ra.area, ra.county, countryCode, ra.conditions, ra.venue, d.distance, isGrandPrixRace
            ORDER BY ra.date DESC, ra.description",
            $eventId, is_null($date) ? 1 : 0, $date
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

    public function updateRace(int $raceId, string $field, ?string $value)
    {
        return $this->updateEntity(__METHOD__, 'race', $field, $value ?? '', $raceId, function ($id) {
			return $this->getRace($id);
		});
    }

    public function getLatestRacesDetails(int $count)
    {
        $sql = $this->resultsDatabase->prepare(
            'SELECT
            e.id AS eventId,
            e.Name as name,
            race.date,
            MIN(race.id) as lastRaceId,
            count(DISTINCT(race.id)) as countOfRaces,
            count(CASE
              WHEN r.personal_best = 1 THEN 1
            END) as countOfPersonalBests,
            count(CASE
              WHEN r.season_best = 1 THEN 1
            END) as countOfSeasonalBests,
            count(r.id) as countOfResults
            FROM `events` e
            INNER JOIN `race` race ON race.event_id = e.id
            INNER JOIN `results` r ON race.id = r.race_id
            GROUP BY eventId, name, race.date
            ORDER BY race.date DESC
            LIMIT %d',
            $count
        );

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getHistoricRaces()
    {
                $sql = "SELECT
  DISTINCT DATE_FORMAT(r.race_date, '%m-%d') AS calendar_day
FROM
  races r
JOIN
  results res ON r.id = res.race_id
ORDER BY
  calendar_day;";

        return $this->executeResultsQuery(__METHOD__, $sql);
    }
}

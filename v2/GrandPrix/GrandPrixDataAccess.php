<?php

namespace IpswichJAFFARunningClubAPI\V2\GrandPrix;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';

use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class GrandPrixDataAccess extends DataAccess
{
    public function getGrandPrixPoints(int $year, int $sexId)
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

		return $this->executeResultsQuery(__METHOD__, $sql);
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

		return $this->executeResultsQuery(__METHOD__, $sql);
    }
}

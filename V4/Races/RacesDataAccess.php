<?php

namespace IpswichJAFFARunningClubAPI\V4\Races;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/DataAccess.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/Results/ResultsDataAccess.php';

use IpswichJAFFARunningClubAPI\V4\DataAccess as DataAccess;
use IpswichJAFFARunningClubAPI\V4\Results\ResultsDataAccess as ResultsDataAccess;

class RacesDataAccess extends DataAccess
{
    private $resultsDataAccess;

    public function __construct($db)
    {
        parent::__construct($db);
        $this->resultsDataAccess = new ResultsDataAccess($db);
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

        $result = $this->executeQuery(__METHOD__, $sql);

        if (!is_wp_error($result)) {
            return $this->getRace($this->resultsDatabase->insert_id);
        }

        return $result;
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
                        $field => $value, 'county' => null, 'area' => null,
                    ),
                    array('id' => $raceId),
                    array(
                        '%s', '%s', '%s',
                    ),
                    array('%d')
                );
            } else {
                $result = $this->resultsDatabase->update(
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
}

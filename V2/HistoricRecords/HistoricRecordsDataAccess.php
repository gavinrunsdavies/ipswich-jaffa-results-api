<?php

namespace IpswichJAFFARunningClubAPI\V2\HistoricRecords;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';

use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class HistoricRecordsDataAccess extends DataAccess
{
    public function getAllRaceResults(int $distanceId)
    {
        $sql = $this->resultsDatabase->prepare(
            "SELECT p.name, 
            p.id, 
            ra.date, 
            ra.id as 'raceId',
            ra.description as 'raceDescription',
            e.name as 'eventName',
            c.id as 'categoryId',
            c.code as 'categoryCode',
            r.result,
            r.performance,
            r.position,
            ra.course_type_id as 'courseTypeId',
            d.result_measurement_unit_type_id as resultMeasurementUnitTypeId,
            d.result_unit_type_id as resultUnitTypeId
            FROM `results` r
            inner join race ra on ra.id = r.race_id
            INNER JOIN runners p ON p.id = r.runner_id
            INNER JOIN events e ON e.id = ra.event_id
            INNER JOIN category c ON c.id = r.category_id
            INNER JOIN distance d ON d.id = ra.distance_id
            WHERE ra.distance_id = %d AND c.id > 0 AND r.result <> '00:00:00' AND r.result <> ''
            order by category_id asc, ra.date asc, r.performance asc",
            $distanceId
        );

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getAllRaceResultsByCategory(int $categoryId)
    {
        $sql = $this->resultsDatabase->prepare(
            "SELECT p.name, 
            p.id, 
            ra.date, 
            ra.id as 'raceId',
            ra.description as 'raceDescription',
            e.name as 'eventName',
            c.id as 'categoryId',
            c.code as 'categoryCode',
            r.result,
            r.performance,
            r.position,
            ra.course_type_id as 'courseTypeId',
            d.distance,
            d.id as 'distanceId',
            d.result_measurement_unit_type_id as resultMeasurementUnitTypeId,
            d.result_unit_type_id as resultUnitTypeId
            FROM `results` r
            INNER join race ra on ra.id = r.race_id
            INNER JOIN runners p ON p.id = r.runner_id
            INNER JOIN events e ON e.id = ra.event_id
            INNER JOIN category c ON c.id = r.category_id
            INNER JOIN distance d ON d.id = ra.distance_id
            WHERE r.category_id = %d AND r.result <> '00:00:00' AND r.result <> ''
            order by d.id asc, ra.date asc, r.performance asc",
            $categoryId
        );

        return $this->executeResultsQuery(__METHOD__, $sql);
    }
}

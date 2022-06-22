<?php

namespace IpswichJAFFARunningClubAPI\V2\Distances;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';

use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class DistancesDataAccess extends DataAccess
{
    public function getDistances()
    {
        // The following are deprecated: text, miles, resultMeasurementUnitTypeId
        $sql = 'SELECT
                d.id, 
                d.distance as text,
                d.distance as name,
                d.miles,
                d.units,
                d.result_measurement_unit_type_id as resultMeasurementUnitTypeId,
                d.result_unit_type_id as resultUnitTypeId,
                mut.name as resultUnitTypeName
                FROM distance d 
                LEFT JOIN `measurement_unit_type` mut ON d.result_unit_type_id = mut.id
                ORDER BY resultUnitTypeId, units';

        return $this->executeResultsQuery(__METHOD__, $sql);
    }
}

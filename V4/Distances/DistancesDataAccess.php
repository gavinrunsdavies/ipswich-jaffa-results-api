<?php

namespace IpswichJAFFARunningClubAPI\V4\Distances;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/DataAccess.php';

use IpswichJAFFARunningClubAPI\V4\DataAccess as DataAccess;

class DistancesDataAccess extends DataAccess
{
    public function getDistances()
    {
        $sql = 'SELECT
			         id, distance as text,
					 result_measurement_unit_type_id as resultMeasurementUnitTypeId,
					 miles
			         FROM distance';

        return $this->executeResultsQuery(__METHOD__, $sql);
    }
}

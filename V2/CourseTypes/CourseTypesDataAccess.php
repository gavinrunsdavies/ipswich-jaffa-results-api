<?php

namespace IpswichJAFFARunningClubAPI\V2\CourseTypes;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';

use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class CourseTypesDataAccess extends DataAccess
{
    public function getCourseTypes()
    {
        $sql = 'SELECT id, description FROM course_type ORDER BY id ASC';

        return $this->executeResultsQuery(__METHOD__, $sql);
    }
}

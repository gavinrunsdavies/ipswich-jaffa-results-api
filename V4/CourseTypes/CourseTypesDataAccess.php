<?php

namespace IpswichJAFFARunningClubAPI\V4\CourseTypes;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/DataAccess.php';

use IpswichJAFFARunningClubAPI\V4\DataAccess as DataAccess;

class CourseTypesDataAccess extends DataAccess
{
    public function getCourseTypes()
    {
        $sql = 'SELECT id, description FROM course_type ORDER BY id ASC';

        return $this->executeResultsQuery(__METHOD__, $sql);
    }
}

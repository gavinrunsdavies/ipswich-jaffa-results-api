<?php

namespace IpswichJAFFARunningClubAPI\V4\Genders;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/DataAccess.php';

use IpswichJAFFARunningClubAPI\V4\DataAccess as DataAccess;

class GendersDataAccess extends DataAccess
{
    public function getGenders()
    {
        $sql = 'SELECT * FROM sex ORDER BY sex';

        return $this->executeResultsQuery(__METHOD__, $sql);
    }
}

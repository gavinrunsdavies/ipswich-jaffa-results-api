<?php

namespace IpswichJAFFARunningClubAPI\V4\Categories;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/DataAccess.php';

use IpswichJAFFARunningClubAPI\V4\DataAccess as DataAccess;

class CategoriesDataAccess extends DataAccess
{
    public function getCategories()
    {
        $sql = 'SELECT id, code, description, sex_id as sexId, default_category as isDefault					 
			    FROM category
                WHERE id > 0
                ORDER BY sex_id, default_category desc, age_greater_equal';

        return $this->executeResultsQuery(__METHOD__, $sql);
    }
}

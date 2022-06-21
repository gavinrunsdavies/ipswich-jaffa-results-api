<?php

namespace IpswichJAFFARunningClubAPI\V4;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/Constants/ErrorMessages.php';

use IpswichJAFFARunningClubAPI\V4\Constants\ErrorMessages as ErrorMessages;

abstract class DataAccess
{
    protected $resultsDatabase;

    public function __construct($db)
    {
        $this->resultsDatabase = $db;
    }

    protected function executeResultsQuery(string $methodName, $sql)
    {
        $results = $this->resultsDatabase->get_results($sql);

        if ($this->resultsDatabase->num_rows == 0) {
            return new stdClass();
        }

        if (!$results) {
            return new \WP_Error(
                $methodName,
                ErrorMessages::GENERIC_ERROR_MESSAGE,
                array(
                    'status' => 500,
                    'last_query' => $this->resultsDatabase->last_query
                )
            );
        }        

        return $results;
    }

    protected function executeResultQuery(string $methodName, $sql)
    {
        $results = $this->resultsDatabase->get_row($sql);

        if ($this->resultsDatabase->num_rows == 0) {
            return new stdClass();
        }

        if (!$results) {
            return new \WP_Error(
                $methodName,
                ErrorMessages::GENERIC_ERROR_MESSAGE,
                array(
                    'status' => 500,
                    'last_query' => $this->resultsDatabase->last_query
                )
            );
        }

        return $results;
    }

    protected function executeQuery(string $methodName, $sql)
    {
        $results = $this->resultsDatabase->query($sql);

        if (!$results) {
            return new \WP_Error(
                $methodName,
                ErrorMessages::GENERIC_ERROR_MESSAGE,
                array(
                    'status' => 500,
                    'last_query' => $this->resultsDatabase->last_query
                )
            );
        }

        return $results;
    }
}

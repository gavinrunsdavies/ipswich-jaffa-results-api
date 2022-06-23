<?php

namespace IpswichJAFFARunningClubAPI\V2;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Constants/ErrorMessages.php';

use IpswichJAFFARunningClubAPI\V2\Constants\ErrorMessages as ErrorMessages;

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

        if (is_null($results) || !empty($this->resultsDatabase->last_error)) {
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

        if (is_null($results) || !empty($this->resultsDatabase->last_error)) {
            return new \WP_Error(
                $methodName,
                ErrorMessages::GENERIC_ERROR_MESSAGE,
                array(
                    'status' => 500,
                    'last_query' => $this->resultsDatabase->last_query,
                    'last_error' => $this->resultsDatabase->last_error
                )
            );
        }

        if ($this->resultsDatabase->num_rows == 0) {
            return new \stdClass();
        }

        return $results;
    }

    protected function executeQuery(string $methodName, $sql)
    {
        $results = $this->resultsDatabase->query($sql);

        if (is_null($results) || !empty($this->resultsDatabase->last_error)) {
            return new \WP_Error(
                $methodName,
                ErrorMessages::GENERIC_ERROR_MESSAGE,
                array(
                    'status' => 500,
                    'last_query' => $this->resultsDatabase->last_query,
                    'last_error' => $this->resultsDatabase->last_error
                )
            );
        }

        return $results;
    }

    protected function insertEntity(string $methodName, $sql, $getEntityFunction)
    {        
        $result = $this->resultsDatabase->query($sql);

        if (is_null($result) || !empty($this->resultsDatabase->last_error)) {
            return new \WP_Error(
                $methodName,
                'Unknown error in inserting entity in to the database',
                array(
                    'status' => 500,
                    'last_query' => $this->resultsDatabase->last_query,
                    'last_error' => $this->resultsDatabase->last_error
                )
            );
        }

        return $getEntityFunction($this->resultsDatabase->insert_id);
    }

    protected function updateEntity(string $methodName, string $tableName, string $field, string $value, int $id, $getEntityFunction)
    {        
        $result = $this->resultsDatabase->update(
            $tableName,
            array(
                $field => $value,
            ),
            array('id' => $id),
            array(
                '%s',
            ),
            array('%d')
        );

        if (is_null($result) || !empty($this->resultsDatabase->last_error)) {
            return new \WP_Error(
                $methodName,
                'Unknown error in updating entity in to the database',
                array(
                    'status' => 500,
                    'last_query' => $this->resultsDatabase->last_query,
                    'last_error' => $this->resultsDatabase->last_error
                )
            );
        }                

        return $getEntityFunction($id);
    }
}

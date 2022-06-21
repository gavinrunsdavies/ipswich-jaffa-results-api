<?php

namespace IpswichJAFFARunningClubAPI\V4\Runners;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/DataAccess.php';

use IpswichJAFFARunningClubAPI\V4\DataAccess as DataAccess;

class RunnersDataAccess extends DataAccess
{
    public function getRunners(bool $loggedIn = true)
    {
        if ($loggedIn === true) {
            $sql = "SELECT
				r.id,
				r.name,
				r.sex_id as 'sexId',
				r.dob as 'dateOfBirth',
				s.sex
				FROM `runners` r
				INNER JOIN 	`sex` s ON r.sex_id = s.id
				ORDER BY r.name";
        } else {
            $sql = "SELECT
				r.id,
				r.name,
				r.sex_id as 'sexId',
				s.sex
				FROM `runners` r
				INNER JOIN 	`sex` s ON r.sex_id = s.id
				ORDER BY r.name";
        }

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getRunner(int $runnerId)
    {
        $sql = $this->resultsDatabase->prepare("select r.id, r.name, r.sex_id as 'sexId', r.dob as 'dateOfBirth', 0 as 'isCurrentMember', s.sex, c.code as 'ageCategory'
				FROM
				runners r, category c, sex s
				WHERE r.id = %d
				AND r.sex_id = s.id
				AND r.sex_id = c.sex_id
				AND (
					(TIMESTAMPDIFF(YEAR, r.dob, CURDATE()) >= c.age_greater_equal AND TIMESTAMPDIFF(YEAR, r.dob, CURDATE()) < c.age_less_than)
					OR r.dob= '0000-00-00'
				)
				LIMIT 1", $runnerId);

        return $this->executeResultQuery(__METHOD__, $sql);
    }

    public function insertRunner($runner)
    {
        $sql = $this->resultsDatabase->prepare('INSERT INTO runners (`name`, `dob`, `sex_id`) VALUES(%s, %s, %d);', $runner['name'], $runner['dateOfBirth'], $runner['sexId']);

        $result = $this->resultsDatabase->query($sql);

        if ($result) {
            return $this->getRunner($this->resultsDatabase->insert_id);
        }

        return new \WP_Error(__METHOD__,
            'Unknown error in inserting runner in to the database', array('status' => 500));
    }

    public function deleteRunner(int $id)
    {
        // Check whether their are any results for this runner already.
        $sql = $this->resultsDatabase->prepare('SELECT COUNT(`id`) FROM results WHERE runner_id = %d LIMIT 1', $id);

        $exists = $this->resultsDatabase->get_var($sql);

        if ($exists != 0) {
            return new \WP_Error(__METHOD__,
                'Runner cannot be deleted; a number results are associated with this runner. Delete the existing results for this runner and try again.', array('status' => 409));
        }

        $sql = $this->resultsDatabase->prepare('DELETE FROM runners WHERE id = %d', $id);

        return $this->executeQuery(__METHOD__, $sql);
    }

    public function updateRunner(int $runnerId, string $field, string $value)
    {
        if ($field == 'name') {
            $result = $this->resultsDatabase->update(
                'runners',
                array(
                    $field => $value,
                ),
                array('id' => $runnerId),
                array(
                    '%s',
                ),
                array('%d')
            );

            if ($result) {
                return $this->getRunner($runnerId);
            }

            return new \WP_Error(__METHOD__,
                'Unknown error in updating runner in to the database', array('status' => 500));
        }
        return new \WP_Error(__METHOD__,
            'Field in event may not be updated', array('status' => 500, 'Field' => $field, 'Value' => $value));
    }
}

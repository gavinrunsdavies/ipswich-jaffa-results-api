<?php

namespace IpswichJAFFARunningClubAPI\V2\VolunteerRoles;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';

use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class VolunteerRolesDataAccess extends DataAccess
{
    public function getVolunteerRoles()
    {
        $sql = 'SELECT id, role FROM volunteer_roles ORDER BY id';

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getVolunteerRole(int $volunteerRoleId)
    {
        $sql = $this->resultsDatabase->prepare(
            'SELECT id, role FROM volunteer_roles WHERE id = %d',
            $volunteerRoleId
        );

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function insertVolunteerRole($volunteerRoleRequest)
    {
        $sql = $this->resultsDatabase->prepare(
            'INSERT INTO volunteer_roles (role) VALUES (%s)',
            $volunteerRoleRequest['role']
        );

        return $this->executeInsertQuery(__METHOD__, $sql);
    }

    public function updateVolunteerRole(int $volunteerRoleId, string $field, string $value)
    {
        if ($field === 'role') {
            $sql = $this->resultsDatabase->prepare(
                'UPDATE volunteer_roles SET role = %s WHERE id = %d',
                $value,
                $volunteerRoleId
            );

            return $this->executeUpdateQuery(__METHOD__, $sql);
        }

        return false;
    }
}
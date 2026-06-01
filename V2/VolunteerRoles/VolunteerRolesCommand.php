<?php

namespace IpswichJAFFARunningClubAPI\V2\VolunteerRoles;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once 'VolunteerRolesDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;

class VolunteerRolesCommand extends BaseCommand
{
    public function __construct($db)
    {
        parent::__construct(new VolunteerRolesDataAccess($db));
    }

    public function getVolunteerRoles(\WP_REST_Request $request)
    {
        $response = $this->dataAccess->getVolunteerRoles();

        return rest_ensure_response($response);
    }

    public function getVolunteerRole(\WP_REST_Request $request)
    {
        $response = $this->dataAccess->getVolunteerRole($request['volunteerRoleId']);

        return rest_ensure_response($response);
    }

    public function saveVolunteerRole(\WP_REST_Request $request)
    {
        $volunteerRole = $request['volunteerRole'];

        if (is_string($volunteerRole)) {
            $volunteerRole = array('role' => $volunteerRole);
        }

        $response = $this->dataAccess->insertVolunteerRole($volunteerRole);

        return rest_ensure_response($response);
    }

    public function updateVolunteerRole(\WP_REST_Request $request)
    {
        $response = $this->dataAccess->updateVolunteerRole(
            $request['volunteerRoleId'],
            $request['field'],
            $request['value']
        );

        return rest_ensure_response($response);
    }
}
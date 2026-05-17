<?php

namespace IpswichJAFFARunningClubAPI\V2\VolunteerRoles;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'VolunteerRolesCommand.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class VolunteerRolesController extends BaseController implements IRoute
{
    public function __construct(string $route, $db)
    {
        parent::__construct($route, new VolunteerRolesCommand($db));
    }

    public function registerRoutes()
    {
        register_rest_route($this->route, '/volunteer-roles', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array($this, 'getVolunteerRoles')
        ));

        register_rest_route($this->route, '/volunteer-roles/(?P<volunteerRoleId>[\d]+)', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array($this, 'getVolunteerRole'),
            'args' => array(
                'volunteerRoleId' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'isValidId'),
                )
            )
        ));

        register_rest_route($this->route, '/volunteer-roles', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'permission_callback' => array($this, 'isAuthorized'),
            'callback' => array($this, 'saveVolunteerRole'),
            'args' => array(
                'volunteerRole' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'validateVolunteerRole'),
                )
            )
        ));

        register_rest_route($this->route, '/volunteer-roles/(?P<volunteerRoleId>[\d]+)', array(
            'methods' => \WP_REST_Server::EDITABLE,
            'permission_callback' => array($this, 'isAuthorized'),
            'callback' => array($this, 'updateVolunteerRole'),
            'args' => array(
                'volunteerRoleId' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'isValidId')
                ),
                'field' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'isValidVolunteerRoleUpdateField')
                ),
                'value' => array(
                    'required' => true
                )
            )
        ));
    }

    public function getVolunteerRoles(\WP_REST_Request $request)
    {
        return rest_ensure_response($this->command->getVolunteerRoles($request));
    }

    public function getVolunteerRole(\WP_REST_Request $request)
    {
        return rest_ensure_response($this->command->getVolunteerRole($request));
    }

    public function saveVolunteerRole(\WP_REST_Request $request)
    {
        return rest_ensure_response($this->command->saveVolunteerRole($request));
    }

    public function updateVolunteerRole(\WP_REST_Request $request)
    {
        return rest_ensure_response($this->command->updateVolunteerRole($request));
    }

    public function validateVolunteerRole($volunteerRole, $request, $key)
    {
        if (empty($volunteerRole['role'])) {
            return new \WP_Error(
                'rest_invalid_param',
                sprintf('%s %s has invalid role value.', $key, json_encode($volunteerRole)),
                array('status' => 400)
            );
        }

        return true;
    }

    public function isValidVolunteerRoleUpdateField($value, $request, $key)
    {
        if ($value == 'role') {
            return true;
        } else {
            return new \WP_Error(
                'rest_invalid_param',
                sprintf('%s %s must be role only.', $key, $value),
                array('status' => 400)
            );
        }
    }
}
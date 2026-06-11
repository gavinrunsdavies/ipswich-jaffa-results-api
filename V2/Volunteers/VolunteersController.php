<?php

namespace IpswichJAFFARunningClubAPI\V2\Volunteers;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'VolunteersCommand.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class VolunteersController extends BaseController implements IRoute
{
    public function __construct(string $route, $db)
    {
        parent::__construct($route, new VolunteersCommand($db));
    }

    public function registerRoutes()
    {
        register_rest_route($this->route, '/volunteers/(?P<meetingId>[\d]+)', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array($this, 'getVolunteersForMeeting'),
            'args' => array(
                'meetingId' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'isValidId'),
                )
            )
        ));

        register_rest_route($this->route, '/volunteers', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'permission_callback' => array($this, 'isAuthorized'),
            'callback' => array($this, 'saveVolunteerAssignment'),
            'args' => array(
                'volunteerAssignment' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'validateVolunteerAssignment'),
                )
            )
        ));

        register_rest_route($this->route, '/volunteers/bulk', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'permission_callback' => array($this, 'isAuthorized'),
            'callback' => array($this, 'saveVolunteerAssignments'),
            'args' => array(
                'volunteerAssignments' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'validateVolunteerAssignments'),
                )
            )
        ));

        register_rest_route($this->route, '/volunteers/(?P<meetingId>[\d]+)/(?P<runnerId>[\d]+)', array(
            'methods' => \WP_REST_Server::DELETABLE,
            'permission_callback' => array($this, 'isAuthorized'),
            'callback' => array($this, 'deleteVolunteerAssignment'),
            'args' => array(
                'meetingId' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'isValidId')
                ),
                'runnerId' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'isValidId')
                )
            )
        ));
    }

    public function getVolunteersForMeeting(\WP_REST_Request $request)
    {
        return rest_ensure_response($this->command->getVolunteersForMeeting($request));
    }

    public function saveVolunteerAssignment(\WP_REST_Request $request)
    {
        return rest_ensure_response($this->command->saveVolunteerAssignment($request));
    }

    public function saveVolunteerAssignments(\WP_REST_Request $request)
    {
        return rest_ensure_response($this->command->saveVolunteerAssignments($request));
    }

    public function deleteVolunteerAssignment(\WP_REST_Request $request)
    {
        return rest_ensure_response($this->command->deleteVolunteerAssignment($request));
    }

    public function validateVolunteerAssignment($volunteerAssignment, $request, $key)
    {
        if (!empty($volunteerAssignment['meetingId']) && !is_numeric($volunteerAssignment['meetingId'])) {
            return new \WP_Error(
                'rest_invalid_param',
                sprintf('%s %s has invalid meetingId value.', $key, json_encode($volunteerAssignment)),
                array('status' => 400)
            );
        }

        if (!empty($volunteerAssignment['raceId']) && !is_numeric($volunteerAssignment['raceId'])) {
            return new \WP_Error(
                'rest_invalid_param',
                sprintf('%s %s has invalid raceId value.', $key, json_encode($volunteerAssignment)),
                array('status' => 400)
            );
        }

        if ((empty($volunteerAssignment['meetingId']) || !is_numeric($volunteerAssignment['meetingId'])) &&
            (empty($volunteerAssignment['raceId']) || !is_numeric($volunteerAssignment['raceId']))) {
            return new \WP_Error(
                'rest_invalid_param',
                sprintf('%s %s must include meetingId or raceId.', $key, json_encode($volunteerAssignment)),
                array('status' => 400)
            );
        }

        if (empty($volunteerAssignment['runnerId']) || !is_numeric($volunteerAssignment['runnerId'])) {
            return new \WP_Error(
                'rest_invalid_param',
                sprintf('%s %s has invalid runnerId value.', $key, json_encode($volunteerAssignment)),
                array('status' => 400)
            );
        }

        if (empty($volunteerAssignment['volunteerRoleId']) || !is_numeric($volunteerAssignment['volunteerRoleId'])) {
            return new \WP_Error(
                'rest_invalid_param',
                sprintf('%s %s has invalid volunteerRoleId value.', $key, json_encode($volunteerAssignment)),
                array('status' => 400)
            );
        }

        return true;
    }

    public function validateVolunteerAssignments($volunteerAssignments, $request, string $key)
    {
        if (!is_array($volunteerAssignments)) {
            return new \WP_Error(
                'rest_invalid_param',
                sprintf('%s must be an array of volunteer assignments.', $key),
                array('status' => 400)
            );
        }

        foreach ($volunteerAssignments as $assignment) {
            $result = $this->validateVolunteerAssignment($assignment, $request, $key);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        return true;
    }
}

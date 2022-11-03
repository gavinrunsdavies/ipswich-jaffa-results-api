<?php

namespace IpswichJAFFARunningClubAPI\V2\Meetings;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'MeetingsCommand.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class MeetingsController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new MeetingsCommand($db));
	}

	public function registerRoutes()
	{
		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/meetings', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getMeetings'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				)
			)
		));

		// Return an event meeting for a race.
		register_rest_route($this->route, '/meetingdetails/(?P<raceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getMeetingForRace'),
			'args'                => array(
				'raceId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/meetings/(?P<meetingId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getMeeting'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				),
				'meetingId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		register_rest_route($this->route, '/meetings/(?P<meetingId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getMeetingById'),
			'args'                => array(
				'meetingId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				)
			)
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/meetings/(?P<meetingId>[\d]+)/races', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getMeetingRaces'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				),
				'meetingId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				)
			)
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/meetings', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this->command, 'saveMeeting'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				),
				'meeting'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'validateMeeting'),
				),
			)
		));

		// Patch - updates
		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/meetings/(?P<meetingId>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this->command, 'updateMeeting'),
			'args'                => array(
				'meetingId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidMeetingUpdateField')
				),
				'value'           => array(
					'required'          => true
				)
			)
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/meetings/(?P<meetingId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array($this->command, 'deleteMeeting'),
			'permission_callback' => array($this, 'isAuthorized'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'meetingId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				),
			)
		));
	}

	public function getMeetingForRace(\WP_REST_Request $request)
	{
		return rest_ensure_response($this->command->getMeetingForRace($request['raceId']));
	}

	public function getMeetings(\WP_REST_Request $request)
	{
		return rest_ensure_response($this->command->getMeetings($request['eventId']));
	}
	
	public function isValidMeetingUpdateField(string $value, \WP_REST_Request $request, string $key)
	{
		if ($value == 'from_date' || $value == 'to_date' || $value == 'name') {
			return true;
		} else {
			return new \WP_Error(
				'rest_invalid_param',
				sprintf('%s %d must be name or fromDate or toDate only.', $key, $value),
				array('status' => 400)
			);
		}
	}

	public function validateMeeting($meeting, $request, string $key)
	{
		$date = date_parse($meeting['fromDate']);
		if (checkdate($date['month'], $date['day'], $date['year']) === FALSE) {
			return new \WP_Error(
				'rest_invalid_param',
				sprintf('%s %s has invalid from date value', $key, json_encode($meeting)),
				array('status' => 400)
			);
		}

		$date = date_parse($meeting['toDate']);
		if (checkdate($date['month'], $date['day'], $date['year']) === FALSE) {
			return new \WP_Error(
				'rest_invalid_param',
				sprintf('%s %s has invalid to date value', $key, json_encode($meeting)),
				array('status' => 400)
			);
		}
	}
}

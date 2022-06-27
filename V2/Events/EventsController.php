<?php

namespace IpswichJAFFARunningClubAPI\V2\Events;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'EventsCommand.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class EventsController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new EventsCommand($db));
	}

	public function registerRoutes()
	{
		register_rest_route($this->route, '/events', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getEvents')
		));

		register_rest_route($this->route, '/events', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this->command, 'saveEvent'),
			'args'                => array(
				'event'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'validateEvent'),
				)
			)
		));

		// Patch - updates
		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this->command, 'updateEvent'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidEventUpdateField')
				),
				'value'           => array(
					'required'          => true
				)
			)
		));

		register_rest_route($this->route, '/events/merge', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this->command, 'mergeEvents'),
			'args'                => array(
				'fromEventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'toEventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array($this->command, 'deleteEvent'),
			'permission_callback' => array($this, 'isAuthorized'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/topAttendees', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getTopAttendees'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				)
			)
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/insights', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getEventRaceInsights'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				)
			)
		));
	}

	public function isValidEventUpdateField(string $value, $request, string $key)
	{
		if ($value == 'name' || $value == 'website') {
			return true;
		} else {
			return new \WP_Error(
				'rest_invalid_param',
				sprintf('%s %d must be name or website only.', $key, $value),
				array('status' => 400)
			);
		}
	}
}

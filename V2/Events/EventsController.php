<?php

namespace IpswichJAFFARunningClubAPI\V2\Events;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'EventsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class EventsController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new EventsDataAccess($db));
	}

	public function registerRoutes()
	{
		register_rest_route($this->route, '/events', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getEvents')
		));

		register_rest_route($this->route, '/events', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this, 'saveEvent'),
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
			'callback'            => array($this, 'updateEvent'),
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
			'callback'            => array($this, 'mergeEvents'),
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
			'callback'            => array($this, 'deleteEvent'),
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
			'callback'            => array($this, 'getTopAttendees'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				)
			)
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/insights', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getEventRaceInsights'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				)
			)
		));
	}

	public function getEventRaceInsights(\WP_REST_Request $request)
	{
		$distanceMetrics = $this->dataAccess->getEventRaceInsightsByDistance($request['eventId']);

		$yearlyMetrics = $this->dataAccess->getEventRaceInsightsByYear($request['eventId']);

		$response = array(
			"years" => $yearlyMetrics,
			"distance" => $distanceMetrics
		);

		return rest_ensure_response($response);
	}

	public function getEvents(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getEvents();

		return rest_ensure_response($response);
	}

	public function getTopAttendees(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getEventTopAttendees($request['eventId']);

		// Array: year, name, count
		// Transform to JSON of form:
		// Data = {}
		// "year1" : {
		//	 	"name1" : count,
		// 		"name2" : count,
		// 		"name3" : count,
		// },
		// "year2" : {
		//	 	"name1" : count,
		// 		"name2" : count,
		// 		"name3" : count,
		// },

		$topAttendeesByYear = array();
		$lastYear = 0;
		foreach ($response as $item) {

			// Should only be the first time
			if (!array_key_exists($item->year, $topAttendeesByYear)) {
				$topAttendeesByYear[$item->year] = new class
				{
				};
			}

			// Build up cumulative values. Add all values from last year
			if ($lastYear != $item->year) {
				if ($lastYear != 0) {
					$topAttendeesByYear[$item->year] = clone $topAttendeesByYear[$lastYear];
				}
				$lastYear = $item->year;
			}

			$topAttendeesByYear[$item->year]->{$item->name} = $item->runningTotal;
		}

		return rest_ensure_response($topAttendeesByYear);
	}

	public function saveEvent(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->insertEvent($request['event']);

		return rest_ensure_response($response);
	}

	public function updateEvent(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->updateEvent($request['eventId'], $request['field'], $request['value']);

		return rest_ensure_response($response);
	}

	public function mergeEvents(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->mergeEvents($request['fromEventId'], $request['toEventId']);

		return rest_ensure_response($response);
	}

	public function deleteEvent(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->deleteEvent($request['eventId']);

		return rest_ensure_response($response);
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

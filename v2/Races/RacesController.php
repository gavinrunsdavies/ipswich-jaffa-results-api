<?php

namespace IpswichJAFFARunningClubAPI\V2\Races;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'RacesDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class RacesController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new RacesDataAccess($db));
	}

	public function registerRoutes()
	{
		// Save Race - two routes
		register_rest_route($this->route, '/races', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this, 'saveRace')
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/races', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this, 'saveRace'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		// Get Race - two routes
		register_rest_route($this->route, '/races/(?P<id>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getRace'),
			'args'                 => array(
				'id'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/races/(?P<id>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getRace'),
			'args'                 => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'id'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		// Update Race - two routes
		register_rest_route($this->route, '/races/(?P<id>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this, 'updateRace'),
			'args'                => array(
				'id'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidRaceUpdateField')
				),
				'value'           => array(
					'required'          => true
				)
			)
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/races/(?P<id>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this, 'updateRace'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'id'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidRaceUpdateField')
				),
				'value'           => array(
					'required'          => true
				)
			)
		));

		// Delete race - one route
		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/race/(?P<raceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array($this, 'deleteRace'),
			'permission_callback' => array($this, 'isAuthorized'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'raceId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				)
			)
		));
	}

	public function saveRace(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->insertRace($request['race']);

		return rest_ensure_response($response);
	}

	public function getRace(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->getRace($request['id']);

		return rest_ensure_response($response);
	}

	public function updateRace(\WP_REST_Request $request)
	{

		if ($request['field'] == "distance_id") {
			$response = $this->dataAccess->updateRaceDistance($request['id'], $request['value']);
		} else {
			$response = $this->dataAccess->updateRace($request['id'], $request['field'], $request['value']);
		}

		return rest_ensure_response($response);
	}

	public function deleteRace(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->deleteRace($request['raceId'], false);

		return rest_ensure_response($response);
	}

	public function isValidRaceUpdateField($value, $request, $key)
	{
		if (
			$value == 'event_id' ||
			$value == 'description' ||
			$value == 'course_type_id' ||
			$value == 'course_number' ||
			$value == 'area' ||
			$value == 'county' ||
			$value == 'country_code' ||
			$value == 'venue' ||
			$value == 'distance_id' ||
			$value == 'conditions' ||
			$value == 'meeting_id' ||
			$value == 'league_id' ||
			$value == 'grand_prix'
		) {
			return true;
		} else {
			return new \WP_Error(
				'rest_invalid_param',
				sprintf('%s %s has an invalid value.', $key, $value),
				array('status' => 400)
			);
		}
	}
}

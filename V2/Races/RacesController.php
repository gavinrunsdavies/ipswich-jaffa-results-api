<?php

namespace IpswichJAFFARunningClubAPI\V2\Races;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'RacesCommand.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class RacesController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new RacesCommand($db));
	}

	public function registerRoutes()
	{
		// Save Race - two routes
		register_rest_route($this->route, '/races', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this->command, 'saveRace')
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/races', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this->command, 'saveRace'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/races', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getRaces'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				)
			)
		));

		// Get Race - two routes
		register_rest_route($this->route, '/races/(?P<id>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getRace'),
			'args'                 => array(
				'id'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/races/(?P<id>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getRace'),
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
			'callback'            => array($this->command, 'updateRace'),
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
			'callback'            => array($this->command, 'updateRace'),
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

		// Delete race
		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/races/(?P<raceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array($this->command, 'deleteRace'),
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

		// TODO "race" not "races" in URL
		register_rest_route($this->route, '/events/(?P<eventId>[\d]+)/race/(?P<raceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array($this->command, 'deleteRace'),
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

	public function isValidRaceUpdateField(string $value, $request, string $key)
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
			$value == 'grand_prix' ||
			$value == 'report'
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

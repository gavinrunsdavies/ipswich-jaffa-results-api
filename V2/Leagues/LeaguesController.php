<?php

namespace IpswichJAFFARunningClubAPI\V2\Leagues;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'LeaguesCommand.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class LeaguesController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new LeaguesCommand($db));
	}

	public function registerRoutes()
	{
		register_rest_route($this->route, '/leagues', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getLeagues')
		));

		register_rest_route($this->route, '/leagues/(?P<leagueId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getLeague'),
			'args'                => array(
				'leagueId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId'),
				)
			)
		));

		register_rest_route($this->route, '/leagues', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this->command, 'saveLeague'),
			'args'                => array(
				'league'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'validateLeague'),
				)
			)
		));

		// Patch - updates
		register_rest_route($this->route, '/leagues/(?P<leagueId>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this->command, 'updateLeague'),
			'args'                => array(
				'leagueId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidLeagueUpdateField')
				),
				'value'           => array(
					'required'          => true
				)
			)
		));

		register_rest_route($this->route, '/leagues/(?P<leagueId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array($this->command, 'deleteLeague'),
			'permission_callback' => array($this, 'isAuthorized'),
			'args'                => array(
				'leagueId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));
	}

	public function isValidLeagueUpdateField($value, $request, $key)
	{
		if ($value == 'name') {
			return true;
		} else {
			return new \WP_Error(
				'rest_invalid_param',
				sprintf('%s %s must be name only.', $key, $value),
				array('status' => 400)
			);
		}
	}

	public function validateLeague($value, $request, $key)
	{
		if ($value != null) {
			return true;
		} else {
			return new \WP_Error(
				'rest_invalid_param',
				sprintf('%s %s invalid.', $key, $value),
				array('status' => 400)
			);
		}
	}
}

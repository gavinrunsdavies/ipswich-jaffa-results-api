<?php

namespace IpswichJAFFARunningClubAPI\V2\Runners;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Results/ResultsCommand.php';
require_once 'RunnersCommand.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;
use IpswichJAFFARunningClubAPI\V2\Results\ResultsCommand as ResultsCommand;

class RunnersController extends BaseController implements IRoute
{
    private $resultsCommand;
    
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new RunnersCommand($db));
        $this->resultsCommand = new ResultsCommand($db);
	}

	public function registerRoutes()
	{
		register_rest_route($this->route, '/runners', array(
			'methods' => \WP_REST_Server::READABLE,
			'callback' => array($this, 'getRunners')
		));

		register_rest_route($this->route, '/runners/(?P<runnerId>[\d]+)', array(
			'methods' => \WP_REST_Server::READABLE,
			'callback' => array($this, 'getRunner'),
			'args' => array(
				'runnerId' => array(
					'required' => true,
					'validate_callback' => array($this, 'isValidId'),
				)
			)
		));

		register_rest_route($this->route, '/runners', array(
			'methods' => \WP_REST_Server::CREATABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback' => array($this, 'saveRunner'),
			'args' => array(
				'runner' => array(
					'required' => true,
					'validate_callback' => array($this, 'validateRunner'),
				)
			)
		));

		register_rest_route($this->route, '/runners/(?P<runnerId>[\d]+)', array(
			'methods' => \WP_REST_Server::DELETABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback' => array($this, 'deleteRunner'),
			'args' => array(
				'runnerId' => array(
					'required' => true,
					'validate_callback' => array($this, 'isValidId'),
				)
			)
		));

		register_rest_route($this->route, '/runners/(?P<runnerId>[\d]+)', array(
			'methods' => \WP_REST_Server::EDITABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback' => array($this, 'updateRunner'),
			'args' => array(
				'runnerId' => array(
					'required' => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'field' => array(
					'required' => true,
					'validate_callback' => array($this, 'isValidRunnerUpdateField')
				),
				'value' => array(
					'required' => true
				)
			)
		));

        register_rest_route($this->route, '/runners/(?P<runnerId>[\d]+)/badges', array(
			'methods' => \WP_REST_Server::CREATABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback' => array($this, 'addRunnerBadges'),
			'args' => array(
				'runnerId' => array(
					'required' => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));
	}	

	public function getRunners(\WP_REST_Request $request)
	{
		return rest_ensure_response($this->command->getRunners());
	}

	public function getRunner(\WP_REST_Request $request)
	{
		return rest_ensure_response($this->command->getRunner($request['runnerId']));
	}

	public function saveRunner(\WP_REST_Request $request)
	{
		return rest_ensure_response($this->command->saveRunner($request['runner']));
	}

    public function addRunnerBadges(\WP_REST_Request $request)
	{
		return rest_ensure_response($this->resultsCommand->addRunnerBadges($request['runnerId'], $request['badges']));
	}

	public function deleteRunner(\WP_REST_Request $request)
	{
		return rest_ensure_response($this->command->deleteRunner($request['runnerId']));
	}

	public function updateRunner(\WP_REST_Request $request)
	{
		return rest_ensure_response($this->command->updateRunner($request['runnerId'], $request['field'], $request['value']));
	}

	public function isValidRunnerUpdateField($value, $request, $key)
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

	public function validateRunner($runner, $request, $key)
	{
		if (empty($runner['name'])) {
			return new \WP_Error(
				'rest_invalid_param',
				sprintf('%s %s has invalid name value.', $key, json_encode($runner)),
				array('status' => 400)
			);
		}

		if (intval($runner['sexId']) < 0) {
			return new \WP_Error(
				'rest_invalid_param',
				sprintf('%s %s has invalid sexId value', $key, json_encode($runner)),
				array('status' => 400)
			);
		}

		$date = date_parse($runner['dateOfBirth']);
		if (checkdate($date['month'], $date['day'], $date['year']) === false) {
			return new \WP_Error(
				'rest_invalid_param',
				sprintf('%s %s has invalid dateOfBirth value', $key, json_encode($runner)),
				array('status' => 400)
			);
		} else {
			return true;
		}
	}
}

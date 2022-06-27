<?php

namespace IpswichJAFFARunningClubAPI\V2\Runners;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Distances/Distances.php';
require_once 'RunnersDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;
use IpswichJAFFARunningClubAPI\V2\Distances\Distances as Distances;

class RunnersCommand extends BaseCommand
{
	public function __construct($db)
	{
		parent::__construct(new RunnersDataAccess($db));
	}

	public function getRunners(\WP_REST_Request $request)
	{
		$loggedIn = $this->isAuthorized($request);
		$response = $this->dataAccess->getRunners($loggedIn);

		return rest_ensure_response($response);
	}

	public function getRunner(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getRunner($request['runnerId']);
		$certificates = $this->dataAccess->getStandardCertificates($request['runnerId']);
		$distances = array(
			Distances::FIVE_KILOMETRES,
			Distances::FIVE_MILES,
			Distances::TEN_KILOMETRES,
			Distances::TEN_MILES,
			Distances::HALF_MARATHON,
			Distances::TWENTY_MILES,
			Distances::MARATHON
		);

		$rankings = $this->dataAccess->getRunnerRankings($request['runnerId'], $response->sexId, $distances);

		$response->certificates = $certificates;
		$response->rankings = $rankings;

		return rest_ensure_response($response);
	}

	public function saveRunner(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->insertRunner($request['runner']);

		return rest_ensure_response($response);
	}

	public function deleteRunner(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->deleteRunner($request['runnerId']);

		return rest_ensure_response($response);
	}

	public function updateRunner(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->updateRunner($request['runnerId'], $request['field'], $request['value']);

		return rest_ensure_response($response);
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

<?php

namespace IpswichJAFFARunningClubAPI\V2\RunnerOfTheMonth;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Genders/Genders.php';
require_once 'RunnerOfTheMonthDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;
use IpswichJAFFARunningClubAPI\V2\Genders\Genders as Genders;

class RunnerOfTheMonthController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new RunnerOfTheMonthDataAccess($db));
	}

	public function registerRoutes()
	{
		register_rest_route($this->route, '/runnerofthemonth', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback'            => array($this, 'saveWinners')
		));

		register_rest_route($this->route, '/runnerofthemonth/vote', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array($this, 'saveRunnerOfTheMonthVote')
		));

		register_rest_route($this->route, '/runnerofthemonth/resultsvote/(?P<resultId>[\d]+)', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array($this, 'saveRunnerOfTheMonthResultVote'),
			'args'                => array(
				'resultId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				)
			)
		));

		register_rest_route($this->route, '/runnerofthemonth/winners', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getRunnerOfTheMonthWinners')
		));

		register_rest_route($this->route, '/runnerofthemonth/winners/year/(?P<year>[\d]+)/month/(?P<month>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getRunnerOfTheMonthWinners'),
			'args'                => array(
				'year'           => array(
					'required'          => true
				),
				'month'           => array(
					'required'          => true
				)
			)
		));
	}

	public function saveWinners(\WP_REST_Request $request)
	{
		$mensWinnerRessponse = true;
		$womensWinnerRessponse = true;
		$boysWinnerRessponse = true;
		$girlsWinnerRessponse = true;
		if ($request['winners']['men'] > 0) {
			$mensWinnerRessponse = $this->dataAccess->insertRunnerOfTheMonthWinners(
				$request['winners']['men'],
				'Men',
				$request['winners']['month'],
				$request['winners']['year']
			);
		}

		if ($request['winners']['women'] > 0) {
			$womensWinnerRessponse = $this->dataAccess->insertRunnerOfTheMonthWinners(
				$request['winners']['women'],
				'Ladies',
				$request['winners']['month'],
				$request['winners']['year']
			);
		}

		if ($request['winners']['boys'] > 0) {
			$boysWinnerRessponse = $this->dataAccess->insertRunnerOfTheMonthWinners(
				$request['winners']['boys'],
				'Boys',
				$request['winners']['month'],
				$request['winners']['year']
			);
		}

		if ($request['winners']['girls'] > 0) {
			$girlsWinnerRessponse = $this->dataAccess->insertRunnerOfTheMonthWinners(
				$request['winners']['girls'],
				'Girls',
				$request['winners']['month'],
				$request['winners']['year']
			);
		}

		return rest_ensure_response($mensWinnerRessponse && $womensWinnerRessponse && $boysWinnerRessponse && $girlsWinnerRessponse);
	}

	public function saveRunnerOfTheMonthVote(\WP_REST_Request $request)
	{
		// Validate user vote
		$voter = $this->dataAccess->getRunner($request['voterId']);
		if (get_class($voter) == 'WP_Error' || $voter->dateOfBirth != $request['voterDateOfBirth']) {
			return rest_ensure_response(new \WP_Error(
				'save_runnerofthemonthvote_invalid',
				'Runner and date of birth do not match.',
				array('status' => 401, "data" => $request, "voter" => json_encode($voter))
			));
		}

		$now = new \DateTime();

		if ($request['men'] != null) {
			$vote = array();
			$vote['runnerId'] = $request['men']['runnerId'];
			$vote['reason'] = $request['men']['reason'];
			$vote['category'] = 'Men';
			$vote['month'] =  $request['month'];
			$vote['year'] =  $request['year'];
			$vote['voterId'] = $request['voterId'];
			$vote['ipAddress'] = $_SERVER['REMOTE_ADDR'];
			$vote['created'] = $now->format('Y-m-d H:i:s');

			$response1 = $this->dataAccess->insertRunnerOfTheMonthVote($vote);
		}

		if ($request['ladies'] != null) {
			$vote = array();
			$vote['runnerId'] = $request['ladies']['runnerId'];
			$vote['reason'] = $request['ladies']['reason'];
			$vote['category'] = 'Ladies';
			$vote['month'] =  $request['month'];
			$vote['year'] =  $request['year'];
			$vote['voterId'] = $request['voterId'];
			$vote['ipAddress'] = $_SERVER['REMOTE_ADDR'];
			$vote['created'] = $now->format('Y-m-d H:i:s');

			$response2 = $this->dataAccess->insertRunnerOfTheMonthVote($vote);
		}

		return rest_ensure_response($response1 && $response2);
	}

	public function saveRunnerOfTheMonthResultVote(\WP_REST_Request $request)
	{
		$command = new CheckRegistrationStatusCommand(JAFFA_RESULTS_UkAthleticsLicenceCheckUrl, JAFFA_RESULTS_UkAthleticsWebAccessKey);

		$ukMembershipResponse = $command->checkRegistrationStatus($request['voterId']);
		if ($ukMembershipResponse->success === false) {
			return rest_ensure_response(new \WP_Error(
				'saveRunnerOfTheMonthResultVote_invalid',
				'UK Athletics Number not valid for Ipswich JAFFA RC Membership.',
				array('status' => 401, "data" => $request, "number" => $request['voterId'])
			));
		}

		if (strcasecmp($ukMembershipResponse->lastName, $request['lastName']) != 0) {
			return rest_ensure_response(new \WP_Error(
				'saveRunnerOfTheMonthResultVote_invalid',
				'Last name supplied does not match that returned by UK Athletics for the membership number.',
				array(
					'status' => 401,
					'data' => $request
				)
				//'ukMembershipResponse' => json_encode($ukMembershipResponse)  ) 
			));
		}

		$now = new \DateTime();

		$result = $this->dataAccess->getResult($request['resultId']);
		$date = strtotime($result->date);

		$vote = array();
		$vote['runnerId'] = $result->runnerId;
		$vote['reason'] = "Event: $result->eventName; result: $result->result; position: $result->position";
		$vote['category'] = $this->getRunnerOfMonthCategory($result->sexId);
		$vote['month'] = date('n', $date);
		$vote['year'] = date('Y', $date);
		$vote['voterId'] = $request['voterId'];
		$vote['ipAddress'] = $_SERVER['REMOTE_ADDR'];
		$vote['created'] = $now->format('Y-m-d H:i:s');

		$response = $this->dataAccess->insertRunnerOfTheMonthVote($vote);

		return rest_ensure_response($response);
	}

	private function getRunnerOfMonthCategory($sexId)
	{
		if ($sexId == Genders::MALE) {
			return 'Men';
		} else {
			return 'Ladies';
		}
	}

	public function getRunnerOfTheMonthWinners(\WP_REST_Request $request)
	{
		$year = isset($request['year']) ? $request['year'] : 0;
		$month = isset($request['month']) ? $request['month'] : 0;
		$response = $this->dataAccess->getRunnerOfTheMonthWinnners($year, $month);

		return rest_ensure_response($response);
	}
}

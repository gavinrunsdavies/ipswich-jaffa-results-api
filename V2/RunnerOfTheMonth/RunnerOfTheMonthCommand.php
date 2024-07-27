<?php

namespace IpswichJAFFARunningClubAPI\V2\RunnerOfTheMonth;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Genders/Genders.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Runners/RunnersCommand.php';
require_once 'RunnerOfTheMonthDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;
use IpswichJAFFARunningClubAPI\V2\Genders\Genders as Genders;

class RunnerOfTheMonthCommand extends BaseCommand
{
	const MENS_CATEGORY = 'Men';
	const LADIES_CATEGORY = 'Ladies';

	public function __construct($db)
	{
		parent::__construct(new RunnerOfTheMonthDataAccess($db));
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
				self::MENS_CATEGORY,
				$request['winners']['month'],
				$request['winners']['year']
			);
		}

		if ($request['winners']['women'] > 0) {
			$womensWinnerRessponse = $this->dataAccess->insertRunnerOfTheMonthWinners(
				$request['winners']['women'],
				self::LADIES_CATEGORY,
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
		$isValid = $this->dataAccess->validateVoter($request['voterId'], $request['voterDateOfBirth']);
		if (is_wp_error($isValid)) {
			return rest_ensure_response(new \WP_Error(
				__METHOD__,
				'Runner and date of birth do not match.',
				array('status' => 401, "data" => $request)
			));
		}

		$now = new \DateTime();
		$response1 = true;
		$response2 = true;

		if ($request['men'] != null) {
			$vote = array();
			$vote['runnerId'] = $request['men']['runnerId'];
			$vote['reason'] = $request['men']['reason'];
			$vote['category'] = self::MENS_CATEGORY;
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
			$vote['category'] = self::LADIES_CATEGORY;
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
				__METHOD__,
				'UK Athletics Number not valid for Ipswich JAFFA RC Membership.',
				array('status' => 401, "data" => $request, "number" => $request['voterId'])
			));
		}

		if (strcasecmp($ukMembershipResponse->lastName, $request['lastName']) != 0) {
			return rest_ensure_response(new \WP_Error(
				__METHOD__,
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

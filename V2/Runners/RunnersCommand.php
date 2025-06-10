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

	public function getRunners()
	{
		$loggedIn = $this->isLoggedInAsEditor();
		return $this->dataAccess->getRunners($loggedIn);
	}

	public function getRunner(int $runnerId)
	{
		$runner = $this->dataAccess->getRunner($runnerId);
		$certificates = $this->dataAccess->getStandardCertificates($runnerId);
		$rankings = [];

		if ($runner->ageAtLastRace > 0) {
			if ($runner->ageAtLastRace >= 16) {
				$distances = array(
					Distances::ONE_MILE,
					Distances::FIVE_KILOMETRES,
					Distances::FIVE_MILES,
					Distances::TEN_KILOMETRES,
					Distances::TEN_MILES,
					Distances::HALF_MARATHON,
					Distances::TWENTY_MILES,
					Distances::MARATHON
				);
			} else {
				$distances = array(
					Distances::FOUR_HUNDRED_METRES,
					Distances::SIX_HUNDRED_METRES,
					Distances::EIGHT_HUNDRED_METRES,
					Distances::ONE_KILOMETRE,
					Distances::FIFTEN_HUNDRED_METRES,
					Distances::ONE_MILE,
					Distances::FIVE_KILOMETRES,
					Distances::FIVE_MILES				
				);
			} 

			$rankings = $this->dataAccess->getRunnerRankings($runnerId, $runner->sexId, $distances);
		}

		$runner->certificates = $certificates;
		$runner->rankings = $rankings;
 		
		return $runner;
	}

	public function saveRunner($runnerRequest)
	{
		return $this->dataAccess->insertRunner($runnerRequest);
	}

	public function deleteRunner(int $runnerId)
	{
		return $this->dataAccess->deleteRunner($runnerId);
	}

	public function updateRunner(int $runnerId, string $field, string $value)
	{
		return $this->dataAccess->updateRunner($runnerId, $field, $value);
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

	private function isLoggedInAsEditor()
    {
        return (current_user_can('editor') || current_user_can('administrator'));
    }
}

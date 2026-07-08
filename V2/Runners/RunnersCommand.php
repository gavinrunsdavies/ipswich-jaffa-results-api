<?php

namespace IpswichJAFFARunningClubAPI\V2\Runners;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Distances/Distances.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Distances/DistancesCommand.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/RunnerResults/RunnerResultsCommand.php';
require_once 'RunnersDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;
use IpswichJAFFARunningClubAPI\V2\Distances\Distances as Distances;
use IpswichJAFFARunningClubAPI\V2\Distances\DistancesCommand as DistancesCommand;
use IpswichJAFFARunningClubAPI\V2\RunnerResults\RunnerResultsCommand as RunnerResultsCommand;

class RunnersCommand extends BaseCommand
{
	private $runnerResultsCommand;
	private $distancesCommand;

	public function __construct($db)
	{
		parent::__construct(new RunnersDataAccess($db));
		$this->runnerResultsCommand = new RunnerResultsCommand($db);
		$this->distancesCommand = new DistancesCommand($db);
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

	public function getRunnerProfile(int $runnerId)
	{
		$runner = $this->getRunner($runnerId);

		$resultsRequest = new \WP_REST_Request('GET', '/');
		$resultsRequest->set_param('runnerId', $runnerId);
		$resultsResponse = $this->runnerResultsCommand->getMemberResults($resultsRequest);
		$results = $resultsResponse instanceof \WP_REST_Response ? $resultsResponse->get_data() : $resultsResponse;

		$distancesRequest = new \WP_REST_Request('GET', '/');
		$distancesResponse = $this->distancesCommand->getDistances($distancesRequest);
		$distances = $distancesResponse instanceof \WP_REST_Response ? $distancesResponse->get_data() : $distancesResponse;

		$insightsByDistance = [];
		$distanceIds = $this->getTopDistanceIds($results);

		foreach ($distanceIds as $distanceId) {
			$insightsRequest = new \WP_REST_Request('GET', '/');
			$insightsRequest->set_param('runnerId', $runnerId);
			$insightsRequest->set_param('distanceId', $distanceId);
			$insightsResponse = $this->runnerResultsCommand->getMemberInsightsRaceDistance($insightsRequest);
			$insights = $insightsResponse instanceof \WP_REST_Response ? $insightsResponse->get_data() : $insightsResponse;
			if (!is_wp_error($insights) && !empty($insights['raceTimes'])) {
				$insightsByDistance[(string) $distanceId] = $insights;
			}
		}

		return array(
			'runner' => $runner,
			'distances' => $distances,
			'results' => $results,
			'insightsByDistance' => $insightsByDistance,
		);
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

	private function getTopDistanceIds(array $results): array
	{
		$counts = array();

		foreach ($results as $result) {
			if (!isset($result->distanceId) || $result->distanceId === null || $result->distanceId == '0') {
				continue;
			}

			if (isset($result->performance) && $result->performance == '0.000') {
				continue;
			}

			$distanceId = (string) $result->distanceId;
			$counts[$distanceId] = isset($counts[$distanceId]) ? $counts[$distanceId] + 1 : 1;
		}

		arsort($counts);
		$distanceIds = array_slice(array_keys($counts), 0, 8);

		return array_map('intval', $distanceIds);
	}

	private function isLoggedInAsEditor()
    {
        return (current_user_can('editor') || current_user_can('administrator'));
    }
}

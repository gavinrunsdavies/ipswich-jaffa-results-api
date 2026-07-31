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
 		
		return $this->normalizeRunner($runner);
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
			'distances' => $this->normalizeDistances($distances),
			'results' => $this->normalizeResults($results),
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

	private function normalizeRunner($runner)
	{
		if (!is_object($runner)) {
			return $runner;
		}

		$runner->id = $this->toInt($runner->id);
		$runner->sexId = $this->toInt($runner->sexId);
		$runner->ageAtLastRace = $this->toInt($runner->ageAtLastRace);

		if (isset($runner->certificates)) {
			$runner->certificates = $this->normalizeCertificates($runner->certificates);
		}

		if (isset($runner->rankings)) {
			$runner->rankings = $this->normalizeRankings($runner->rankings);
		}

		return $runner;
	}

	private function normalizeCertificates($certificates)
	{
		if (!is_array($certificates)) {
			return $certificates;
		}

		return array_map(function ($certificate) {
			if (!is_object($certificate)) {
				return $certificate;
			}

			$certificate->performance = $this->toFloat($certificate->performance);
			unset($certificate->result);

			return $certificate;
		}, $certificates);
	}

	private function normalizeResults($results)
	{
		if (!is_array($results)) {
			return $results;
		}

		return array_map(function ($result) {
			if (!is_object($result)) {
				return $result;
			}

			$result->eventId = $this->toInt($result->eventId);
			$result->distanceId = $this->toInt($result->distanceId);
			$result->id = $this->toInt($result->id);
			$result->raceId = $this->toInt($result->raceId);
			$result->position = $this->toInt($result->position);
			$result->courseTypeId = $this->toInt($result->courseTypeId);
			$result->performance = $this->toFloat($result->performance);
			$result->percentageGrading = $this->toFloat($result->percentageGrading);
			$result->percentageGradingBest = $this->toBool($result->percentageGradingBest);
			$result->isPersonalBest = $this->toBool($result->isPersonalBest);
			$result->isSeasonBest = $this->toBool($result->isSeasonBest);
			unset($result->time);
			unset($result->result);

			return $result;
		}, $results);
	}

	private function normalizeRankings($rankings)
	{
		if (!is_array($rankings)) {
			return $rankings;
		}

		return array_map(function ($ranking) {
			if (!is_object($ranking)) {
				return $ranking;
			}

			$ranking->rank = $this->toInt($ranking->rank);
			$ranking->runnerId = $this->toInt($ranking->runnerId);
			$ranking->distanceId = $this->toInt($ranking->distanceId);
			$ranking->performance = $this->toFloat($ranking->performance);
			unset($ranking->result);

			return $ranking;
		}, $rankings);
	}

	private function normalizeDistances($distances)
	{
		if (!is_array($distances)) {
			return $distances;
		}

		// Only include the fields the RunnerProfile UI uses and cast to correct types
		return array_map(function ($distance) {
			if (!is_object($distance)) {
				return $distance;
			}

			$normalized = new \stdClass();
			$normalized->id = $this->toInt($distance->id);
			$normalized->text = isset($distance->text) ? (string) $distance->text : '';
			$normalized->units = $this->toInt($distance->units);
			$normalized->miles = $this->toFloat($distance->miles);
			$normalized->resultUnitTypeName = isset($distance->resultUnitTypeName) ? (string) $distance->resultUnitTypeName : '';

			return $normalized;
		}, $distances);
	}

	private function getTopDistanceIds(array $results): array
	{
		$counts = array();

		foreach ($results as $result) {
			if (!isset($result->distanceId) || $result->distanceId === null || $result->distanceId == '0') {
				continue;
			}

			if (isset($result->performance) && (float) $result->performance <= 0) {
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

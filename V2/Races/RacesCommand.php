<?php

namespace IpswichJAFFARunningClubAPI\V2\Races;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DailyCache.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Results/ResultsCommand.php';
require_once 'RacesDataAccess.php';

use DateTime;
use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;
use IpswichJAFFARunningClubAPI\V2\Results\ResultsCommand as ResultsCommand;

class RacesCommand extends BaseCommand
{
	private $resultsCommand;

	public function __construct($db)
	{
		parent::__construct(new RacesDataAccess($db));

		$this->resultsCommand = new ResultsCommand($db);
	}

	public function saveRace($race)
	{
		return $this->dataAccess->insertRace($race);
	}

	public function getRaces(int $eventId, ?string $date)
	{
		return $this->dataAccess->getRaces($eventId, $date);
	}

	public function getRace(int $id)
	{
		return $this->dataAccess->getRace($id);
	}

	public function updateRace(int $raceId, string $field, string $value)
	{
		$response = $this->dataAccess->updateRace($raceId, $field, $value);

		if ($field == 'country_code' && $value != 'GB') {
			$this->dataAccess->updateRace($raceId, 'county', null);
			$response = $this->dataAccess->updateRace($raceId, 'area', null);
		}

		if ($field == "distance_id") {
			$results = $this->resultsCommand->getRaceResults($raceId);

			for ($i = 0; $i < count($results); $i++) {

				$this->resultsCommand->updateResult($results[$i]->id, 'result', $results[$i]->result);
				// TODO - add error handling
			}
		}

		return $response;
	}

	public function deleteRace(int $raceId)
	{
		return $this->dataAccess->deleteRace($raceId, false);
	}

	public function getLatestRacesDetails(?int $count)
	{
		return $this->dataAccess->getLatestRacesDetails($count ?? 10);
	}

	public function getHistoricRaces(?string $date)
	{
		$data = getDailyCache(
			'on-this-day-summary',
			function ($date) {
				$rawData = $this->getHistoricRacesData($date);

				foreach ($rawData as &$row) {
					// use the performance field (string like "3573.000")
					$row->performance = $this->secondsToTimeString($row->performance ?? 0);
				}

				$htmlSummary = $this->getAIGeneratedSummary($rawData);

				return $htmlSummary;
			},
			$date
		);

		return $data;
	}

	public function getHistoricRacesData(?string $date)
	{
		$results = $this->dataAccess->getAllHistoricRaces($date);

		if (empty($results)) {
			return [];
		} elseif (count($results) > 20) {
			$results = $this->dataAccess->getTopHistoricRaces($date);
		}

		return $results;
	}

	private function getAIGeneratedSummary($raceResults)
	{
		$api_key = OPEN_AI_API_SCERET__HISTORIC_RACE_RESULTS;

		$ch = curl_init('https://api.openai.com/v1/chat/completions');

		$instructionPath = __DIR__ . '/HistoricRaceSummaryPrompt.txt';
		$instruction = file_get_contents($instructionPath);

		if ($instruction === false) {
			return new WP_Error(
				'historic_race_prompt_read_error',
				'Unable to read the historic race summary prompt',
				['prompt_path' => $instructionPath]
			);
		}

		$resultsJson = json_encode(
			$raceResults,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		if ($resultsJson === false) {
			return new WP_Error(
				'open_ai_json_error',
				'Unable to encode race results as JSON'
			);
		}

		$requestBody = [
			'model' => 'gpt-6-luna',
			'messages' => [
				[
					'role' => 'system',
					'content' => $instruction
				],
				[
					'role' => 'user',
					'content' => "Here is the complete race-results dataset. Examine all of it before selecting the highlights:\n\n" . $resultsJson
				]
			]
		];

		$jsonBody = json_encode($requestBody);

		if ($jsonBody === false) {
			return new WP_Error(
				'open_ai_request_json_error',
				'Unable to encode OpenAI request'
			);
		}

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $jsonBody,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json',
				'Authorization: Bearer ' . $api_key
			],
			CURLOPT_TIMEOUT => 60,
			CURLOPT_CONNECTTIMEOUT => 10
		]);

		$response = curl_exec($ch);

		if ($response === false) {
			$curlError = curl_error($ch);

			curl_close($ch);

			return new WP_Error(
				'open_ai_curl_error',
				'OpenAI API request failed: ' . $curlError
			);
		}

		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		$decoded = json_decode($response, true);

		if ($httpcode < 200 || $httpcode >= 300) {
			return new WP_Error(
				'open_ai_api_error',
				'OpenAI API request failed',
				[
					'http_code' => $httpcode,
					'response' => $response
				]
			);
		}

		if (
			isset($decoded['choices'][0]['message']['content']) &&
			$decoded['choices'][0]['message']['content'] !== ''
		) {
			return [
				'success' => true,
				'content' => trim($decoded['choices'][0]['message']['content'])
			];
		}

		return [
			'success' => false,
			'error' => 'No response content found',
			'raw' => $decoded
		];
	}

	/**
	 * Convert seconds (float|string) to human-readable time.
	 * - Keeps one decimal place for fractional seconds if non-zero.
	 * - < 3600s → m:ss(.d)
	 * - >= 3600s → h:mm:ss(.d)
	 *
	 * Examples:
	 *   3573.000  → "59:33"
	 *   3573.40   → "59:33.4"
	 *   3723.25   → "1:02:03.3"
	 *
	 * @param float|string $seconds
	 * @return string
	 */
	function secondsToTimeString($seconds)
	{
		$sec = (float) $seconds;
		if (!is_finite($sec) || $sec < 0) return '';

		// Whole seconds + fractional remainder
		$whole = floor($sec);
		$frac = $sec - $whole;

		$h = intdiv($whole, 3600);
		$m = intdiv($whole % 3600, 60);
		$s = $whole % 60;

		// Format base string (no decimals yet)
		if ($h > 0) {
			$base = sprintf('%d:%02d:%02d', $h, $m, $s);
		} else {
			$base = sprintf('%d:%02d', $m, $s);
		}

		// Add decimal
		if ($frac >= 0.01) {
			$base .= sprintf('.%01d', (int) round($frac * 10));
		}

		return $base;
	}
}

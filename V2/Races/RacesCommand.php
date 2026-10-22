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

		$ch = curl_init('https://api.openai.com/v1/responses');

		$instruction = <<<PROMPT
Create a short, engaging "On This Day" historical race recap for
Ipswich JAFFA Running Club, based in Ipswich, Suffolk, UK.

The supplied JSON contains historical race results.

IMPORTANT:
- Only use facts contained in the supplied JSON.
- Do not invent results, times, locations, achievements, descriptions or context.
- Do not guess missing information.
- Always use the supplied runner names, event names, years, race IDs and runner IDs.
- Always include a race time whenever a runner is mentioned and their performance value is non-zero.
- The performance field is a number of seconds. Convert it to a normal race time such as 59:33 or 1:12:45.
- Do not change or estimate a runner's time.

CONTENT:
- Start with a short opening line such as:
  "On this day in JAFFA history…"
  or something similar.
- Then provide the highlights as compact <li> elements inside a <ul>.
- Include:
  - Top 3 finishers (position 1, 2 or 3)
  - Any non-empty info field
  - Any runner where isPercentageGradingBest = 1
  - Any runner where isPersonalBest = 1
- Group performances from the same eventName into a single <li> where appropriate.
- If the same event appears in multiple years, include the year next to the relevant event link.
- Mention notable away performances when the supplied data clearly shows that the race is outside East Anglia or overseas.
- Sort the highlights by significance, with wins, podiums, PBs, outstanding performances, long-distance events and international/away performances given priority.

STYLE:
- Warm, conversational and factual.
- Suitable for the Ipswich JAFFA Running Club website.
- Keep the recap concise — normally around 5–10 list items/lines.
- Do not add a separate heading unless it is necessary.
- Avoid exaggerated claims.
- A short club-related observation is fine when it is directly supported by the supplied data.

LINKS:
Runner links must use exactly:
<a href="/member-results/members-results/?runner_id={runnerId}">{runnerName}</a>

Event links must use exactly:
<a href="/member-results/race-results/?raceId={raceId}">{eventName}</a>

- Replace {runnerId}, {runnerName}, {raceId} and {eventName} with the supplied values.
- Escape HTML where necessary, but preserve the required links.
- Always mention the race year (YYYY).

OUTPUT:
Return ONLY the HTML.
The output must contain:
<ul>
    ...highlight <li> elements...
</ul>

Do not wrap the response in Markdown code fences.
Do not include explanations before or after the HTML.
PROMPT;

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
			'instructions' => $instruction,
			'input' => "JSON data:\n" . $resultsJson
		];

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode($requestBody),
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

		/*
     * Responses API returns the generated text in output.
     * output_text is also available in SDKs, but because this code
     * uses raw cURL we extract the text from the response structure.
     */
		$content = '';

		if (!empty($decoded['output']) && is_array($decoded['output'])) {
			foreach ($decoded['output'] as $outputItem) {
				if (
					isset($outputItem['type']) &&
					$outputItem['type'] === 'message' &&
					!empty($outputItem['content']) &&
					is_array($outputItem['content'])
				) {
					foreach ($outputItem['content'] as $contentItem) {
						if (
							isset($contentItem['type']) &&
							$contentItem['type'] === 'output_text' &&
							isset($contentItem['text'])
						) {
							$content .= $contentItem['text'];
						}
					}
				}
			}
		}

		if ($content !== '') {
			return [
				'success' => true,
				'content' => trim($content)
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

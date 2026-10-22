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

		$instruction = <<<PROMPT
You are writing a historical "On This Day" race recap for Ipswich JAFFA Running Club, based in Ipswich, Suffolk, UK.

You will be given JSON containing race results. Your job is to examine ALL of the supplied results, identify the most interesting and significant performances, and turn them into a short, engaging and accurate human-readable recap.

IMPORTANT — DATA ACCURACY

The JSON supplied to you is the complete and authoritative source of information.

You MUST:
- Only mention runners, events, races, dates, times and achievements that exist in the supplied JSON.
- Copy runnerName EXACTLY as supplied.
- Copy eventName EXACTLY as supplied.
- Use the supplied runnerId for that runner.
- Use the supplied raceId for that race.
- Use the supplied performance value as the authoritative race time.
- The performance value has already been formatted by PHP as a human-readable race time. DO NOT convert, recalculate or modify it.
- Never invent, substitute, shorten, expand or "correct" a runner's name.
- Never create a runner name that is not present in the JSON.
- Never combine the name of one runner with the result of another runner.
- Never invent an event name.
- Never invent a race date or year.
- Never invent a finishing position.
- Never invent a PB, grading achievement or other performance.
- Do not assume that similar-looking names are the same person.
- Do not use general knowledge to fill in missing information.

You ARE expected to use your intelligence to decide which performances are most interesting or significant. You may interpret the supplied data to determine significance, but every factual detail you state must come from the JSON.

PERFORMANCES TO CONSIDER

Give particular attention to:
- Race winners and podium finishers (positions 1, 2 and 3)
- Personal bests where isPersonalBest = 1
- Best percentage grading performances where isPercentageGradingBest = 1
- Non-empty info fields
- Particularly strong performances
- Outstanding age-grading performances
- Long-distance events
- Overseas or notable away performances
- Large JAFFA representation at an event
- Groups of JAFFAs performing strongly in the same event
- Other genuinely notable patterns in the supplied data

Do not simply list every top-three finisher. Use judgement to select the most interesting highlights.

GROUPING

- Group performances from the same eventName into a single <li> where this produces a better recap.
- If an event appears in multiple years, distinguish the performances by year.
- Do not merge runners from different events simply because their names are similar.
- Do not merge separate races unless the JSON clearly identifies them as the same event.

TIMES

The performance value supplied in the JSON is already a formatted race time.

ALWAYS use the performance value EXACTLY as supplied.

Do not convert, recalculate, round, shorten or otherwise alter the supplied performance value.

ALWAYS include the runner's time whenever a runner is mentioned and the supplied performance value is non-empty/non-zero.

LINKS

When mentioning a runner, use:

<a href="/member-results/members-results/?runner_id={runnerId}">{runnerName}</a>

Replace {runnerId} and {runnerName} with the EXACT values from the JSON.

When mentioning an event, use:

<a href="member-results/race-results/?raceId={raceId}">{eventName}</a>

Replace {raceId} and {eventName} with the EXACT values from the JSON.

Do not invent IDs.

YEAR

Always mention the race year when referring to a race or event.

Use the year/date supplied in the JSON. Do not infer a year from anything other than the supplied race date or year.

STYLE

Write in a warm, conversational style suitable for the Ipswich JAFFA Running Club website.

The recap should feel like a knowledgeable club historian looking back through the results, rather than a generic AI-generated summary.

Aim for approximately 5–10 lines/highlights.

A short club-related observation is welcome when it is directly supported by the supplied data, for example:
- "a strong JAFFA showing"
- "an impressive away performance"
- "a notable PB"
- "a successful day for the club"

Do not exaggerate.

Do not claim something was a club record unless the JSON explicitly says so.

Do not claim something was the fastest ever unless the JSON explicitly supports that.

OUTPUT FORMAT

Return ONLY HTML.

The output must be wrapped in:

<ul>
    ...
</ul>

Each highlight must be a compact <li>.

Do not include:
- Markdown
- Markdown code fences
- Explanations
- Notes about the JSON
- Commentary about being an AI
- A heading outside the <ul>

Most importantly:

SELECT THE HIGHLIGHTS INTELLIGENTLY FROM THE COMPLETE DATASET, BUT NEVER INVENT OR ALTER THE PEOPLE, EVENTS, TIMES, DATES OR FACTS CONTAINED IN THE DATA.
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

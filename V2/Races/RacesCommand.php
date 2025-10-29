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
		 $data = getDailyCache('on-this-day-summary', function ($date) {
        	$rawData = $this->getHistoricRacesData($date);

			foreach ($rawData as &$row) {
			    // use the performance field (string like "3573.000")
			    $row['performance'] = $this->secondsToTimeString($row['performance'] ?? 0);
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

		$instruction = "Summarize the races in the provided JSON data as a short, engaging “On This Day” recap (5–10 lines) for Ipswich JAFFA Running Club, based in Ipswich, Suffolk (UK).
			Include:	
				- A short opening line such as `On this day in JAFFA history…` or similar.
				- Then list each highlight as a compact <li> inside a <ul>, focusing on:
				- - Top 3 finishers (position = 1, 2, or 3),
				- - Any info field that is non-empty,
				- - Any runner with isPercentageGradingBest or isPersonalBest = 1.

			Context & grouping:
				- Group performances from the same eventName into a single <li>.
				- If the same event appears in multiple years, include years in parentheses next to the event link.
				- Note when a race is outside East Anglia or overseas, highlighting it as a notable away performance.

			Output style:
				- Each <li> should be compact, factual, and warm in tone, optionally adding a short club-related insight ('a big JAFFA showing in Essex', 'impressive PB race', etc.).
				- Convert runner names and event names into hyperlinks:
				- - Runner: <a href=\"/member-results/members-results/?runner_id={runnerId}\">{runnerName}</a>
				- - Event: <a href=\"member-results/race-results/?raceId={raceId}\">{eventName}</a>
				- Mention the race year (YYYY).
				- Always include the runner’s time from the `performance` field when non-zero
  				- - Append it naturally in the sentence (e.g., 'in 59:33' or 'clocking 1:12:45'), even if not a top 3 finish.
				- Sort items by significance (wins, medals, PBs, long-distance or international events first).
				
			Wrap everything in:	
			<ul>
			  ...list items here...
			</ul>

			Always include a race time for every runner mentioned. Do not omit times, even for personal bests or awards.
			";

		$resultsJson = json_encode($raceResults);
	
		$requestBody = [
		    'model' => 'gpt-4o-mini', // better than 3.5-turbo for summarization
		    'messages' => [
		        [
		            'role' => 'user',
		            'content' => $instruction . "\n\nJSON data:\n" . $resultsJson
		        ]
		    ],
		    'temperature' => 0.4,
		];

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $api_key
		]);

		$response = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpcode !== 200) {
	        return new WP_Error('open_ai_api_error', 'OpenAI API request failed', ['response' => $response]);
	    }

	    $decoded = json_decode($response, true);
	
	    // Return just the assistant’s message (the useful part)
	    if (isset($decoded['choices'][0]['message']['content'])) {
	        return [
	            'success' => true,
	            'content' => $decoded['choices'][0]['message']['content']
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
	function secondsToTimeString($seconds) {
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

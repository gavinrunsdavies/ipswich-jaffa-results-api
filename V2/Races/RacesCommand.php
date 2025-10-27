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
			$htmlSummary = $this->GetAIGeneratedSummary($rawData);

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

	private function GetAIGeneratedSummary($raceResults)
	{
		$api_key = OPEN_AI_API_SCERET__HISTORIC_RACE_RESULTS;

		$ch = curl_init('https://api.openai.com/v1/chat/completions');

		$instruction = "Summarize the races in the provided JSON data as a short, engaging 'On This Day' recap of 5-10 lines. 
			
			Include:
			- A fun one-sentence intro such as 'On this day in JAFFA history...' or a similar line, or fun fact at the end.
			- Then list each highlight as a compact HTML list item (<li>), focusing on:
			  - Top 3 finishers (`position` = 1, 2, or 3),
			  - Any `info` field that is non-empty,
			  - Any runner with `isPercentageGradingBest` or `isPersonalBest` = 1.
			
			For each mention:
			- Convert the runner's name to an HTML hyperlink using their ID: `<a href=\"/member-results/members-results/?runner_id={runnerId}\">{runnerName}</a>`.
			- Convert the event name to an HTML hyperlink using its ID: `<a href=\"member-results/race-results/?raceId={raceId}\">{eventName}</a>`.
			- Include the year of the race (from the `year` field, e.g., '(2023)').
			- For top 3 finishes, convert the `performance` field from seconds to time format:
			  - Use `m:ss` if under 1 hour, or `h:mm:ss` if 1 hour or more.
			  - Append the time after their placing, e.g., '1st in 17:04' or '2nd in 1:12:45'.
			- Group results and summaries of a similar race (eventName) and just add the link to the year when differnt.
			
			Sort the items so the most significant performances appear first (e.g. representing GB/England, or a race win).
			
			Wrap everything in:
			```html
			<div class=\"race-summary\">
			  <h3>🏁 On This Day in JAFFA History</h3>
			  <ul>
			    ...list items here...
			  </ul>
			</div>
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
		    'temperature' => 0.9,
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
}

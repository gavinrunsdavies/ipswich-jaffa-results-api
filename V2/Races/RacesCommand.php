<?php

namespace IpswichJAFFARunningClubAPI\V2\Races;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
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

	public function getHistoricRaces()
	{
		return $this->dataAccess->getHistoricRaces();
	}
}

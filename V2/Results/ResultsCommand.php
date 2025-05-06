<?php

namespace IpswichJAFFARunningClubAPI\V2\Results;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Badges/Badges.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Constants/Rules.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/CourseTypes/CourseTypes.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Distances/Distances.php';
require_once 'ResultsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\Badges\Badges as Badges;
use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;
use IpswichJAFFARunningClubAPI\V2\Constants\Rules as Rules;
use IpswichJAFFARunningClubAPI\V2\CourseTypes\CourseTypes as CourseTypes;
use IpswichJAFFARunningClubAPI\V2\Distances\Distances as Distances;

class ResultsCommand extends BaseCommand
{
	public function __construct($db)
	{
		parent::__construct(new ResultsDataAccess($db));
	}

	public function getResults(int $eventId = null, string $fromDate = null, string $toDate = null, int $numberOfResults = null)
	{
		return $this->dataAccess->getResults($eventId, $fromDate, $toDate, $numberOfResults);
	}

	public function deleteResult(int $resultId)
	{
		return $this->dataAccess->deleteResult($resultId);
	}

	public function getRaceResults(int $raceId)
	{
		$response = $this->dataAccess->getRaceResults($raceId);

		$pbRunners = array();
		foreach ($response as $result) {
			if (!in_array($result->runnerId, $pbRunners)) {
				$pbRunners[] = $result->runnerId;
			}
		}

		if (!empty($pbRunners)) {
			$runnerIds = implode(", ", $pbRunners);

			$previousPersonalBestResults = $this->dataAccess->getPreviousPersonalBest($runnerIds, $raceId);

			foreach ($response as $result) {
				foreach ($previousPersonalBestResults as $previousBestResult) {
					if ($result->runnerId == $previousBestResult->runnerId) {
						$result->previousPersonalBestResult = $previousBestResult->previousBest;
						$result->previousPersonalBestPerformance = $previousBestResult->previousBestPerformance;
						break;
					}
				}
			}
		}

		return $response;
	}

	public function getCountyChampions()
	{
		return $this->dataAccess->getCountyChampions();
	}

	public function insertResult($resultRequest)
	{
		$isPersonalBest = false;
		$isSeasonBest = false;
		$standardTypeId = 0;
		$ageGrading = 0;
		$ageGrading2015 = 0;

		$categoryId = $this->dataAccess->getCategoryId($resultRequest['runnerId'], $resultRequest['date']);
		$race = $this->dataAccess->getRace($resultRequest['raceId']);
		$performance = $this->calculateSecondsFromTime($resultRequest['result']);

		if ($this->isCertificatedCourseAndResult($race, $performance)) {
			$isPersonalBest = $this->dataAccess->isPersonalBest($resultRequest['raceId'], $resultRequest['runnerId'], $performance);

			$isSeasonBest = $this->dataAccess->isSeasonBest($resultRequest['raceId'], $resultRequest['runnerId'], $performance, $resultRequest['date']);

			$ageGrading = $this->dataAccess->getAgeGrading($performance, $resultRequest['runnerId'], $resultRequest['raceId']);

			if ($resultRequest['date'] >= Rules::START_OF_2015_AGE_GRADING) {
				$ageGrading2015 = $this->dataAccess->get2015FactorsAgeGrading($performance, $resultRequest['runnerId'], $resultRequest['raceId']);
			}

			if ($ageGrading2015) {
				$standardTypeId = $this->dataAccess->getResultStandardTypeId($categoryId, $resultRequest['result'], $resultRequest['raceId'], $ageGrading2015, $resultRequest['date']);
			}
		}

		$resultId = $this->dataAccess->insertResult($resultRequest, $performance, $categoryId, $isPersonalBest, $isSeasonBest, $standardTypeId, $ageGrading, $ageGrading2015);

		if (is_wp_error($resultId)) {
			return $resultId;
		}

		if ($ageGrading > 0) {
			// TODO check response for number of results
			$this->dataAccess->updatePercentageGradingPersonalBest($resultId, $resultRequest['runnerId'], $resultRequest['date']);

			$isNewStandard = $this->dataAccess->isNewStandard($resultId);

			if ($isNewStandard) {
				$this->dataAccess->saveStandardCertificate($resultId);
			}
		}

		// If a PB query to see if we need to re-evaluate later PB
		if ($isPersonalBest) {
			$this->dataAccess->checkAndUpdatePersonalBestResults($resultRequest['runnerId']);
		}

        $this->updateBadges($race, $resultRequest['runnerId']);

		return $this->dataAccess->getResult($resultId);
	}

	public function updateResult(int $resultId, string $field, string $value)
	{
		// TODO Changing raceId could mean new results generation for PBs etc
		if ($field == 'info' || $field == 'position' || $field == "scoring_team" || $field == 'race_id') {
			return $this->dataAccess->updateResult($resultId, $field, $value);
		} else if ($field == 'county_champion') {
			// TODO
			// Check if exists - insert or update or delete
		} else if ($field == 'result') {
			// Get old, delete and insert. Reuses exitsing logic
			$existingResult = $this->dataAccess->getResult($resultId);

			if (is_wp_error($existingResult)) {
				return $existingResult;
			}

			$this->dataAccess->deleteResult($resultId);

			$resultRequest = array(
				'runnerId' => $existingResult->runnerId,
				'date' => $existingResult->date,
				'result' => $value,
				'raceId' => $existingResult->raceId,
				'position' => $existingResult->position,
				'info' => $existingResult->info,
				'team' => $existingResult->team
			);
            
			return $this->insertResult($resultRequest);
		}
	}

    public function updateBadges(int $runnerId, array $badges)
	{
		$this->dataAccess->addRunnerBadges($runnerId, $badges);   
	}

    private function updateBadges($race, int $runnerId)
    {
        $badges = [];
        if ((int)$race->courseTypeId === CourseTypes::TRACK) {
            $badges[] = Badges::TRACK;
        } else if ((int)$race->courseTypeId === CourseTypes::CROSS_COUNTRY) {
            $badges[] = Badges::CROSS_COUNTRY;
        }
        if ((int)$race->distanceId === Distances::MARATHON) {
            $badges[] = Badges::MARATHON;
        }
        if ($race->countryCode !== "GB") {
            $badges[] = Badges::INTERNATIONAL;
        }

        if (empty($badges)) {
            return;
        }
        
        $this->dataAccess->addRunnerBadges($runnerId, $badges);        
    }

	private function isCertificatedCourseAndResult($race, float $performance): bool
	{
		if (!isset($performance)) {
			return false;
		}

		return $race && $race->distance != null && in_array($race->courseTypeId, array(CourseTypes::ROAD, CourseTypes::TRACK, CourseTypes::INDOOR));
	}

	private function calculateSecondsFromTime(string $result): float
	{
		$seconds = 0;
		if (!empty($result)) {

			$timeExploded = explode(':', $result);

			if (isset($timeExploded[2])) {
				// hh:mm:ss.mmmm
				$seconds = $timeExploded[0] * 3600 + $timeExploded[1] * 60 + $timeExploded[2];
			} elseif (isset($timeExploded[1])) {
				// mm:ss.mmmm
				$seconds = $timeExploded[0] * 60 + $timeExploded[1];
			} elseif (isset($timeExploded[0])) {
				// ss.mmmm
				$seconds = $timeExploded[0];
			}
		}

		return $seconds;
	}
}

<?php

namespace IpswichJAFFARunningClubAPI\V2\Results;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/Constants/Rules.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/CourseTypes/CourseTypes.php';
require_once 'ResultsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;
use IpswichJAFFARunningClubAPI\V2\Constants\Rules as Rules;
use IpswichJAFFARunningClubAPI\V2\CourseTypes\CourseTypes as CourseTypes;

class ResultsCommand extends BaseCommand
{
	public function __construct($db)
	{
		parent::__construct(new ResultsDataAccess($db));
	}

	public function getResults(\WP_REST_Request $request)
	{
		// TODO, eventID, fromDate, toDate and limit. All optional.
		// Sanitization needed before
		$parameters = $request->get_query_params();
		$response = $this->dataAccess->getResults($parameters['eventId'], $parameters['fromDate'], $parameters['toDate'], $parameters['numberOfResults']);

		return rest_ensure_response($response);
	}

	public function saveResult(\WP_REST_Request $request)
	{
		$response = $this->insertResult($request['result']);

		return rest_ensure_response($response);
	}

	public function deleteResult(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->deleteResult($request['resultId'], false);

		return rest_ensure_response($response);
	}

	public function updateResult(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->updateResult($request['resultId'], $request['field'], $request['value']);

		return rest_ensure_response($response);
	}

	public function getRaceResults(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getRaceResults($request['raceId']);

		$pbRunners = array();
		foreach ($response as $result) {
			if (!in_array($result->runnerId, $pbRunners)) {
				$pbRunners[] = $result->runnerId;
			}
		}

		$runnerIds = implode(", ", $pbRunners);

		$previousPersonalBestResults = $this->dataAccess->getPreviousPersonalBest($runnerIds, $request['raceId']);

		foreach ($response as $result) {
			foreach ($previousPersonalBestResults as $previousBestResult) {
				if ($result->runnerId == $previousBestResult->runnerId) {
					$result->previousPersonalBestResult = $previousBestResult->previousBest;
					break;
				}
			}
		}

		return rest_ensure_response($response);
	}

	public function getCountyChampions(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getCountyChampions();

		return rest_ensure_response($response);
	}

	public function insertResult($resultRequest)
    {       
        $isPersonalBest = false;
        $isSeasonBest = false;
        $standardTypeId = 0;
        $ageGrading = 0;
        $ageGrading2015 = 0;  

		$categoryId = $this->dataAccess->getCategoryId($resultRequest['runnerId'], $resultRequest['date']);

		$performance = $this->calculateSecondsFromTime($resultRequest['result']);

        if ($this->isCertificatedCourseAndResult($resultRequest['raceId'], $performance)) {
            $isPersonalBest = $this->dataAccess->isPersonalBest($resultRequest['raceId'], $resultRequest['runnerId'], $performance, $resultRequest['date']);

            $isSeasonBest = $this->dataAccess->isSeasonBest($resultRequest['raceId'], $resultRequest['runnerId'], $performance, $resultRequest['date']);

            $ageGrading = $this->dataAccess->getAgeGrading($resultRequest['result'], $resultRequest['runnerId'], $resultRequest['raceId']);

            if ($resultRequest['date'] >= Rules::START_OF_2015_AGE_GRADING) {
                $ageGrading2015 = $this->dataAccess->get2015FactorsAgeGrading($performance, $resultRequest['runnerId'], $resultRequest['raceId']);
            }

            $standardTypeId = $this->dataAccess->getResultStandardTypeId($categoryId, $resultRequest['result'], $resultRequest['raceId'], $ageGrading2015, $resultRequest['date']);
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

        return $this->dataAccess->getResult($resultId);
    }

	private function isCertificatedCourseAndResult(int $raceId, float $performance) : bool
    {
        if (!isset($performance)) {
            return false;
        }

        $race = $this->dataAccess->getRace($raceId);

        return $race && $race->distance != null && in_array($race->courseTypeId, array(CourseTypes::ROAD, CourseTypes::TRACK, CourseTypes::INDOOR));
    }

	private function calculateSecondsFromTime(string $result) : float
	{
		$seconds = 0;
		if (!empty($result)) {

			$timeExploded = explode(':', $result);

			if (isset($timeExploded[2])) {
				// hh:mm:ss.mmmm
				$seconds = $timeExploded[0] * 3600 + $timeExploded[1] * 60 + $timeExploded[2];
			}

			elseif (isset($timeExploded[1])) {
				// mm:ss.mmmm
				$seconds = $timeExploded[0] * 60 + $timeExploded[1];
			}
			
			elseif (isset($timeExploded[0])) {
				// ss.mmmm
				$seconds = $timeExploded[0];
			}
		}

		return $seconds;
	}
}

<?php

namespace IpswichJAFFARunningClubAPI\V2\Statistics;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once 'StatisticsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;

class StatisticsCommand extends BaseCommand
{
	public function __construct($db)
	{
		parent::__construct(new StatisticsDataAccess($db));
	}

	public function getResultCountByCategoryAndCourse(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getResultCountByCategoryAndCourse();
		
		return $this->processDataResponse(
			$response,
			function ($response) {
				$courseTypeName = "courseTypes";
				$groupedResults = array();

				foreach ($response as $item) {
					$courseName = $item->courseType ?? "Undefined";
					$categoryCode = $item->code;
					if (!array_key_exists($categoryCode, $groupedResults)) {	
						$groupedResults[$categoryCode] = array("name" => $categoryCode, $courseTypeName => array());
					}
					
					$groupedResults[$categoryCode][$courseTypeName][] = array("name" => $courseName, "count" => $item->count);
				}

				return array_values($groupedResults);
			}
		);
	}

	public function getMeanPercentageGradingByMonth(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getMeanPercentageGradingByMonth();

		return $this->processDataResponse(
			$response,
			function ($response) {
				$gradingsByCategory = array();

				foreach ($response as $item) {
					$categoryCode = $item->categoryCode;
					if (!array_key_exists($categoryCode, $gradingsByCategory)) {
						$gradingsByCategory[$categoryCode] = array();
					}
					$gradingsByCategory[$categoryCode][] = array("date" => $item->date, "meanGrading" => $item->meanGrading);
				}

				return $gradingsByCategory;
			}
		);
	}

	public function getResultCountByRunnerByYear(\WP_REST_Request $request)
	{
		$parameters = $request->get_query_params();
		$limit = $parameters['limit'] ?? 50;
		$response = $this->dataAccess->getResultCountByRunnerByYear($request['year'], $limit);
		return $this->processDataResponse($response, function ($response) {
			return $response;
		});
	}

	public function getClubResults(\WP_REST_Request $request)
	{
		$parameters = $request->get_query_params();
		$limit = $parameters['limit'] ?? 5000;
		$response = $this->dataAccess->getClubResultsCount($request['year'], $limit);
		return $this->processDataResponse($response, function ($response) {
			return $response;
		});
	}

	public function getGroupedRunnerResultsCount(\WP_REST_Request $request)
	{
		$parameters = $request->get_query_params();
		$groupSize = $parameters['groupSize'] ?? 50;
		$minimumResultCount = $parameters['minimumResultCount'] ?? 0;
		$response = $this->dataAccess->getGroupedRunnerResultsCount($groupSize, $minimumResultCount);
		return $this->processDataResponse($response, function ($response) {
			return $response;
		});
	}

	public function getStatistics(\WP_REST_Request $request)
	{
		switch ($request['typeId']) {
			case 1:
				$response = $this->dataAccess->getResultsByYearAndCounty();
				break;
			case 2:
				$response = $this->dataAccess->getResultsByYearAndCountry();
				break;
			case 3:
				$response = $this->dataAccess->getResultsCountByYear();
				break;
			case 4:
				$response = $this->dataAccess->getPersonalBestTotals();
				break;
			case 5:
				$response = $this->dataAccess->getPersonalBestTotalByYear();
				break;
			case 6:
				$response = $this->dataAccess->getTopAttendedRaces();
				break;
			case 7:
				$response = $this->dataAccess->getTopMembersRacing();
				break;
			case 8:
				$response = $this->dataAccess->getTopMembersRacingByYear();
				break;
			default:
				break;
		}

		return $this->processDataResponse($response, function ($response) {
			return $response;
		});
	}
}

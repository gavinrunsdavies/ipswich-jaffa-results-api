<?php

namespace IpswichJAFFARunningClubAPI\V2\RunnerResults;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once 'RunnerResultsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;

class RunnerResultsCommand extends BaseCommand
{
	public function __construct($db)
	{
		parent::__construct(new RunnerResultsDataAccess($db));
	}

	public function getMemberResults( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getMemberResults($request['runnerId']);

		return rest_ensure_response( $response );
	}
	
	public function getMemberPBResults( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getMemberPBResults($request['runnerId']);

		return rest_ensure_response( $response );
	}
	
	public function compareMemberRaces( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getHeadToHeadResults($request['runnerIds']);

		return rest_ensure_response( $response );
	}

	public function getMemberInsightsRaceDistance( \WP_REST_Request $request ) {
		$raceTimes = $this->dataAccess->getMemberInsightsRaceDistance($request['distanceId']);

		$memberTotals = $this->dataAccess->getRunnerDistanceResultMinMaxAverage($request['runnerId'], $request['distanceId']);

		$response = array();
		$response['raceTimes'] = $raceTimes;
		$response['slowest'] = $memberTotals->slowest;
		$response['fastest'] = $memberTotals->fastest;
		$response['mean'] = $memberTotals->mean;
		return rest_ensure_response( $response );
	}

	public function getMemberInsightsNumberOfRaces( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getMemberInsightsNumberOfRaces($request['runnerId']);

		return rest_ensure_response( $response );
	}
							
	public function getMemberInsightsTotalRaceTime( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getMemberInsightsTotalRaceTime($request['runnerId']);

		return rest_ensure_response( $response );
	}
}

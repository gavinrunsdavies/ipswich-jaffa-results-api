<?php

namespace IpswichJAFFARunningClubAPI\V2\Rankings;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once 'RankingsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;

class RankingsCommand extends BaseCommand
{
	public function __construct($db)
	{
		parent::__construct(new RankingsDataAccess($db));
	}

	public function getResultRankings( \WP_REST_Request $request ) {
		$parameters = $request->get_query_params();			
		$response = $this->dataAccess->getResultRankings($request['distanceId'], $parameters['year'], $parameters['sexId'], $parameters['categoryId']);

		return rest_ensure_response( $response );
	}
	
	public function getWMAPercentageRankings( \WP_REST_Request $request ) {
		$parameters = $request->get_query_params();			
		$response = $this->dataAccess->getWMAPercentageRankings($parameters['sexId'], $parameters['distanceId'], $parameters['year'], $parameters['distinct']);

		return rest_ensure_response( $response );
	}
	
	public function getAveragePercentageRankings( \WP_REST_Request $request ) {
		$parameters = $request->get_query_params();			
		$response = $this->dataAccess->getAveragePercentageRankings($parameters['sexId'], $parameters['year'], $parameters['numberOfRaces']);

		return rest_ensure_response( $response );
	}
}

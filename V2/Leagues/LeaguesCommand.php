<?php

namespace IpswichJAFFARunningClubAPI\V2\Leagues;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once 'LeaguesDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;

class LeaguesCommand extends BaseCommand
{
	public function __construct($db)
	{
		parent::__construct(new LeaguesDataAccess($db));
	}

	public function getLeagues(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getLeagues();

		return rest_ensure_response($response);
	}

	public function getLeague(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->getLeague($request['leagueId']);

		return rest_ensure_response($response);
	}

	public function saveLeague(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->insertLeague($request['league']);

		return rest_ensure_response($response);
	}

	public function updateLeague(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->updateLeague($request['leagueId'], $request['field'], $request['value']);

		return rest_ensure_response($response);
	}

	public function deleteLeague(\WP_REST_Request $request)
	{
		$parameters = $request->get_query_params();

		$response = $this->dataAccess->deleteLeague($request['leagueId'], $parameters['deleteRaceAssociations']);

		return rest_ensure_response($response);
	}
}

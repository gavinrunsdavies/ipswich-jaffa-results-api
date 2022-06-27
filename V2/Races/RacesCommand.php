<?php

namespace IpswichJAFFARunningClubAPI\V2\Races;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once 'RacesDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;

class RacesCommand extends BaseCommand
{
	public function __construct($db)
	{
		parent::__construct(new RacesDataAccess($db));
	}

	public function saveRace(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->insertRace($request['race']);

		return rest_ensure_response($response);
	}

	public function getRaces(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getRaces($request['eventId']);

		return rest_ensure_response($response);
	}

	public function getRace(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->getRace($request['id']);

		return rest_ensure_response($response);
	}

	public function updateRace(\WP_REST_Request $request)
	{

		if ($request['field'] == "distance_id") {
			$response = $this->dataAccess->updateRaceDistance($request['id'], $request['value']);
		} else {
			$response = $this->dataAccess->updateRace($request['id'], $request['field'], $request['value']);
		}

		return rest_ensure_response($response);
	}

	public function deleteRace(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->deleteRace($request['raceId'], false);

		return rest_ensure_response($response);
	}
}

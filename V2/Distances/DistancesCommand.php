<?php

namespace IpswichJAFFARunningClubAPI\V2\Distances;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once 'DistancesDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;

class DistancesCommand extends BaseCommand
{
	public function __construct($db)
	{
		parent::__construct(new DistancesDataAccess($db));
	}

	public function getDistances(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getDistances();

		return rest_ensure_response($response);
	}
}

<?php

namespace IpswichJAFFARunningClubAPI\V2\Genders;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once 'GendersDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;

class GendersCommand extends BaseCommand
{
	public function __construct($db)
	{
		parent::__construct(new GendersDataAccess($db));
	}

	public function getGenders(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getGenders();

		return rest_ensure_response($response);
	}
}

<?php

namespace IpswichJAFFARunningClubAPI\V2\Categories;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once 'CategoriesDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;

class CategoriesCommand extends BaseCommand
{
	public function __construct($db)
	{
		parent::__construct(new CategoriesDataAccess($db));
	}

	public function getCategories(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getCategories();

		return rest_ensure_response($response);
	}
}

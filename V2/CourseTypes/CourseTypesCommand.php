<?php

namespace IpswichJAFFARunningClubAPI\V2\CourseTypes;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once 'CourseTypesDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;

class CourseTypesCommand extends BaseCommand
{
	public function __construct($db)
	{
		parent::__construct(new CourseTypesDataAccess($db));
	}

	public function getCourseTypes(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getCourseTypes();

		return rest_ensure_response($response);
	}
}

<?php

namespace IpswichJAFFARunningClubAPI\V4\CourseTypes;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/IRoute.php';
require_once 'CourseTypesDataAccess.php';

use IpswichJAFFARunningClubAPI\V4\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V4\IRoute as IRoute;

class CourseTypesController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new CourseTypesDataAccess($db));
	}

	public function registerRoutes()
	{
		register_rest_route($this->route, '/coursetypes', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getCourseTypes')
		));
	}

	public function getCourseTypes(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getCourseTypes();

		return rest_ensure_response($response);
	}
}

<?php

namespace IpswichJAFFARunningClubAPI\V4\Categories;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/IRoute.php';
require_once 'CategoriesDataAccess.php';

use IpswichJAFFARunningClubAPI\V4\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V4\IRoute as IRoute;

class CategoriesController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new CategoriesDataAccess($db));
	}

	public function registerRoutes()
	{
		register_rest_route($this->route, '/categories', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'getCategories')
		));
	}

	public function getCategories(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getCategories();

		return rest_ensure_response($response);
	}
}

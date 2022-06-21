<?php

namespace IpswichJAFFARunningClubAPI\V4\Genders;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/IRoute.php';
require_once 'GendersDataAccess.php';

use IpswichJAFFARunningClubAPI\V4\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V4\IRoute as IRoute;

class GendersController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new GendersDataAccess($db));
	}

	public function registerRoutes()
	{
		register_rest_route($this->route, '/genders', array(
			'methods' => \WP_REST_Server::READABLE,
			'permission_callback' => array($this, 'isAuthorized'),
			'callback' => array($this, 'getGenders')
		));
	}

	public function getGenders(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getGenders();

		return rest_ensure_response($response);
	}
}

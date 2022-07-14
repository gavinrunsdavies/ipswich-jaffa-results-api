<?php

namespace IpswichJAFFARunningClubAPI\V2\Distances;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'DistancesCommand.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class DistancesController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new DistancesCommand($db));
	}

	public function registerRoutes()
	{
		register_rest_route($this->route, '/distances', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getDistances')
		));
	}
}

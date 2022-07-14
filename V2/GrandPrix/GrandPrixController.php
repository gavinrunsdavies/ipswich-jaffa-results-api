<?php

namespace IpswichJAFFARunningClubAPI\V2\GrandPrix;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'GrandPrixCommand.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class GrandPrixController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new GrandPrixCommand($db));
	}

	public function registerRoutes()
	{
		register_rest_route($this->route, '/results/grandPrix/(?P<year>[\d]{4})/(?P<sexId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this->command, 'getGrandPrixPoints'),
			'args'                => array(
				'sexId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'isValidId')
				),
				'year'           => array(
					'required'          => true
				)
			)
		));
	}
}

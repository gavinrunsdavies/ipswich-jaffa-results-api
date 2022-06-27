<?php
namespace IpswichJAFFARunningClubAPI\V2\TeamResults;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH .'V2/BaseCommand.php';
require_once 'TeamResultsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;
	
class TeamResultsCommand extends BaseCommand
{			
	public function __construct($db) 
	{        
		parent::__construct(new TeamResultsDataAccess($db));
	}
	
	public function getTeamResult( \WP_REST_Request $request ) {
	
		return $this->dataAccess->getTeamResult($request['teamResultId']);
	}

	public function saveTeamResult( \WP_REST_Request $request ) {

		return $this->dataAccess->insertTeamResult($request);		
	}

	public function deleteTeamResult( \WP_REST_Request $request ) {
		
		return $this->dataAccess->deleteTeamResult($request['teamResultId']);
	}
}

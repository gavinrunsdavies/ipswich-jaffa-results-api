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
	
	public function getTeamResult(int $teamResultId) {
	
		return $this->dataAccess->getTeamResult($teamResultId);
	}

	public function saveTeamResult(string $name, string $category, string $result, int $position, int $meetingId, $resultIds) {

		return $this->dataAccess->insertTeamResult($name, $category, $result, $position, $meetingId, $resultIds);		
	}

	public function deleteTeamResult(int $teamResultId) {
		
		return $this->dataAccess->deleteTeamResult($teamResultId);
	}
}

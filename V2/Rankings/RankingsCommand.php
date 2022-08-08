<?php

namespace IpswichJAFFARunningClubAPI\V2\Rankings;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once 'RankingsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;

class RankingsCommand extends BaseCommand
{
	public function __construct($db)
	{
		parent::__construct(new RankingsDataAccess($db));
	}

	public function getResultRankings( int $distanceId, ?int $year = 0, ?int $sexId = 0, ?int $categoryId = 0) {	
		return $this->dataAccess->getResultRankings($distanceId, $year, $sexId, $categoryId);
	}
	
	public function getWMAPercentageRankings(?int $sexId = 0, ?int $distanceId = 0, ?int $year = 0, ?bool $distinct = false) {		
		return $this->dataAccess->getWMAPercentageRankings($sexId, $distanceId, $year, $distinct);
	}
	
	public function getAveragePercentageRankings(?int $sexId = 2, ?int $year = 0, ?int $numberOfRaces = 5, ?int $numberOfResults = 200) {
		return $this->dataAccess->getAveragePercentageRankings($sexId, $year, $numberOfRaces, $numberOfResults);
	}
}

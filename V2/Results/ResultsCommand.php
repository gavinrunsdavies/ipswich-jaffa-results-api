<?php

namespace IpswichJAFFARunningClubAPI\V2\Results;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseCommand.php';
require_once 'ResultsDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseCommand as BaseCommand;

class ResultsCommand extends BaseCommand
{
	public function __construct($db)
	{
		parent::__construct(new ResultsDataAccess($db));
	}

	public function getResults(\WP_REST_Request $request)
	{
		// TODO, eventID, fromDate, toDate and limit. All optional.
		// Sanitization needed before
		$parameters = $request->get_query_params();
		$response = $this->dataAccess->getResults($parameters['eventId'], $parameters['fromDate'], $parameters['toDate'], $parameters['numberOfResults']);

		return rest_ensure_response($response);
	}

	public function saveResult(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->insertResult($request['result']);

		return rest_ensure_response($response);
	}

	public function deleteResult(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->deleteResult($request['resultId'], false);

		return rest_ensure_response($response);
	}

	public function updateResult(\WP_REST_Request $request)
	{

		$response = $this->dataAccess->updateResult($request['resultId'], $request['field'], $request['value']);

		return rest_ensure_response($response);
	}

	public function getRaceResults( \WP_REST_Request $request ) {
		$response = $this->dataAccess->getRaceResults($request['raceId']);
      
		$pbRunners = array();
		foreach ($response as $result) {
		  if (!in_array($result->runnerId, $pbRunners)) {  
			$pbRunners[] = $result->runnerId;
		  }
		}   
		
		$runnerIds = implode (", ", $pbRunners);
		
		$previousPersonalBestResults = $this->dataAccess->getPreviousPersonalBest($runnerIds, $request['raceId']);
		
		foreach ($response as $result) {
		  foreach ($previousPersonalBestResults as $previousBestResult) {
			if ($result->runnerId == $previousBestResult->runnerId) {
			  $result->previousPersonalBestResult = $previousBestResult->previousBest;
			  break;
			}
		  }          
		}
		
		return rest_ensure_response( $response );
	}	
	
  	public function getCountyChampions( \WP_REST_Request $request ) {
		    $response = $this->dataAccess->getCountyChampions();

			return rest_ensure_response( $response );
	}	
}

<?php
namespace IpswichJAFFARunningClubAPI\V2;

require_once plugin_dir_path( __FILE__ ) .'BaseController.php';
require_once plugin_dir_path( __FILE__ ) .'IRoute.php';
require_once plugin_dir_path( __FILE__ ) .'config.php';
	
class RunnerOfTheMonthController extends BaseController implements IRoute {			
	
	public function __construct($namespace) {        
		parent::__construct($namespace);
	}
	
	public function registerRoutes() {												
		register_rest_route( $this->namespace, '/runnerofthemonth', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'saveWinners' )				
		) );
		
		register_rest_route( $this->namespace, '/runnerofthemonth/vote', array(
			'methods'             => \WP_REST_Server::CREATABLE,			
			'callback'            => array( $this, 'saveRunnerOfTheMonthVote' )	
		) );

		register_rest_route( $this->namespace, '/runnerofthemonth/vote/(?P<resultId>[\d]+)', array(
			'methods'             => \WP_REST_Server::CREATABLE,			
			'callback'            => array( $this, 'saveRunnerOfTheMonthResultVote' ),
			'args'                => array(
				'resultId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'isValidId' )
					)
				)		
		) );

		register_rest_route( $this->namespace, '/runnerofthemonth/winners', array(
			'methods'             => \WP_REST_Server::READABLE,			
			'callback'            => array( $this, 'getRunnerOfTheMonthWinners' )				
		) );	

		register_rest_route( $this->namespace, '/runnerofthemonth/winners/year/(?P<year>[\d]+)/month/(?P<month>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,			
			'callback'            => array( $this, 'getRunnerOfTheMonthWinners' ),
			'args'                => array(
				'year'           => array(
					'required'          => true				
				),				
				'month'           => array(
					'required'          => true				
				)
			)				
		) );			
	}	

	private function getEvents( \WP_REST_Request $request ) {

		$response = $this->dataAccess->getEvents();
		
		return rest_ensure_response( $response );
	}

	private function saveWinners( \WP_REST_Request $request ) {

		$response1 = true;
		$response2 = true;
		$response3 = true;
		$response4 = true;
		if ($request['winners']['men'] > 0) {
			$response1 = $this->dataAccess->insertRunnerOfTheMonthWinners(
				$request['winners']['men'],
				'Men',
				$request['winners']['month'],
				$request['winners']['year']);
		}
				
		if ($request['winners']['women'] > 0) {
				$response2 = $this->dataAccess->insertRunnerOfTheMonthWinners(
				$request['winners']['women'],
				'Ladies',
				$request['winners']['month'],
				$request['winners']['year']);
		}
				
		if ($request['winners']['boys'] > 0) {
				$response3 = $this->dataAccess->insertRunnerOfTheMonthWinners(
				$request['winners']['boys'],
				'Boys',
				$request['winners']['month'],
				$request['winners']['year']);
		}
	
		if ($request['winners']['girls'] > 0) {
				$response4 = $this->dataAccess->insertRunnerOfTheMonthWinners(
				$request['winners']['girls'],
				'Girls',
				$request['winners']['month'],
				$request['winners']['year']);
		}
			
		return rest_ensure_response( $response1 && $response2 && $response3 && $response4);
	}

	private function saveRunnerOfTheMonthVote( \WP_REST_Request $request ) {
		// Validate user vote
		$voter = $this->dataAccess->getRunner($request['voterId']);
		if (get_class($voter) == 'WP_Error' || $voter->dateOfBirth != $request['voterDateOfBirth']) {
			return rest_ensure_response(new \WP_Error(
				'save_runnerofthemonthvote_invalid',
				'Runner and date of birth do not match.',
				array( 'status' => 401, "data" => $request, "voter" => json_encode($voter)  ) 
			));		
		}
		
		$now = new \DateTime();	
		
		if ($request['men'] != null) {
			$vote = array();
			$vote['runnerId'] = $request['men']['runnerId'];
			$vote['reason'] = $request['men']['reason'];
			$vote['category'] = 'Men';
			$vote['month'] =  $request['month'];
			$vote['year'] =  $request['year'];
			$vote['voterId'] = $request['voterId'];
			$vote['ipAddress'] = $_SERVER['REMOTE_ADDR'];
			$vote['created'] = $now->format('Y-m-d H:i:s');
			
			$response = $this->dataAccess->insertRunnerOfTheMonthVote($vote);
		}
		
		if ($request['ladies'] != null) {
			$vote = array();
			$vote['runnerId'] = $request['ladies']['runnerId'];
			$vote['reason'] = $request['ladies']['reason'];
			$vote['category'] = 'Ladies';
			$vote['month'] =  $request['month'];
			$vote['year'] =  $request['year'];
			$vote['voterId'] = $request['voterId'];
			$vote['ipAddress'] = $_SERVER['REMOTE_ADDR'];
			$vote['created'] = $now->format('Y-m-d H:i:s');
			
			$response = $this->dataAccess->insertRunnerOfTheMonthVote($vote);
		}
		
		return rest_ensure_response(true);
	}

	private function saveRunnerOfTheMonthResultVote( \WP_REST_Request $request ) {
					
		$ukMembershipResponse = $this->getUkAthleticsMembershipDetails($request['voterId']);

		if ($ukMembershipResponse->success == false) {
			return rest_ensure_response(new \WP_Error(
				'saveRunnerOfTheMonthResultVote_invalid',
				'UK Athletics Number not valid for Ipswich JAFFA RC Membership.',
				array( 'status' => 401, "data" => $request, "number" => $request['voterId']  ) 
			));
		}

		if ($ukMembershipResponse->lastName != $request['lastName']) {
			return rest_ensure_response(new \WP_Error(
				'saveRunnerOfTheMonthResultVote_invalid',
				'Last name supplied does not match that returned by UK Athletics for the membership number.',
				array( 'status' => 401, "data" => $request, "ukMembershipResponse" => json_encode($ukMembershipResponse)  ) 
			));						
		}

		$now = new \DateTime();

		$result = $this->dataAccess->getResult($request['resultId']);
		$date = strtotime($result['date']);
		
		$vote = array();
		$vote['runnerId'] = $result['runnerId'];
		$vote['reason'] = $result['eventName'] + ', result: ' + $result['result'] + ', position: ' + $result['position'];
		$vote['category'] = $this->getRunnerOfMonthCategory($result['sexId']);
		$vote['month'] = date('n', $date);
		$vote['year'] = date('Y', $date);
		$vote['voterId'] = $request['voterId'];
		$vote['ipAddress'] = $_SERVER['REMOTE_ADDR'];
		$vote['created'] = $now->format('Y-m-d H:i:s');
		
		$response = $this->dataAccess->insertRunnerOfTheMonthVote($vote);

		return rest_ensure_response(true);					
	}

	private function getUkAthleticsMembershipDetails($ukAthleticsMembershipNumber) {
		$client = new SoapClient(JAFFA_RESULTS_UkAthleticsLicenceCheckUrl);

		$request = array();
		$request['webUserKey'] = JAFFA_RESULTS_UkAthleticsWebAccessKey;
		$request['urn'] = $ukAthleticsMembershipNumber;

		$response = $client->CheckRegistrationStatus_Urn("CheckRegistrationStatus_Urn", $request);

		$getUkAthleticsMembershipDetailsResponse = array(
			"success" => false
		);

		if ($response->CheckRegistrationStatus_UrnResponse->CheckRegistrationStatus_UrnResult != "MatchFound")
			return getUkAthleticsMembershipDetailsResponse;

		if ($response->CheckRegistrationStatus_UrnResponse->result->Registered != "true" ||
		    \strpos($response->CheckRegistrationStatus_UrnResponse->result->FirstClaimClub, 'Ipswich Jaffa RC') !== false)
			return getUkAthleticsMembershipDetailsResponse;

		$getUkAthleticsMembershipDetailsResponse['success'] = true;	
		$getUkAthleticsMembershipDetailsResponse['lastName'] = $response->CheckRegistrationStatus_UrnResponse->result->FirstClaimClub;
		
		return $getUkAthleticsMembershipDetailsResponse;
	}

	private function getRunnerOfMonthCategory($sexId) {
		if ($sexId == 2)
			return 'Men';
		else 
			return 'Ladies';
	}
	
	private function getRunnerOfTheMonthWinners( \WP_REST_Request $request ) {
		$year = isset($request['year']) ? $request['year'] : 0;
		$month = isset($request['month']) ? $request['month'] : 0;
		$response = $this->dataAccess->getRunnerOfTheMonthWinnners($year, $month);

		return rest_ensure_response( $response );
	}		
}
?>
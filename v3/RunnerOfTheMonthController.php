<?php
namespace IpswichJAFFARunningClubAPI\V3;
	
require_once plugin_dir_path( __FILE__ ) .'BaseController.php';
require_once plugin_dir_path( __FILE__ ) .'IRoute.php';

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

		register_rest_route( $this->namespace, '/runnerofthemonth/winners', array(
			'methods'             => \WP_REST_Server::READABLE,			
			'callback'            => array( $this, 'getRunnerofthemonthwinners' )				
		) );	
		
		register_rest_route( $this->namespace, '/runnerofthemonth/vote/email', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'sendRunnerOftheMonthVotesEmail' ),
			'args'                => array(
				'email'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validateRotmEmail' ),
					),
				)
		) );

		register_rest_route( $this->namespace, '/runnerofthemonth/winners/year/(?P<year>[\d]+)/month/(?P<month>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,			
			'callback'            => array( $this, 'getRunnerofthemonthwinners' ),
			'args'                => array(
				'year'           => array(
					'required'          => true				
				),				
				'month'           => array(
					'required'          => true				
				)
			)				
		) );

		register_rest_route( $this->namespace, '/runnerofthemonth/winners/(?P<runnerOfTheMonthId>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array( $this, 'isAuthorized' ),
			'callback'            => array( $this, 'updateRunnerofthemonth' ),
			'args'                => array(
				'runnerOfTheMonthId'    => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					),
				'field'                 => array(
					'required'          => true
					),
				'value'           => array(
					'required'          => true
					)
				)				
		) );		
	}
	
	public function saveWinners( \WP_REST_Request $request ) {

    $response1 = true;
    $response2 = true;
    $response3 = true;
    $response4 = true;
		if ($request['winners']['men'] > 0)
		$response1 = $this->dataAccess->insertRunnerOfTheMonthWinners(
			$request['winners']['men'],
			'Men',
			$request['winners']['month'],
			$request['winners']['year']);
			
		if ($request['winners']['women'] > 0)
			$response2 = $this->dataAccess->insertRunnerOfTheMonthWinners(
			$request['winners']['women'],
			'Ladies',
			$request['winners']['month'],
			$request['winners']['year']);
			
		if ($request['winners']['boys'] > 0)
			$response3 = $this->dataAccess->insertRunnerOfTheMonthWinners(
			$request['winners']['boys'],
			'Boys',
			$request['winners']['month'],
			$request['winners']['year']);

		if ($request['winners']['girls'] > 0)
			$response4 = $this->dataAccess->insertRunnerOfTheMonthWinners(
			$request['winners']['girls'],
			'Girls',
			$request['winners']['month'],
			$request['winners']['year']);
		
		return rest_ensure_response( $response1 && $response2 && $response3 && $response4);
	}
	
	public function getRunnerofthemonthwinners( \WP_REST_Request $request ) {
		$year = isset($request['year']) ? $request['year'] : 0;
		$month = isset($request['month']) ? $request['month'] : 0;
		$response = $this->dataAccess->getRunnerOfTheMonthWinnners($year, $month);

		// Group data in structure:
		  // {
			// "year": "2016",
			// "month": "August",				
			// "winners": [
			  // {
				// "id": "116",
				// "name": "Gavin Davies"
				// "category": "Mens",
        // "winner_id": 123
			  // },
			  // {
				// "id": "117",
				// "name": "Helen Davies"
				// "category": "Ladies",
        // "winner_id": 124
			  // },
			  // {
				// "id": "118",
				// "name": "Kingsley Davies"
				// "category": "Boys",
        // "winner_id": 125
			  // },
			// ],						
		  // },	
		$results = array();		
		foreach ($response as $item) {
			$monthYear = $item->month . $item->year;
			if (!array_key_exists($monthYear, $results)) {				
				$results[$monthYear] = array("year" => $item->year, "month" => $item->month, "winners" => array());
			}
			
			$results[$monthYear]['winners'][] = array("id" => $item->runner_id, "name" => $item->name, "category" => $item->category, "winner_id" => $item->id);								
		}
			  
		return rest_ensure_response( array_values($results) );
	}
	
	public function updateRunnerofthemonth( \WP_REST_Request $request ) {

		$response = $this->dataAccess->updateRunnerOfTheMonthWinnners($request['runnerOfTheMonthId'], $request['field'], $request['value']);
		
		return rest_ensure_response( $response );
	}
	
	public function validateRotmEmail($value, $request, $key) {
		if (intval($request['email']['year']) <= 2010 ||				
			intval($request['email']['month']) < 0 ||
			empty($request['email']['toAddress'])) {				
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %s has invalid values', $key, json_encode($save)), array( 'status' => 400 ) );
		} else {
			return true;
		}
	}
	
	public function sendRunnerOftheMonthVotesEmail(\WP_REST_Request $request) {
	
		// To send HTML mail, the Content-type header must be set
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

		$user = wp_get_current_user();
		$fromAddress = $user->user_firstname . " " . $user->user_lastname . " <" . $user->user_email .">";
		
		// Additional headers
		$headers .= 'To: '.$request['email']['toAddress'] . "\r\n";
		$headers .= 'From: ' . $fromAddress . "\r\n";
		$headers .= 'Cc: gavinrunsdavies@gmail.com' . "\r\n";

		$year = $request['email']['year'];
		$month = str_pad($request['email']['month'], 2, "0", STR_PAD_LEFT);
		$date = new \DateTime("$year-$month-01");
		$subject = "Ipswich JAFFA RC John Jarrold Runner of the Month Votes ". $date->format('F') . " " .$date->format('Y');
		
		$votes = $this->dataAccess->getRunnerOfTheMonthVotes($request['email']['year'], $request['email']['month']);
		$votesHtml = $this->getVotesHtml($votes);
		$footerHtml  = "<p><small>This email was automatically sent via a request made on the Ipswich JAFFA RC Results Management Portal by ".$user->user_firstname . " " . $user->user_lastname .".</small></p>";
		$html = $votesHtml.$footerHtml;
		// $message = "Hello Gavin";
		// $message .= "From email: $fromEmail";
		// $message .= "From address: $fromAddress";
		// $message .= "to address: ".$request['email']['toAddress'];
		// Mail it
		mail($request['email']['toAddress'], $subject, $html, $headers);
		
		return rest_ensure_response( $votes );
	}
	
	private function getVotesHtml($results) {
		$mensWinners = array();
		$ladiesWinners = array();
		
		$html = "<h4>Men's nominations</h4>";
		$html .= $this->getVotesTable($results, "Men", $mensWinners);
		$html .= "<h4>Ladies nominations</h4>";
		$html .= $this->getVotesTable($results, "Ladies", $ladiesWinners);
				
		$headingHtml = "<h3>Men's winner</h3>";
		$headingHtml .= $this->getTotalVotesHtml($mensWinners);
		
		$headingHtml .= "<h3>Ladies winner</h3>";
		$headingHtml .= $this->getTotalVotesHtml($ladiesWinners);	
		$headingHtml .= '<br><br>';
		
		
		return $headingHtml.$html;
	}
	
	private function getVotesTable($results, $category, &$runners) {
		$html = '<table><thead><tr><th>Nomination</th><th>Reason</th><th>Voter ID</th></tr></thead>';
		$html .= '<tbody>';
		foreach ($results as $row) {
			if ($row->category == $category) {
				$html .= '<tr>';			
				$html .= '<td>'.$row->nomination.'</td>';
				$html .= '<td>'.$row->reason.'</td>';
				$html .= '<td>'.$row->voterId.'</td>';
				$html .= '</tr>';
			
		
				if (!array_key_exists($row->nomination, $runners)) {
					$runners[$row->nomination] = 1;
				} else {
					$runners[$row->nomination] += 1;
				}
			}
		}
		$html .= '</tbody></table>';
		
		arsort($runners);
		
		return $html;

	}
	
	private function getTotalVotesHtml($sortedVotes) {
		
		$headingHtml .= '<ol>';
		$i = 0;
		$lastVoteCount = 0;
		foreach($sortedVotes as $name => $votes) {			
			if ($i >= 3 && $lastVoteCount != $votes) {
				break;
			}
			$headingHtml .= '<li>'.$name.' - '.$votes.' votes</li>';
			$i++;
			$lastVoteCount = $votes;
		}
		$headingHtml .= '</ol>';
		
		return $headingHtml;
	}	
}
?>
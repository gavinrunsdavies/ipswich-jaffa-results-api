<?php
namespace IpswichJAFFARunningClubAPI\V3;
	
require_once plugin_dir_path( __FILE__ ) .'class-ipswich-jaffa-results-data-access.php';

class Ipswich_JAFFA_Results_WP_REST_API_Controller_V3 {
	
	private $data_access;
	
	private $user;
	
	public function __construct() {
		$this->data_access = new Ipswich_JAFFA_Results_Data_Access();
	}
	
	public function rest_api_init( ) {			
		
		$namespace = 'ipswich-jaffa-api/v3'; // base endpoint for our custom API
									
		$this->register_routes_runner_of_the_month($namespace);		
		$this->register_routes_results($namespace);
		
		add_filter( 'rest_endpoints', array( $this, 'remove_wordpress_core_endpoints'), 10, 1 );			
	}
	
	public function plugins_loaded() {

		// enqueue WP_API_Settings script
		add_action( 'wp_print_scripts', function() {
			wp_enqueue_script( 'wp-api' );
		} );					
	}
	
	private function register_routes_results($namespace) {
		register_rest_route( $namespace, '/results/autoload', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'get_chipTimingResults' ),
			'args'                => array(
				'chipTimingResultsUrl' => array(
					'required'    => true,					
					)
				)
		) );
	}
	
	private function register_routes_runner_of_the_month($namespace) {			
		
		register_rest_route( $namespace, '/runnerofthemonth', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'save_winners' )				
		) );

		register_rest_route( $namespace, '/runnerofthemonth/winners', array(
			'methods'             => \WP_REST_Server::READABLE,			
			'callback'            => array( $this, 'get_runnerofthemonthwinners' )				
		) );	
		
		register_rest_route( $namespace, '/runnerofthemonth/vote/email', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'sendRunnerOftheMonthVotesEmail' )				,
			'args'                => array(
				'email'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validate_rotm_email' ),
					),
				)
		) );

		register_rest_route( $namespace, '/runnerofthemonth/winners/year/(?P<year>[\d]+)/month/(?P<month>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,			
			'callback'            => array( $this, 'get_runnerofthemonthwinners' ),
			'args'                => array(
				'year'           => array(
					'required'          => true				
				),				
				'month'           => array(
					'required'          => true				
				)
			)				
		) );

		register_rest_route( $namespace, '/runnerofthemonth/winners/(?P<runnerOfTheMonthId>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'update_runnerofthemonth' ),
			'args'                => array(
				'runnerOfTheMonthId'    => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
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
	
	public function save_winners( \WP_REST_Request $request ) {

		if ($request['winners']['men'] > 0)
		$response = $this->data_access->insertRunnerOfTheMonthWinners(
			$request['winners']['men'],
			'Men',
			$request['winners']['month'],
			$request['winners']['year']);
			
		if ($response == true && $request['winners']['women'] > 0)
			$response = $this->data_access->insertRunnerOfTheMonthWinners(
			$request['winners']['women'],
			'Ladies',
			$request['winners']['month'],
			$request['winners']['year']);
			
		if ($response == true && $request['winners']['junior'] > 0)
			$response = $this->data_access->insertRunnerOfTheMonthWinners(
			$request['winners']['junior'],
			'Juniors',
			$request['winners']['month'],
			$request['winners']['year']);
		
		return rest_ensure_response( $response );
	}
	
	public function get_runnerofthemonthwinners( \WP_REST_Request $request ) {
		$year = isset($request['year']) ? $request['year'] : 0;
		$month = isset($request['month']) ? $request['month'] : 0;
		$response = $this->data_access->getRunnerOfTheMonthWinnners($year, $month);

		// Group data in structure:
		  // {
			// "year": "2016",
			// "month": "August",				
			// "winners": [
			  // {
				// "id": "116",
				// "name": "Gavin Davies"
				// "category": "Mens"
			  // },
			  // {
				// "id": "117",
				// "name": "Helen Davies"
				// "category": "Ladies"
			  // },
			  // {
				// "id": "118",
				// "name": "Kingsley Davies"
				// "category": "Junior"
			  // },
			// ],						
		  // },	
		$results = array();		
		foreach ($response as $item) {
			$monthYear = $item->month . $item->year;
			if (!array_key_exists($monthYear, $results)) {				
				$results[$monthYear] = array("year" => $item->year, "month" => $item->month, "winners" => array());
			}
			
			$results[$monthYear]['winners'][] = array("id" => $item->id, "name" => $item->name, "category" => $item->category);								
		}
			  
		return rest_ensure_response( array_values($results) );
	}
			
	public function get_runners( \WP_REST_Request $request ) {
		$loggedIn = $this->permission_check($request);
		$response = $this->data_access->getRunners($loggedIn);

		return rest_ensure_response( $response );
	}
	
	public function update_runnerofthemonth( \WP_REST_Request $request ) {

		$response = $this->data_access->updateRunnerOfTheMonthWinnners($request['runnerOfTheMonthId'], $request['field'], $request['value']);
		
		return rest_ensure_response( $response );
	}
	
	public function validate_rotm_email($request, $request, $key) {
		if (intval($request['email']['year']) <= 2010 ||				
			intval($request['email']['month']) < 0 ||
			empty($request['email']['toAddress'])) {				
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %s has invalid values', $key, json_encode($save)), array( 'status' => 400 ) );
		} else {
			return true;
		}
	}
	
	public function get_chipTimingResults($request) {
		$contents = file_get_contents($request['chipTimingResultsUrl']);
		
		$xml = simplexml_load_string($contents);
		$results = array();
		foreach ($xml->children() as $child)
		{
			$result = array();
			foreach ($child->children() as $element) {			  
			  if ($element['class'] == "grid_pos") {
					$result['position'] = (string)$element;
			  } else if ($element['class'] == "grid_chip") {
					$result['chip'] = (string)$element;
			  } else if ($element['class'] == "grid_name") {
					$result['name'] = (string)$element;
			  }
			}
			$results[] = $result;
		}
		
		return rest_ensure_response( $results );
	}
	
	public function sendRunnerOftheMonthVotesEmail(\WP_REST_Request $request) {
	
		// To send HTML mail, the Content-type header must be set
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

		$user = $this->getCurrentUser();
		$fromAddress = $user->first_name . " " . $user->last_name . " <" . $user->user_email .">";
		
		// Additional headers
		$headers .= 'To: '.$request['email']['toAddress'] . "\r\n";
		$headers .= 'From: ' . $fromAddress . "\r\n";
	//	$headers .= 'Cc: results@ipswichjaffa.org.uk' . "\r\n";
		$headers .= 'Cc: gavinrunsdavies@gmail.com' . "\r\n";

		$year = $request['email']['year'];
		$month = str_pad($request['email']['month'], 2, "0", STR_PAD_LEFT);
		$date = new \DateTime("$year-$month-01");
		$subject = "Ipswich JAFFA RC John Jarrold Runner of the Month Votes ". $date->format('F') . " " .$date->format('Y');
		
		$votes = $this->data_access->getRunnerOfTheMonthVotes($request['email']['year'], $request['email']['month']);
		$votesHtml = $this->getVotesHtml($votes);
		$footerHtml  = "<p><small>This email was automatically sent via a request made on the Ipswich JAFFA RC Results Management Portal by ".$user->first_name . " " . $user->last_name .".</small></p>";
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
		foreach($sortedVotes as $name => $votes) {
			if ($i == 3) {
				break;
			}
			$headingHtml .= '<li>'.$name.' - '.$votes.' votes</li>';
			$i++;
		}
		$headingHtml .= '</ol>';
		
		return $headingHtml;
	}
	
	private function getCurrentUser() {
		$id = $this->basic_auth_handler($this->user);
		return get_userdata($id);
	}
	
	private function basic_auth_handler( $user ) {
		// Don't authenticate twice
		if ( ! empty( $user ) ) {
			return $user->ID;
		}
		
		// Check that we're trying to authenticate
		if ( !isset( $_SERVER['PHP_AUTH_USER'] ) ) {
			return $user->ID;
		}
		$username = $_SERVER['PHP_AUTH_USER'];
		$password = $_SERVER['PHP_AUTH_PW'];
		
		/**
		 * In multi-site, wp_authenticate_spam_check filter is run on authentication. This filter calls
		 * get_currentuserinfo which in turn calls the determine_current_user filter. This leads to infinite
		 * recursion and a stack overflow unless the current function is removed from the determine_current_user
		 * filter during authentication.
		 */
		remove_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );
		$user = wp_authenticate( $username, $password );
		add_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );
		if ( is_wp_error( $user ) ) {				
			return 0;
		}
		
		return $user->ID;
	}	
		
	public function permission_check( \WP_REST_Request $request ) {
		$id = $this->basic_auth_handler($this->user);
		if ( $id  <= 0 ) {				
			return new \WP_Error( 'rest_forbidden',
				sprintf( 'You must be logged in to use this API.' ), array( 'status' => 403 ) );
		} else if (!user_can( $id, 'publish_pages' )){
			return new \WP_Error( 'rest_forbidden',
				sprintf( 'You do not have enough privlidges to use this API.' ), array( 'status' => 403 ) );
		} else {
			return true;
		}
	}

	/**
	 * Unsets all core WP endpoints registered by the WordPress REST API (via rest_endpoints filter)
	 * @param  array   $endpoints   registered endpoints
	 * @return array
	 */
	public function remove_wordpress_core_endpoints( $endpoints ) {

		foreach ( array_keys( $endpoints ) as $endpoint ) {
			if ( stripos( $endpoint, '/wp/v2' ) === 0 ) {
				unset( $endpoints[ $endpoint ] );
			}
		}

		return $endpoints;
	}

	public function is_valid_id( $value, $request, $key ) {
		if ( $value < 1 ) {
			// can return false or a custom \WP_Error
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %d must be greater than 0', $key, $value ), array( 'status' => 400 ) );
		} else {
			return true;
		}
	}		
}
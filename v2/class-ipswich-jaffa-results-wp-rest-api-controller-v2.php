<?php
namespace IpswichJAFFARunningClubAPI\V2;
	
require_once plugin_dir_path( __FILE__ ) .'class-ipswich-jaffa-results-data-access.php';

class Ipswich_JAFFA_Results_WP_REST_API_Controller_V2 {
	
	private $data_access;
	
	private $user;
	
	public function __construct() {
		$this->data_access = new Ipswich_JAFFA_Results_Data_Access();
	}
	
	public function rest_api_init( ) {			
		
		$namespace = 'ipswich-jaffa-api/v2'; // base endpoint for our custom API
				
		$this->register_routes_authentication($namespace);
		$this->register_routes_distances($namespace);
		$this->register_routes_events($namespace);		
		$this->register_routes_meetings($namespace);		
		$this->register_routes_runners($namespace);
		$this->register_routes_results($namespace);						
		$this->register_routes_runner_of_the_month($namespace);		
		$this->register_routes_statistics($namespace);			
		$this->register_routes_races($namespace);	
		
		add_filter( 'rest_endpoints', array( $this, 'remove_wordpress_core_endpoints'), 10, 1 );			
	}
	
	public function plugins_loaded() {

		// enqueue WP_API_Settings script
		add_action( 'wp_print_scripts', function() {
			wp_enqueue_script( 'wp-api' );
		} );					
	}
	
	private function register_routes_authentication($namespace) {					
		register_rest_route( $namespace, '/login', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'login' ),
			'args'                => array(
				'username'           => array(
					'required'          => true						
					),
				'password'           => array(
					'required'          => true						
					)
				)				
		) );					
	}

	/* 
	 * NOTE:  Sanitization is useful when you would rather change value. Validation will throw exception
	 * 
	 */
	private function register_routes_distances($namespace) {			
		register_rest_route( $namespace, '/distances', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_distances' )
		) );					
	}
	
	private function register_routes_statistics($namespace) {			
					
		register_rest_route( $namespace, '/statistics/type/(?P<typeId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_statistics' ),
			'args'                => array(
				'typeId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'is_valid_id' ),
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
	}
	
	private function register_routes_events($namespace) {										
		register_rest_route( $namespace, '/events', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_events' )
		) );
		
		register_rest_route( $namespace, '/events/(?P<eventId>[\d]+)/races', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_races' ),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'is_valid_id' ),
					),
				)
		) );
		register_rest_route( $namespace, '/coursetypes', array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'get_coursetypes' )
			) );
			
		register_rest_route( $namespace, '/events', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'save_event' ),				
			'args'                => array(
				'event'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validate_event' ),
					),
				)
		) );
		
		// Patch - updates
		register_rest_route( $namespace, '/events/(?P<eventId>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'update_event' ),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
					),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array( $this, 'is_valid_event_update_field' )
					),
				'value'           => array(
					'required'          => true
					)
				)				
		) );
		
		register_rest_route( $namespace, '/events/merge', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'merge_events' ),
			'args'                => array(
				'fromEventId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'is_valid_id' )
					),
				'toEventId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
					)
				)					
		) );
		
		register_rest_route( $namespace, '/events/(?P<eventId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_event' ),
			'permission_callback' => array( $this, 'permission_check' ),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
					)
				)
		) );
	}
	
	private function register_routes_meetings($namespace) {										
		register_rest_route( $namespace, '/events/(?P<eventId>[\d]+)/meetings', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_meetings' ),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'is_valid_id' ),
					)
			)
		) );
		
		register_rest_route( $namespace, '/events/(?P<eventId>[\d]+)/meetings/(?P<meetingId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_meeting' ),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'is_valid_id' ),
					),
				'meetingId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'is_valid_id' ),
					)
				)
		) );
			
		register_rest_route( $namespace, '/events/(?P<eventId>[\d]+)/meetings/(?P<meetingId>[\d]+)/races', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_meetingRaces' ),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'is_valid_id' ),
					),
				'meetingId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'is_valid_id' ),
					)
				)
		) );
			
		register_rest_route( $namespace, '/events/(?P<eventId>[\d]+)/meetings', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'save_meeting' ),				
			'args'                => array(
				'eventId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'is_valid_id' ),
					),
				'meeting'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validate_meeting' ),
					),
				)
		) );
		
		// Patch - updates
		register_rest_route( $namespace, '/events/(?P<eventId>[\d]+)/meetings/(?P<meetingId>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'update_meeting' ),
			'args'                => array(
				'meetingId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
					),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array( $this, 'is_valid_meeting_update_field' )
					),
				'value'           => array(
					'required'          => true
					)
				)	
		) );
		
		register_rest_route( $namespace, '/events/(?P<eventId>[\d]+)/meetings/(?P<meetingId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_meeting' ),
			'permission_callback' => array( $this, 'permission_check' ),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
					),
				'meetingId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'is_valid_id' ),
					),
				)
		) );
	}
	
	private function register_routes_races($namespace) {										
		register_rest_route( $namespace, '/events/(?P<eventId>[\d]+)/races', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_races' ),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'is_valid_id' ),
					),
				)
		) );			
		
		register_rest_route( $namespace, '/races', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'save_race' ),				
			'args'                => array(
				'race'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validate_race' ),
					),
				)
		) );						
		
		register_rest_route( $namespace, '/races/(?P<id>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_race' ),				
			'args'                 => array(
				'id'           => array(
					'required'          => true,	
					'validate_callback' => array( $this, 'is_valid_id' )
					)
				)
		) );	

		register_rest_route( $namespace, '/races/(?P<id>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'update_race' ),
			'args'                => array(
				'id'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
					),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array( $this, 'is_valid_race_update_field' )
					),
				'value'           => array(
					'required'          => true
					)
				)				
		) );

		register_rest_route( $namespace, '/events/(?P<eventId>[\d]+)/race/(?P<raceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_race' ),
			'permission_callback' => array( $this, 'permission_check' ),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
					),
				'raceId'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'is_valid_id' ),
					),
				)
		) );		
	}
	
	private function register_routes_runners($namespace) {
		
		register_rest_route( $namespace, '/runners', array(
			'methods'             => \WP_REST_Server::READABLE,			
			'callback'            => array( $this, 'get_runners' )
		) );
		
		register_rest_route( $namespace, '/runners', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'save_runner' ),				
			'args'                => array(
				'runner'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validate_runner' )
					),
				)
		) );
		
		register_rest_route( $namespace, '/runners/(?P<runnerId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'delete_runner' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' ),
					)
				)
		) );
		
		register_rest_route( $namespace, '/runners/(?P<runnerId>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'update_runner' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
					),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array( $this, 'is_valid_runner_update_field' )
					),
				'value'           => array(
					'required'          => true
					)
				)				
		) );
		
		register_rest_route( $namespace, '/genders', array(
			'methods'             => \WP_REST_Server::READABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'get_genders' )
		) );
	}
	
	private function register_routes_results($namespace) {
		register_rest_route( $namespace, '/results', array(
			'methods'             => \WP_REST_Server::READABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'get_results' )
		) );
		
		register_rest_route( $namespace, '/results', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'save_result' ),				
			'args'                => array(
				'result'           => array(
					'required'          => true,												
					'validate_callback' => array( $this, 'validate_result' ),
					),
				)
		) );
		
		register_rest_route( $namespace, '/results/(?P<resultId>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_result' ),
			'permission_callback' => array( $this, 'permission_check' ),
			'args'                => array(
				'resultId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' ),
					)
				)
		) );
		
		// Patch - updates
		register_rest_route( $namespace, '/results/(?P<resultId>[\d]+)', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => array( $this, 'permission_check' ),
			'callback'            => array( $this, 'update_result' ),
			'args'                => array(
				'resultId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
					),
				'field'           => array(
					'required'          => true,
					'validate_callback' => array( $this, 'is_valid_result_update_field' )
					),
				'value'           => array(
					'required'          => true
					)
				)				
		) );
		
		register_rest_route( $namespace, '/results/records/distance/(?P<distanceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'get_clubRecords' ),
			'args'                => array(
				'distanceId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
					)
				)		
		) );
		
		register_rest_route( $namespace, '/results/historicrecords/distance/(?P<distanceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'get_historicClubRecords' ),
			'args'                => array(
				'distanceId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
					)
				)		
		) );
		
		register_rest_route( $namespace, '/results/ranking/distance/(?P<distanceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'get_resultRankings' ),
			'args'                => array(
				'distanceId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
					)
				)		
		) );
		
		register_rest_route( $namespace, '/results/ranking/averageWMA', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'get_averagePercentageRankings' )
		) );
		
		register_rest_route( $namespace, '/results/ranking/wma', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'get_WMAPercentageRankings' )				
		) );
		
		register_rest_route( $namespace, '/results/runner/(?P<runnerId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'get_memberResults' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
					)
				)		
		) );
		
		register_rest_route( $namespace, '/results/runner/(?P<runnerId>[\d]+)/personalbests', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'get_memberPBResults' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
					)
				)		
		) );
		
		register_rest_route( $namespace, '/results/runner/compare', array(
			'methods'             => \WP_REST_Server::CREATABLE,				
			'callback'            => array( $this, 'get_compareMemberRaces' ),
			'args'                => array(
				'runnerIds'           => array(
					'required'          => true
					)
				)		
		) );
		
		register_rest_route( $namespace, '/certificates/(?P<runnerId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'get_standardCertificates' ),
			'args'                => array(
				'runnerId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
					)
				)		
		) );
		
		register_rest_route( $namespace, '/results/grandPrix/(?P<year>[\d]{4})/(?P<sexId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'get_grandPrixPoints' ),
			'args'                => array(
				'sexId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
					)
				),
				'year'           => array(
					'required'          => true
				)
		) );
		
		register_rest_route( $namespace, '/results/race/(?P<raceId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'get_raceResults' ),
			'args'                => array(
				'raceId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'is_valid_id' )
					)
				)
		) );			
	}		
	
	public function get_races( \WP_REST_Request $request ) {
	
		$response = $this->data_access->getRaces($request['eventId']);
		
		return rest_ensure_response( $response );
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
		
		public function get_distances( \WP_REST_Request $request ) {
		    $response = $this->data_access->getDistances();

			return rest_ensure_response( $response );
		}
		
		public function get_statistics( \WP_REST_Request $request ) {
			switch ($request['typeId'])
			{
				case 1:
					$response = $this->data_access->getResultsByYearAndCounty();
					break;
				case 2:
					$response = $this->data_access->getResultsByYearAndCountry();
					break;
				case 3:
					$response = $this->data_access->getResultsCountByYear();
					break;
				case 4:
					$response = $this->data_access->getPersonalBestTotals();
					break;
				case 5:
					$response = $this->data_access->getPersonalBestTotalByYear();
					break;				
				case 6:
					$response = $this->data_access->getTopAttendedRaces();
					break;
				case 7:
					$response = $this->data_access->getTopMembersRacing();
					break;	
				case 8:
					$response = $this->data_access->getTopMembersRacingByYear();
					break;					
				default:
				break;
			}
		    
			return rest_ensure_response( $response );
		}
		
		public function get_resultsCountByYear( \WP_REST_Request $request ) {
		    $response = $this->data_access->getResultsCountByYear();

			return rest_ensure_response( $response );
		}
		
		public function get_clubRecords( \WP_REST_Request $request ) {
		    $response = $this->data_access->getClubRecords($request['distanceId']);

			return rest_ensure_response( $response );
		}
		
		public function get_raceResults( \WP_REST_Request $request ) {
		    $response = $this->data_access->getRaceResults($request['raceId']);

			return rest_ensure_response( $response );
		}
		
		public function get_memberResults( \WP_REST_Request $request ) {
		    $response = $this->data_access->getMemberResults($request['runnerId']);

			return rest_ensure_response( $response );
		}
		
		public function get_memberPBResults( \WP_REST_Request $request ) {
		    $response = $this->data_access->getMemberPBResults($request['runnerId']);

			return rest_ensure_response( $response );
		}		
		
		public function get_compareMemberRaces( \WP_REST_Request $request ) {
		    $response = $this->data_access->getHeadToHeadResults($request['runnerIds']);

			return rest_ensure_response( $response );
		}
		
		public function get_standardCertificates( \WP_REST_Request $request ) {
		    $response = $this->data_access->getStandardCertificates($request['runnerId']);

			return rest_ensure_response( $response );
		}
		
		public function get_resultRankings( \WP_REST_Request $request ) {
			$parameters = $request->get_query_params();			
		    $response = $this->data_access->getResultRankings($request['distanceId'], $parameters['year'], $parameters['sexId']);

			return rest_ensure_response( $response );
		}
		
		public function get_WMAPercentageRankings( \WP_REST_Request $request ) {
			$parameters = $request->get_query_params();			
		    $response = $this->data_access->getWMAPercentageRankings($parameters['sexId'], $parameters['distanceId'], $parameters['year'], $parameters['distinct']);

			return rest_ensure_response( $response );
		}
		
		public function get_historicClubRecords( \WP_REST_Request $request ) {			
		    $response = $this->data_access->getAllRaceResults($request['distanceId']);
			
			// Group data in to catgeories and pick best times
			$categoryId = 0;
			$records = array();
			foreach ($response as $item) {
				$categoryId = $item->categoryId;
				if (!array_key_exists($categoryId, $records)) {
					$result = array("runnerId" => $item->id, "runnerName" => $item->name, "raceId" => $item->raceId, "raceDescription" => $item->raceDescription, "eventName" => $item->eventName, "time" => $item->result, "position" => $item->position, "date" => $item->date);
					$records[$categoryId] = array("id" => $categoryId, "code" => $item->categoryCode, "records" => array($result));
					
					continue;
				}
				
				$currentResult = $item->result;
				$count = count($records[$categoryId]['records']);
				$previousRecord = $records[$categoryId]['records'][$count-1]['time'];
				if ($currentResult < $previousRecord) {
					$records[$categoryId]['records'][] = array("runnerId" => $item->id, "runnerName" => $item->name, "raceId" => $item->raceId, "raceDescription" => $item->raceDescription, "eventName" => $item->eventName, "time" => $item->result, "position" => $item->position, "date" => $item->date);
				}									
			}

			return rest_ensure_response( $records );
		}
		
		public function get_averagePercentageRankings( \WP_REST_Request $request ) {
			$parameters = $request->get_query_params();			
		    $response = $this->data_access->getAveragePercentageRankings($parameters['sexId'], $parameters['year'], $parameters['numberOfRaces']);

			return rest_ensure_response( $response );
		}
		
		public function get_grandPrixPoints( \WP_REST_Request $request ) {
		    $response = $this->data_access->getGrandPrixPoints($request['year'], $request['sexId']);

			// Group data in structure:
			// {
			  // "5": {
				// "id": "5",
				// "name": "Alan Jackson",
				// "dateOfBirth": "1980-01-02",
				// "races": [
				  // {
					// "id": "954",
					// "points": "85"
				  // },
				  // {
					// "id": "1512",
					// "points": "79"
				  // },
				  // {
					// "id": "729",
					// "points": "90"
				  // }
				// ],
				// "totalPoints": 254
			  // },
			  // "9": {
				// "id": "9",
				// "name": "Alistair Dick",
				// "races": [
				  // {
					// "id": "954",
					// "points": "88"
				  // },
				  // {
					// "id": "549",
					// "points": "96"
				  // }
				// ],
				// "totalPoints": 184
			  // },			
			$results = array();
			$races = array();
			foreach ($response as $item) {
				$runnerId = $item->runnerId;
				if (!array_key_exists($runnerId, $results)) {
					$gpCategory = $this->getGrandPrixCategory($item->dateOfBirth, $request['year']);
					$results[$runnerId] = array("id" => $runnerId, "name" => $item->name, "categoryCode" => $gpCategory, "races" => array());
				}
				
				$results[$runnerId]['races'][] = array("id" => $item->raceId, "points" => $item->rank);
				$results[$runnerId]['totalPoints'] += $item->rank;				
				
				$raceId = $item->raceId;
				if (!in_array($raceId, $races)) {
					$races[] = $raceId;
				}
			}
			
			// Get race details
			$raceDetails = $this->data_access->getRaceDetails($races);
			
			foreach ($results as $runner){
				$results[$runner['id']]['best8Score'] = $this->getGrandPrixBest8Score($runner['races']);
			}
			
			$getGrandPrixPointsResponse = array(
				"races" => $raceDetails,
				"results" => $results
			);
			
			return rest_ensure_response( $getGrandPrixPointsResponse );
		}
		
		private function getGrandPrixCategory($dateOfBirth, $year) 
		{
		//http://stackoverflow.com/questions/3776682/php-calculate-age		

		  $dob = new \DateTime($dateOfBirth);
          $gpDate = new \DateTime("$year-03-01");

          $diff = $dob->diff($gpDate);
		  		  
		  if ($diff->y < 40) 
			return "Open";
		  if ($diff->y < 50) 
			return "V40";
		  if ($diff->y < 60) 
			return "V50";
		  
		  return "V60";
		}
		
		private function getGrandPrixBest8Score($races) 
		{			
			uasort($races, array($this, 'compareGrandPrixRaces'));

			// Get best 8 scores 
			$best8Score = 0;   

			$count = 1;
			foreach ($races as $race) 
			{        
				$best8Score += $race['points'];				
				if ($count == 8) 
				{
				  break;
				}
				$count++;
		    }		
		  
		    return $best8Score;
		} // end function getGrandPrixBest8Score
		
		private function compareGrandPrixRaces($a, $b) {
			if ($a['points'] == $b['points']) {
				return 0;
			}
			
			return ($a['points'] > $b['points']) ? -1 : 1;
		}
		
		public function get_resultsByYearAndCountry( \WP_REST_Request $request ) {
		    $response = $this->data_access->getResultsByYearAndCountry();

			return rest_ensure_response( $response );
		}
		
		public function get_coursetypes( \WP_REST_Request $request ) {

			$response = $this->data_access->getCourseTypes();
			
			return rest_ensure_response( $response );
		}
		
		public function get_events( \WP_REST_Request $request ) {

			$response = $this->data_access->getEvents();
			
			return rest_ensure_response( $response );
		}
		
		public function save_event( \WP_REST_Request $request ) {

			$response = $this->data_access->insertEvent($request['event']);
			
			return rest_ensure_response( $response );
		}
		
		public function get_race( \WP_REST_Request $request ) {

			$response = $this->data_access->getRace($request['id']);
			
			return rest_ensure_response( $response );
		}
		
		public function save_race( \WP_REST_Request $request ) {

			$response = $this->data_access->insertRace($request['race']);
			
			return rest_ensure_response( $response );
		}
		
		public function update_event( \WP_REST_Request $request ) {

			$response = $this->data_access->updateEvent($request['eventId'], $request['field'], $request['value']);
			
			return rest_ensure_response( $response );
		}
		
		public function update_race( \WP_REST_Request $request ) {

			if ($request['field'] == "distance_id") {
				$response = $this->data_access->updateRaceDistance($request['id'], $request['value']);
			} else {
			$response = $this->data_access->updateRace($request['id'], $request['field'], $request['value']);
			}
			
			return rest_ensure_response( $response );
		}
		
		public function delete_race( \WP_REST_Request $request ) {
			
			$response = $this->data_access->deleteRace($request['raceId'], false);
			
			return rest_ensure_response( $response );
		}
				
		public function delete_event( \WP_REST_Request $request ) {
			// TODO deleteResults parameter.
			$response = $this->data_access->deleteEvent($request['eventId'], false);
			
			return rest_ensure_response( $response );
		}
		
		public function merge_events( \WP_REST_Request $request ) {

			$response = $this->data_access->mergeEvents($request['fromEventId'], $request['toEventId']);
			
			return rest_ensure_response( $response );
		}
		
		public function get_genders( \WP_REST_Request $request ) {

			$response = $this->data_access->getGenders();

			return rest_ensure_response( $response );
		}
		
		public function get_runners( \WP_REST_Request $request ) {
			$loggedIn = $this->permission_check($request);
			$response = $this->data_access->getRunners($loggedIn);

			return rest_ensure_response( $response );
		}
		
		public function save_runner( \WP_REST_Request $request ) {

			$response = $this->data_access->insertRunner($request['runner']);
			
			return rest_ensure_response( $response );
		}
		
		public function delete_runner( \WP_REST_Request $request ) {
			// TODO deleteResults parameter.
			$response = $this->data_access->deleteRunner($request['runnerId'], false);
			
			return rest_ensure_response( $response );
		}
		
		public function update_runner( \WP_REST_Request $request ) {

			$response = $this->data_access->updateRunner($request['runnerId'], $request['field'], $request['value']);
			
			return rest_ensure_response( $response );
		}
		
		public function get_results( \WP_REST_Request $request ) {
			// TODO, eventID, fromDate, toDate and limit. All optional.
			// Sanitization needed before
			$parameters = $request->get_query_params();
			$response = $this->data_access->getResults($parameters['eventId'], $parameters['fromDate'], $parameters['toDate'], $parameters['numberOfResults']);

			return rest_ensure_response( $response );
		}
		
		public function save_result( \WP_REST_Request $request ) {

			$response = $this->data_access->insertResult($request['result']);
			
			return rest_ensure_response( $response );
		}
		
		public function delete_result( \WP_REST_Request $request ) {
			
			$response = $this->data_access->deleteResult($request['resultId'], false);
			
			return rest_ensure_response( $response );
		}
		
		public function update_result( \WP_REST_Request $request ) {

			$response = $this->data_access->updateResult($request['resultId'], $request['field'], $request['value']);
			
			return rest_ensure_response( $response );
		}
		
		public function get_meetings( \WP_REST_Request $request ) {

			$response = $this->data_access->getMeetings($request['eventId']);
			
			return rest_ensure_response( $response );
		}
		
		public function get_meeting( \WP_REST_Request $request ) {

			$response = $this->data_access->getMeeting($request['meetingId']);
			
			return rest_ensure_response( $response );
		}
		
		public function get_meetingRaces( \WP_REST_Request $request ) {

			$response = $this->data_access->getMeetingRaces($request['meetingId']);
			
			return rest_ensure_response( $response );
		}
		
		public function save_meeting( \WP_REST_Request $request ) {

			$response = $this->data_access->insertMeeting($request['meeting'], $request['eventId']);
			
			return rest_ensure_response( $response );
		}
		
		public function update_meeting( \WP_REST_Request $request ) {

			$response = $this->data_access->updateMeeting($request['meetingId'], $request['field'], $request['value']);
			
			return rest_ensure_response( $response );
		}
		
		public function delete_meeting( \WP_REST_Request $request ) {
			
			$response = $this->data_access->deleteMeeting($request['meetingId']);
			
			return rest_ensure_response( $response );
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

		public function is_valid_event_update_field($value, $request, $key){
			if ( $value == 'name' || $value == 'website' ) {
				return true;
			} else {
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %d must be name or website only.', $key, $value ), array( 'status' => 400 ) );
			} 			
		}
		
		public function is_valid_meeting_update_field($value, $request, $key){
			if ( $value == 'from_date' || $value == 'to_date' || $value == 'name' ) {
				return true;
			} else {
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %d must be name or fromDate or toDate only.', $key, $value ), array( 'status' => 400 ) );
			} 			
		}
		
		public function is_valid_race_update_field($value, $request, $key){
			if ( $value == 'event_id' || 
			    $value == 'description' || 
				$value == 'course_type_id' || 
				$value == 'course_number' || 
				$value == 'area' || 
				$value == 'county' ||
				$value == 'country_code' || 
				$value == 'venue' || 
				$value == 'distance_id' || 
				$value == 'conditions' || 
				$value == 'meeting_id' || 
				$value == 'grand_prix' ) {
				return true;
			} else {
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %d invalid value.', $key, $value ), array( 'status' => 400) );
			} 			
		}
		
		public function is_valid_runner_update_field($value, $request, $key){
			if ( $value == 'name' || $value == 'current_member' ) {
				return true;
			} else {
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %d must be name or current_member only.', $key, $value ), array( 'status' => 400 ) );
			} 			
		}
		
		public function is_valid_result_update_field($value, $request, $key){
			if ( $value == 'info' || $value == 'position' || $value == 'result' || $value == 'grandprix' || $value == 'scoring_team' || $value == 'race_id') {
				return true;
			} else {
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %d must be info or position or result or grandprix or scoring_team only.', $key, $value ), array( 'status' => 400 ) );
			} 			
		}
		
		public function validate_race($race, $request, $key) {
			if (intval($race['eventId']) < 1) {				
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid eventId value', $key, json_encode($race)), array( 'status' => 400 ) );
			}					
				
			$date=date_parse($race['date']);
			if (checkdate($date['month'], $date['day'], $date['year']) === FALSE) {				
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid date value', $key, json_encode($race)), array( 'status' => 400 ) );
			} 
		}
		
		public function validate_meeting($meeting, $request, $key) {
			$date=date_parse($meeting['fromDate']);
			if (checkdate($date['month'], $date['day'], $date['year']) === FALSE) {				
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid from date value', $key, json_encode($meeting)), array( 'status' => 400 ) );
			} 
			
			$date=date_parse($meeting['toDate']);
			if (checkdate($date['month'], $date['day'], $date['year']) === FALSE) {				
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid to date value', $key, json_encode($meeting)), array( 'status' => 400 ) );
			} 
		}

		public function validate_event($event, $request, $key) {
			if ( empty($event['name']) || 				
				intval($event['distanceId']) < 0 ||
				(!filter_var($event['website'], FILTER_VALIDATE_URL) !== FALSE && !empty($event['website']))) {				
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid values', $key, json_encode($event)), array( 'status' => 400 ) );
			} else {
				return true;
			}
		}
		
		public function validate_runner($runner, $request, $key) {			
			if ( empty($runner['name'])) {				
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid name value.', $key, json_encode($runner)), array( 'status' => 400 ) );
			} 
						
			if (intval($runner['sexId']) < 0) {				
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid sexId value', $key, json_encode($runner)), array( 'status' => 400 ) );
			} 
			
			if ($runner['isCurrentMember'] < 0 ||
				$runner['isCurrentMember'] > 1) {				
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid isCurrentMember value', $key, json_encode($runner)), array( 'status' => 400 ) );
			} 
			
			$date=date_parse($runner['dateOfBirth']);
			if (checkdate($date['month'], $date['day'], $date['year']) === FALSE) {				
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid dateOfBirth value', $key, json_encode($runner)), array( 'status' => 400 ) );
			} else {
				return true;
			}
		}
		
		public function validate_result($result, $request, $key) {					
			if (intval($result['eventId']) < 1) {				
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid eventId value', $key, json_encode($result)), array( 'status' => 400 ) );
			}
			
			if (intval($result['runnerId']) < 1) {				
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid runnerId value', $key, json_encode($result)), array( 'status' => 400 ) );
			}		
			
			if (intval($result['position']) < 0) {				
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid position value', $key, json_encode($result)), array( 'status' => 400 ) );
			}
			
			if (intval($result['team']) < 0) {				
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid team value', $key, json_encode($result)), array( 'status' => 400 ) );
			}
			
			if ($result['isGrandPrixResult'] < 0 ||
				$result['isGrandPrixResult'] > 1) {				
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid isGrandPrixResult value', $key, json_encode($result)), array( 'status' => 400 ) );
			}
				
			$date=date_parse($result['date']);
			if (checkdate($date['month'], $date['day'], $date['year']) === FALSE) {				
				return new \WP_Error( 'rest_invalid_param',
					sprintf( '%s %s has invalid date value', $key, json_encode($result)), array( 'status' => 400 ) );
			} 
			
			if (strpos($result['result'], ':') !== false) {
				$time = explode(":", $result['result']);	

				if ($time[0] < 0 || $time[1] < 0 || $time[2] < 0 || $time[1] > 59 || $time[2] > 59){ 					
					return new \WP_Error( 'rest_invalid_param',
						sprintf( '%s %s has invalid time value', $key, json_encode($result)), array( 'status' => 400 ) );
				} else {
					return true;
				}
			} else {
				// TODO validate distance (meters)
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
		
		public function login(\WP_REST_Request $request) {
			$username = base64_decode($request['username']);
			$password = base64_decode($request['password']);
			
			$this->user = wp_authenticate( $username, $password );
			
			return $this->user;
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
<?php
/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace IpswichJAFFARunningClubAPI\V2;
	
require_once plugin_dir_path( __FILE__ ) .'config.php';

class ResultsDataAccess {		

	private $jdb;

	public function __construct() {
		
		$this->jdb = new \wpdb(JAFFA_RESULTS_DB_USER, JAFFA_RESULTS_DB_PASSWORD, JAFFA_RESULTS_DB_NAME, DB_HOST);		
		$this->jdb->show_errors();
	}
	
	public function getDistances() {		
			 $sql = 'SELECT id, distance as text, result_measurement_unit_type_id as resultMeasurementUnitTypeId, miles FROM distance';

			 $results = $this->jdb->get_results($sql, OBJECT);

			 if (!$results)	{			
				 return new \WP_Error( 'ipswich_jaffa_api_getDistances',
						 'Unknown error in reading results from the database', 
						 array( 
							'status' => 500 
						));			
			 }

			 return $results;
		 } // end function getDistances
		 
	public function getCourseTypes() {

			$sql = 'SELECT id, description FROM `course_type` ORDER BY id ASC';

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getCourseTypes',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		 
	public function getEvents() {

		$sql = 'SELECT e.id as id, e.name as name, e.website, MAX(ra.date) as lastRaceDate, count( r.id ) AS count 
			FROM `events` e 
			LEFT JOIN `race` ra on ra.event_id = e.id 
			LEFT JOIN `results` r ON ra.id = r.race_id 
			GROUP BY e.id, e.name, e.website 
			ORDER BY lastRaceDate DESC, e.name ASC ';

		$results = $this->jdb->get_results($sql, OBJECT);

		if (!$results)	{			
			return new \WP_Error( 'ipswich_jaffa_api_getEvents',
					'Unknown error in reading results from the database', array( 'status' => 500 ) );			
		}

		return $results;
	}
	
	public function insertEvent($event)	{			
		$sql = $this->jdb->prepare('INSERT INTO events (`name`, `website`) VALUES(%s, %s);', $event['name'], $event['website']);

		$result = $this->jdb->query($sql);

		if ($result)
		{
			return $this->getEvent($this->jdb->insert_id);
		}

		return new \WP_Error( 'ipswich_jaffa_api_insertEvent',
					'Unknown error in inserting event in to the database', array( 'status' => 500 ) );
	}
	
	public function getRace($raceId) {

		$sql = $this->jdb->prepare(
				'SELECT ra.id, e.id AS eventId, e.Name as eventName, ra.description as description, ra.date, ra.course_type_id AS courseTypeId, c.description AS courseType, ra.area, ra.county, ra.country_code AS countryCode, ra.conditions, ra.venue, d.distance, ra.grand_prix as isGrandPrixRace, ra.course_number as courseNumber, ra.meeting_id as meetingId, d.result_measurement_unit_type_id as resultMeasurementUnitTypeId
				FROM `events` e
				INNER JOIN `race` ra ON ra.event_id = e.id
				LEFT JOIN `distance` d ON ra.distance_id = d.id
				LEFT JOIN `course_type` c ON ra.course_type_id = c.id
				WHERE ra.id = %d', $raceId);

		$results = $this->jdb->get_row($sql, OBJECT);

		if (!$results)	{			
			return new \WP_Error( 'ipswich_jaffa_api_getRaces',
					'Unknown error in reading race from the database', array( 'status' => 500 ) );			
		}

		return $results;
	}
		
	public function insertRace($race) {			
		$sql = $this->jdb->prepare('INSERT INTO `race`(`event_id`, `date`, `course_number`, `venue`, `description`, `conditions`, `distance_id`, `course_type_id`, `county`, `country_code`, `area`, `grand_prix`) VALUES(%d, %s, %s, %s, %s, %s, %d, %d, %s, %s, %s, %d)', $race['eventId'], $race['date'], $race['courseNumber'], $race['venue'], $race['description'], $race['conditions'], $race['distanceId'], $race['courseTypeId'], $race['county'], $race['countryCode'], $race['area'], $race['isGrandPrixRace']);

		$result = $this->jdb->query($sql);

		if ($result)
		{
			return $this->getRace($this->jdb->insert_id);
		}

		return new \WP_Error( 'ipswich_jaffa_api_insertRace',
					'Unknown error in inserting race in to the database', array( 'status' => 500 ) );
	}
		
	public function getEvent($eventId) {
		// Get updated event
		$sql = $this->jdb->prepare("SELECT e.id, e.name,  e.website FROM `events` e WHERE e.id = %d", $eventId);

		$result = $this->jdb->get_row($sql, OBJECT);
		
		if ($result) return $result;
		
		return new \WP_Error( 'ipswich_jaffa_api_getEvent',
					'Unknown error in getting the event in to the database', array( 'status' => 500 ) );
	}

	public function updateEvent($eventId, $field, $value) {		

		// Only name and website may be changed.
		if ($field == 'name' || $field == 'website') 
		{
			$result = $this->jdb->update( 
				'events', 
				array( 
					$field => $value
				), 
				array( 'id' => $eventId ), 
				array( 
					'%s'
				), 
				array( '%d' ) 
			);

			if ($result)
			{
				// Get updated event
				return $this->getEvent($eventId);
			}
			
			return new \WP_Error( 'ipswich_jaffa_api_updateEvent',
					'Unknown error in updating event in to the database'.$sql, array( 'status' => 500 ) );
		}

		return new \WP_Error( 'ipswich_jaffa_api_updateEvent',
					'Field in event may not be updated', array( 'status' => 500 ) );
	}
		
	public function getRaces($eventId) {
		$sql = $this->jdb->prepare(
				'SELECT ra.id, e.id AS eventId, e.Name as name, ra.date, ra.description, ra.course_type_id AS courseTypeId, c.description AS courseType, ra.area, ra.county, ra.country_code AS countryCode, ra.conditions, ra.venue, d.distance, ra.grand_prix as isGrandPrixRace, ra.course_number as courseNumber, ra.meeting_id as meetingId, d.result_measurement_unit_type_id as resultMeasurementUnitTypeId, count(r.id) as count
				FROM `events` e
				INNER JOIN `race` ra ON ra.event_id = e.id
                LEFT JOIN `results` r ON ra.id = r.race_id
				LEFT JOIN `distance` d ON ra.distance_id = d.id
				LEFT JOIN `course_type` c ON ra.course_type_id = c.id
				WHERE e.id = %d
				GROUP BY ra.id, eventId, name, ra.date, ra.description, courseTypeId, courseType, ra.area, ra.county, countryCode, ra.conditions, ra.venue, d.distance, isGrandPrixRace
				ORDER BY ra.date DESC, ra.description', $eventId);

		$results = $this->jdb->get_results($sql, OBJECT);
		
		return $results;
	}

	public function updateRaceDistance($raceId, $distanceId) {
		$results = $this->getRaceResults($raceId);
		
		// Update race distance
		$success = $this->jdb->update( 
						'race', 
						array( 
							'distance_id' => $distanceId
						), 
						array( 'id' => $raceId ), 
						array( 
							'%d'
						), 
						array( '%d' ) 
					);
		
		// For each race result
		for ($i = 0; $i < count($results); $i++) {		
			// Update result, percentage grading and standard
			$existingResult = $results[$i]->result;
			$pb = 0;
			$seasonBest = 0;
			$standardType = 0;
	
			if (!($existingResult == "00:00:00" || $existingResult == "" || $existingResult == null)) {
				$pb = $this->isPersonalBest($raceId, $results[$i]->runnerId, $existingResult, $results[$i]->date);
				
				$seasonBest = $this->isSeasonBest($raceId, $results[$i]->runnerId, $existingResult, $results[$i]->date);
								
				if ($results[$i]->date < '2017-01-01') {
					$standardType = $this->getStandardTypeId($results[$i]->categoryId, $existingResult, $raceId);				
				}
			}
			
			$success = $this->jdb->update( 
				'results', 
				array( 
					'personal_best' => $pb,
					'season_best' => $seasonBest,
					'standard_type_id' => $standardType
				), 
				array( 'id' => $results[$i]->id ), 
				array( 
					'%d',
					'%d',
					'%d'
				), 
				array( '%d' ) 
			);							

			if ($success)
			{
				$this->updateAgeGrading($results[$i]->id, $raceId, $results[$i]->runnerId);
				
				if ($results[$i]->date >= '2017-01-01') {
					$this->update2015FactorsAgeGrading($results[$i]->id, $raceId, $results[$i]->runnerId);
					$this->updateResultStandardType($results[$i]->id);					
				}
			
				// If a PB query to see whether a new certificate is required.
				if ($pb == true)
				{
					$isNewStandard = $this->isNewStandard($results[$i]->id);

					if ($isNewStandard == true)
					{
						$this->saveStandardCertificate($results[$i]->id);
					}
				}					
			}
		}
		
		if ($success)
		{
			// Get updated race
			return $this->getRace($raceId);
		}
		
		return new \WP_Error( 'ipswich_jaffa_api_updateRaceDistance',
				'Unknown error in updating race in to the database', array( 'status' => 500 ) );
	}

	public function updateRace($raceId, $field, $value) {		
			// Race date and distance can not be changed - affected PBs etc
			if ($field == 'event_id' || 
			    $field == 'description' || 
				$field == 'course_type_id' || 
				$field == 'course_number' || 
				$field == 'area' || 
				$field == 'county' ||
				$field == 'country_code' || 
				$field == 'venue' || 
				$field == 'conditions' || 
				$field == 'meeting_id' || 
				$field == 'grand_prix') 
			{
				if ($field == 'country_code' && $value != 'GB') {
					$result = $this->jdb->update( 
						'race', 
						array( 
							$field => $value, 'county' => null, 'area' => null
						), 
						array( 'id' => $raceId ), 
						array( 
							'%s', '%s', '%s'
						), 
						array( '%d' ) 
					);				
				} else {
					$result = $this->jdb->update( 
						'race', 
						array( 
							$field => $value
						), 
						array( 'id' => $raceId ), 
						array( 
							'%s'
						), 
						array( '%d' ) 
					);
				}

				if ($result)
				{
					// Get updated race
					return $this->getRace($raceId);
				}
				
				return new \WP_Error( 'ipswich_jaffa_api_updateRace',
						'Unknown error in updating event in to the database'.$sql, array( 'status' => 500 ) );
			}

			return new \WP_Error( 'ipswich_jaffa_api_updateRace',
						'Field in event may not be updated', array( 'status' => 500 , 'Field' => $field, 'Value' => $value) );
		}

		public function updateRunner($runnerId, $field, $value) {		
			if ($field == 'name' || $field == 'current_member') 
			{
				$result = $this->jdb->update( 
					'runners', 
					array( 
						$field => $value
					), 
					array( 'id' => $runnerId ), 
					array( 
						'%s'
					), 
					array( '%d' ) 
				);
				if ($result)
				{
					return $this->getRunner($runnerId);
				}
				
				return new \WP_Error( 'ipswich_jaffa_api_updateRunner',
						'Unknown error in updating runner in to the database'.$sql, array( 'status' => 500 ) );
			}
			return new \WP_Error( 'ipswich_jaffa_api_updateRunner',
						'Field in event may not be updated', array( 'status' => 500 , 'Field' => $field, 'Value' => $value) );
		}

	public function updateResult($resultId, $field, $value) {		
		// Only name and website may be changed.
		if ($field == 'info' || $field == 'position' || $field == "scoring_team" || $field == 'race_id') 
		{
			$result = $this->jdb->update( 
				'results', 
				array( 
					$field => $value
				), 
				array( 'id' => $resultId ), 
				array( 
					'%s'
				), 
				array( '%d' ) 
			);

			if ($result !== false)
			{
				return $this->getResult($resultId);
			}
			
			return new \WP_Error( 'ipswich_jaffa_api_updateResult',
					'Unknown error in updating result in to the database.', array( 'status' => 500 , 'code' => 001) );
		} else if ($field == 'result') {
			// Update result, percentage grading and standard
			$existingResult = $this->getResult($resultId);
			$pb = 0;
			$seasonBest = 0;
			$standardType = 0;
	
			if ($this->isCertificatedCourseAndResult($existingResult->raceId, $existingResult->courseNumber, $value)) {
				$pb = $this->isPersonalBest($existingResult->raceId, $existingResult->runnerId, $value, $existingResult->date);
				
				$seasonBest = $this->isSeasonBest($existingResult->raceId, $existingResult->runnerId, $value, $existingResult->date);

				if ($existingResult->date < '2017-01-01') {
					$standardType = $this->getStandardTypeId($existingResult->categoryId, $value, $existingResult->raceId);				
				}
			}
			
			$success = $this->jdb->update( 
				'results', 
				array( 
					'result' => $value,
					'personal_best' => $pb,
					'season_best' => $seasonBest,
					'standard_type_id' => $standardType
				), 
				array( 'id' => $resultId ), 
				array( 
					'%s', 
					'%d',
					'%d',
					'%d'
				), 
				array( '%d' ) 
			);							

			if ($result !== false)
			{
				$this->updateAgeGrading($resultId, $existingResult->raceId, $existingResult->runnerId);
			
				if ($existingResult->date >= '2017-01-01') {
					$this->update2015FactorsAgeGrading($resultId, $existingResult->raceId, $existingResult->runnerId);
					$this->updateResultStandardType($resultId);					
				}
				
				// If a PB query to see whether a new certificate is required.
				if ($pb == true)
				{
					$isNewStandard = $this->isNewStandard($resultId);

					if ($isNewStandard == true)
					{
						$this->saveStandardCertificate($resultId);
					}
				}
		
				return $this->getResult($resultId);
			}
			
			return new \WP_Error( 'ipswich_jaffa_api_updateResult',
					'Unknown error in updating result in to the database', array( 'status' => 500, 'code' => 002 ) );
		}

		return new \WP_Error( 'ipswich_jaffa_api_updateResult',
					'Field in result may not be updated', array( 'status' => 500, 'code' => 003 ) );
	}

		public function deleteEvent($eventId, $deleteResults) {		

			$sql = $this->jdb->prepare('SELECT COUNT(r.id) FROM results r INNER JOIN race ra ON ra.id = r.race_id WHERE ra.event_id = %d LIMIT 1;',$eventId);

			$exists = $this->jdb->get_var($sql); // $jdb->get_var returns a single value from the database. In this case 1 if the find term exists and 0 if it does not.

			if ($exists != 0) {
				if (empty($deleteResults)) {
					return new \WP_Error( 'ipswich_jaffa_api_validation',
						'Event cannot be deleted; a number results are associated with this event. Delete the existing results for this event and try again.', array( 'status' => 500 ) );
				}
				
				// Delete all associated results
				$result = $this->deleteEventResults($eventId);				
				if ($result != true) return $result;
			}		

			$sql = $this->jdb->prepare('DELETE FROM events WHERE id = %d;', $eventId);

			$result = $this->jdb->query($sql);

			if (!$result) {			
				return new \WP_Error( 'ipswich_jaffa_api_deleteEvent',
						'Unknown error in deleting event from the database', array( 'status' => 500, 'sql' => $sql) );			
			}	

			return $result;
		} // end function deleteEvent
			
		// TODO - change
		private function deleteEventResults($eventId) {
			$sql = $this->jdb->prepare('DELETE FROM results WHERE event_id = %d;', $eventId);

			$result = $this->jdb->query($sql);

			if (!$result) {			
				return new \WP_Error( 'ipswich_jaffa_api_deleteEventResults',
						'Unknown error in deleting results from the database', array( 'status' => 500 ) );			
			}

			return true;
		}
		
		public function deleteResult($resultId) {
			$sql = $this->jdb->prepare('DELETE FROM results WHERE id = %d;', $resultId);

			$result = $this->jdb->query($sql);

			if (!$result) {			
				return new \WP_Error( 'ipswich_jaffa_api_deleteResult',
						'Unknown error in deleting results from the database', array( 'status' => 500 ) );			
			}

			return true;
		}
		
		public function deleteRace($raceId) {
			$sql = $this->jdb->prepare('DELETE FROM race WHERE id = %d;', $raceId);

			$result = $this->jdb->query($sql);

			if (!$result) {			
				return new \WP_Error( 'ipswich_jaffa_api_deleteRace',
						'Unknown error in deleting race from the database', array( 'status' => 500 ) );			
			}

			return true;
		}
		
		public function getRunners($loggedIn = true) {
			
			if ($loggedIn === true) {
				$sql = "SELECT r.id, r.name, r.sex_id as 'sexId', r.dob as 'dateOfBirth', r.current_member as 'isCurrentMember', s.sex FROM `runners` r, `sex` s WHERE r.sex_id = s.id ORDER BY r.name";
			} else {
				$sql = "SELECT r.id, r.name, r.sex_id as 'sexId', s.sex FROM `runners` r, `sex` s WHERE r.sex_id = s.id ORDER BY r.name";
			}

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getRunners',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getRunner($runnerId) {
			$sql = $this->jdb->prepare("select r.id, r.name, r.sex_id as 'sexId', r.dob as 'dateOfBirth', r.current_member as 'isCurrentMember', s.sex, c.code as 'ageCategory'
				FROM 
				runners r, category c, sex s
				WHERE r.id = %d
				AND r.sex_id = s.id 
				AND r.sex_id = c.sex_id
				AND (
					(TIMESTAMPDIFF(YEAR, r.dob, CURDATE()) >= c.age_greater_equal AND TIMESTAMPDIFF(YEAR, r.dob, CURDATE()) < c.age_less_than) 
					OR r.dob= '0000-00-00'
				)
				LIMIT 1", $runnerId);
					
			$results = $this->jdb->get_row($sql, OBJECT);
			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getRunner',
						'Unknown error in reading runner from the database', array( 'status' => 500 ) );			
			}
			return $results;
		}
		
		public function insertRunner($runner) {
		
			$sql = $this->jdb->prepare('INSERT INTO runners (`membership_no`, `name`, `dob`, `sex_id`, `current_member`, `club_id`) VALUES(0, %s, %s, %d, %d, 439);', $runner['name'], $runner['dateOfBirth'], $runner['sexId'], $runner['isCurrentMember']);
 
			$result = $this->jdb->query($sql);

			if ($result) {
				return $this->getRunner($this->jdb->insert_id);
			}

			return new \WP_Error( 'ipswich_jaffa_api_insertRunner',
						'Unknown error in inserting runner in to the database', array( 'status' => 500 ) );
		} // end function addRunner
		
		public function deleteRunner($id) {
		
			// Check whether their are any results for this runner already.
			$sql = $this->jdb->prepare('SELECT COUNT(`id`) FROM results WHERE runner_id = %d LIMIT 1;', $id);

			$exists = $this->jdb->get_var($sql);

			if ($exists != 0) {
				// Runners cannot be deleted; a number results are associated with this runner. Delete these results first and then try again.

				return new \WP_Error( 'ipswich_jaffa_api_validation',
							'Runner cannot be deleted; a number results are associated with this runner. Delete the existing results for this runner and try again.', array( 'status' => 409 ) );
			}
			
			$sql = $this->jdb->prepare('DELETE FROM runners WHERE id = %d;', $id);

			$result = $this->jdb->query($sql);

			if (!$result) {			
				return new \WP_Error( 'ipswich_jaffa_api_deleteRunner',
						'Unknown error in deleting runner from the database', array( 'status' => 500 ) );			
			}	

			return $result;
		} // end function deleteRunner
	
		public function getGenders(){
		
			$sql = 'SELECT * FROM sex ORDER BY sex';

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getGenders',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		} // end function getGenders
		
		public function insertResult($result) {
	
			$categoryId = $this->getCategoryId($result['runnerId'], $result['date']);
			$pb = 0;
			$seasonBest = 0;
			$standardType = 0;
			
			if ($this->isCertificatedCourseAndResult($result['raceId'], $result['courseId'], $result['result'])) {
				$pb = $this->isPersonalBest($result['raceId'], $result['runnerId'], $result['result'], $result['date']);
				
				$seasonBest = $this->isSeasonBest($result['raceId'], $result['runnerId'], $result['result'], $result['date']);

				if ($result['date'] < '2017-01-01') {
					$standardType = $this->getStandardTypeId($categoryId, $result['result'], $result['raceId']);				
				}
			}
			
			$sql = $this->jdb->prepare('INSERT INTO results (`result`, `event_id`, `racedate`, `info`, `runner_id`, `club_id`, `position`, `category_id`, `personal_best`, `season_best`, `standard_type_id`, `grandprix`, `scoring_team`, `race_id`) VALUES(%s, %d, %s, %s, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d)', $result['result'], $result['eventId'], $result['date'], $result['info'], $result['runnerId'], 439, $result['position'], $categoryId, $pb, $seasonBest, $standardType, $result['isGrandPrixResult'], $result['team'] != null ? $result['team'] : 0, $result['raceId']);
					
			$success = $this->jdb->query($sql);

			if (!$success) {
				return new \WP_Error( 'ipswich_jaffa_api_insertResult',
					'Unknown error in inserting results in to the database : ', array( 'status' => 500 , 'sql' => $sql) );	
			}

			// Get the ID of the inserted event
			$resultId = $this->jdb->insert_id;
						
			$response = $this->updateAgeGrading($resultId, $result['raceId'], $result['runnerId']);

			if ($response != true)
				return $response;
				
			if ($result['date'] >= '2017-01-01') {
				$this->update2015FactorsAgeGrading($resultId, $result['raceId'], $result['runnerId']);
				$this->updateResultStandardType($resultId);				
			}
								
			// If a PB query to see whether a new certificate is required.
			if ($pb == true)
			{
				$isNewStandard = $this->isNewStandard($resultId);

				if ($isNewStandard == true)
				{
					$this->saveStandardCertificate($resultId);
				}
			}					

			return $this->getResult($resultId);			
		}
	
		public function getResults($eventId, $fromDate, $toDate, $numberOfResults) {
			
			if (empty($eventId)) {
				$whereEvent = '';				
			} else {
				$whereEvent = ' AND ra.event_id = '.$eventId;
			}
			
			if (empty($fromDate)) {
				$whereFrom = '';				
			} else {
				$whereFrom = " AND ra.date >= '$fromDate'";
			}
			
			if (empty($toDate)) {
				$whereTo = '';				
			} else {
				$whereTo = " AND ra.date <= '$toDate'";
			}
			
			$limit = abs(intval($numberOfResults));
			
			if ($limit <= 0)
				$limit = 100;

			$sql = "SELECT r.id, ra.event_id as 'eventId', r.runner_id as 'runnerId', r.position, ra.date as 'date', r.result as 'time', r.result as 'result', r.info, r.event_division_id as 'eventDivisionId', r.standard_type_id as 'standardTypeId', r.category_id as 'categoryId', r.personal_best as 'isPersonalBest', r.season_best as 'isSeasonBest', r.grandprix as 'isGrandPrixResult',
			r.scoring_team as 'team',
			CASE
			   WHEN ra.date >= '2017-01-01' THEN r.percentage_grading_2015
			   ELSE r.percentage_grading
			END as percentageGrading,
			p.name as 'runnerName',
			e.name as 'eventName', ra.description as 'raceDescription' 
			FROM results r
			INNER JOIN runners p on p.id = r.runner_id
			INNER JOIN race ra ON r.race_id = ra.id
			INNER JOIN events e ON ra.event_id = e.id
			WHERE 1=1 $whereEvent $whereFrom $whereTo
			ORDER BY ra.date DESC, ra.id, r.position ASC, r.result ASC LIMIT $limit";
							
			$results = $this->jdb->get_results($sql, OBJECT);

			if ($this->jdb->num_rows == 0)
				return null;
			
			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getResults',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getRaceResults($raceId) {
			
			$sql = "SELECT r.id, r.runner_id as 'runnerId', r.position, r.result as 'time', r.result as 'result', r.info, s.name as standardType, c.code as categoryCode, r.personal_best as 'isPersonalBest', r.season_best as 'isSeasonBest', 
			r.scoring_team as 'team',
			CASE
			   WHEN r.racedate >= '2017-01-01' THEN r.percentage_grading_2015
			   ELSE r.percentage_grading
			END as percentageGrading,
			p.name as 'runnerName', r.race_id as raceId, c.id as categoryId, r.racedate as 'date'
			FROM results r
			INNER JOIN runners p on r.runner_id = p.id 
			LEFT JOIN standard_type s on s.id = r.standard_type_id
			LEFT JOIN category c ON c.id = r.category_id
			WHERE r.race_id = $raceId 
			ORDER BY r.position ASC, r.result ASC";
							
			$results = $this->jdb->get_results($sql, OBJECT);

			if ($this->jdb->num_rows == 0)
				return null;
			
			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getRaceResults',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
				
        public function getPreviousPersonalBest($runnerIds, $newRaceId) {
      $sql = "SELECT r1.runner_id as runnerId, MIN(r2.result) as previousBest 
              FROM `results` r1 
              INNER JOIN `race` ra1 ON r1.race_id = ra1.id
              inner join `results` r2 on r1.runner_id = r2.runner_id AND r1.racedate > r2.racedate AND r2.personal_best = 1
              INNER JOIN `race` ra2 ON r2.race_id = ra2.id
              where r1.race_id = $newRaceId
              and r1.personal_best = 1
              AND ra1.distance_id = ra2.distance_id
              AND r1.runner_id in ($runnerIds)
              GROUP BY r1.runner_id";
							
			$results = $this->jdb->get_results($sql, OBJECT);

			if ($this->jdb->num_rows == 0)
				return null;
			
			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getPreviousPersonalBest',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
    }
				
		public function getResult($resultId) {
						
			$sql = "SELECT r.id, r.event_id as 'eventId', r.runner_id as 'runnerId', r.position, r.racedate as 'date', r.result as 'time', r.result as 'result', r.info, r.event_division_id as 'eventDivisionId', r.standard_type_id as 'standardTypeId', r.category_id as 'categoryId', r.personal_best as 'isPersonalBest', r.season_best as 'isSeasonBest', r.grandprix as 'isGrandPrixResult', 
			r.scoring_team as 'team', ra.id as 'raceId', p.sex_id, e.name as 'eventName',
			CASE
			   WHEN ra.date >= '2017-01-01' THEN r.percentage_grading_2015
			   ELSE r.percentage_grading
			END as percentageGrading,
			ra.course_number as 'courseNumber', p.name as 'runnerName', e.name as 'eventName', ra.description as 'raceDescription' 
			FROM results r
			INNER JOIN runners p on p.id = r.runner_id
			INNER JOIN race ra ON r.race_id = ra.id
			INNER JOIN events e ON ra.event_id = e.id
			WHERE r.id = $resultId
			ORDER BY ra.date DESC, ra.id, r.position ASC, r.result ASC";
							
			$results = $this->jdb->get_row($sql, OBJECT);

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getResult',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function insertRunnerOfTheMonthWinners($runnerId, $category, $month, $year) {
			$sql = $this->jdb->prepare("insert into runner_of_the_month_winners set runner_id=%d, category='%s', month=%d, year=%d",
                        $runnerId, $category, $month, $year);			
 
			$result = $this->jdb->query($sql);

			if ($result) {
				return true;
			}

			return new \WP_Error( 'ipswich_jaffa_api_insertRunnerOfTheMonthWinners',
						'Unknown error in inserting runner in to the database', array( 'status' => 500 ) );
		}
		
		public function insertRunnerOfTheMonthVote($vote) {
			$sql = $this->jdb->prepare("insert into runner_of_the_month_votes 
										set 
										runner_id=%d, 
										reason='%s',
										category='%s',
										month=%d,
										year=%d,
										voter_id=%d,
										ip_address='%s',
										created='%s'",
                    $vote['runnerId'], $vote['reason'], $vote['category'], $vote['month'], $vote['year'], $vote['voterId'], $vote['ipAddress'], $vote['created']);			
 
			$result = $this->jdb->query($sql);

			if ($result) {
				return true;
			}

			return new \WP_Error( 'ipswich_jaffa_api_insertRunnerOfTheMonthVote',
						'Unknown error in inserting runner in to the database', array( 'status' => 500 ) );
		}
		
		public function getResultsByYearAndCounty() {
			$sql = "SELECT YEAR(ra.date) as year, ra.county, count(r.id) as count FROM `race` ra INNER join results r on ra.id = r.race_id WHERE ra.county IS NOT NULL GROUP BY YEAR(ra.date), ra.county ORDER BY `year` ASC";

			$results = $this->jdb->get_results($sql, OBJECT);
			
			if ($this->jdb->num_rows == 0)
				return null;

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getResultsByYearAndCounty',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getResultsByYearAndCountry() {
			$sql = "SELECT YEAR(ra.date) as year, ra.country_code, count(r.id) as count FROM `race` ra INNER join results r on ra.id = r.race_id WHERE ra.country_code IS NOT NULL GROUP BY YEAR(ra.date), ra.country_code ORDER BY `year` ASC";

			$results = $this->jdb->get_results($sql, OBJECT);
			
			if ($this->jdb->num_rows == 0)
				return null;

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getResultsByYearAndCountry',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getResultsCountByYear() {
			$sql = "SELECT YEAR(ra.date) as year, count(r.id) as count FROM results r INNER JOIN race ra ON ra.id = r.race_id GROUP BY YEAR(ra.date) ORDER BY `year` DESC";

			$results = $this->jdb->get_results($sql, OBJECT);
			
			if ($this->jdb->num_rows == 0)
				return null;

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getResultsCountByYear',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getPersonalBestTotals() {
			$sql = "SELECT p.id as runnerId, p.name, count(r.id) as count, MIN(ra.date) AS firstPB, MAX(ra.date) AS lastPB FROM `results` r inner join runners p on r.runner_id = p.id INNER JOIN race ra ON ra.id = r.race_id where r.personal_best = 1 group by runnerId, p.name order by count DESC limit 50";

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getPersonalBestTotals',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getPersonalBestTotalByYear() {
			$sql = "SELECT count(*) AS count, YEAR(ra.date) as year from results r INNER JOIN race ra ON ra.id = r.race_id where r.personal_best = 1 GROUP by year order by year desc";

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getPersonalBestTotalByYear',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getTopAttendedRaces() {
			$sql = "SELECT e.id as eventId, e.name, ra.date, count(r.id) as count 
					FROM `results` r 					
					INNER JOIN race ra ON ra.id = r.race_id 
					inner join events e on ra.event_id = e.id 
					group by eventId, e.name, ra.date 
					order by count desc limit 50";

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getTopAttendedRaces',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getTopMembersRacing() {
			$sql = "SELECT p.id as runnerId, p.name, count(r.id) as count FROM `results` r inner join runners p on r.runner_id = p.id group by runnerId, p.name order by count desc limit 50";

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getTopMembersRacing',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getTopMembersRacingByYear() {
			$sql = "select YEAR(ra.date) AS year, count(r.id) AS count, p.id as runnerId, p.name from results r inner join runners p on p.id = r.runner_id INNER JOIN race ra ON ra.id = r.race_id group by year, runnerId, p.name order by count DESC, year ASC LIMIT 10";

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getTopMembersRacingByYear',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		private function updateResultStandardType($resultId) {

			$sql = $this->jdb->prepare("update results set standard_type_id = 
			CASE
				WHEN percentage_grading_2015 >= 86 THEN 14
				WHEN percentage_grading_2015 >= 80 THEN 15
				WHEN percentage_grading_2015 >= 74 THEN 16
				WHEN percentage_grading_2015 >= 68 THEN 17
				WHEN percentage_grading_2015 >= 62 THEN 18
				WHEN percentage_grading_2015 >= 56 THEN 19
				WHEN percentage_grading_2015 >= 50 THEN 20
				ELSE 0
			END
			WHERE id = %d AND racedate >= '2017-01-01'",
			$resultId);

			$result = $this->jdb->query($sql);

			if (!$result) {
				return new \WP_Error( 'ipswich_jaffa_api_updateResultStandardType',
					'Unknown error in updating standard type results from the database', array( 'status' => 500 ) );	
			}

			return true;
		}
		
		private function updateAgeGrading($resultId, $raceId, $runnerId) {

			$sql = $this->jdb->prepare("update wma_age_grading g,
 results r,
 distance d,
 wma_records a,
 runners p,
race ra
set r.percentage_grading = 
CASE
	WHEN d.result_measurement_unit_type_id >= 2 THEN
	 (ROUND((a.record * 100) / (r.result * g.grading_percentage), 2))
	ELSE
 (ROUND((a.record * 100) / (((substring(r.result, 1, 2) * 3600) +  (substring(r.result, 4, 2) * 60) + (substring(r.result, 7, 2))) * g.grading_percentage), 2))
END
WHERE g.distance_id = a.distance_id
AND d.id = ra.distance_id
AND a.distance_id = ra.distance_id
AND r.race_id = ra.id
AND g.age = (YEAR(ra.date) - YEAR(p.dob) - IF(DATE_FORMAT(p.dob, '%%j') > DATE_FORMAT(ra.date, '%%j'), 1, 0))
AND g.sex_id = p.sex_id 
AND g.sex_id = a.sex_id
AND r.runner_id = p.id
AND r.runner_id = %d
AND p.dob <> '0000-00-00'
AND p.dob is not null
and r.result <> '00:00:00'
and ra.id = %d
and ra.distance_id <> 0
and r.id = %d", $runnerId, $raceId, $resultId);

			$result = $this->jdb->query($sql);

			if (!$result) {
				return new \WP_Error( 'ipswich_jaffa_api_updateAgeGrading',
					'Unknown error in updating age grading results from the database', array( 'status' => 500 ) );	
			}

			return true;
		}
		
		private function update2015FactorsAgeGrading($resultId, $raceId, $runnerId) {

			$sql = $this->jdb->prepare("update wma_age_grading_2015 g,
 results r,
 distance d,
 wma_records_2015 a,
 runners p,
race ra
set r.percentage_grading_2015 = 
CASE
	WHEN d.result_measurement_unit_type_id >= 2 THEN
	 (ROUND((a.record * 100) / (r.result * g.grading_percentage), 2))
	ELSE
 (ROUND((a.record * 100) / (((substring(r.result, 1, 2) * 3600) +  (substring(r.result, 4, 2) * 60) + (substring(r.result, 7, 2))) * g.grading_percentage), 2))
END
WHERE g.distance_id = a.distance_id
AND d.id = ra.distance_id
AND a.distance_id = ra.distance_id
AND r.race_id = ra.id
AND g.age = (YEAR(ra.date) - YEAR(p.dob) - IF(DATE_FORMAT(p.dob, '%%j') > DATE_FORMAT(ra.date, '%%j'), 1, 0))
AND g.sex_id = p.sex_id 
AND g.sex_id = a.sex_id
AND r.runner_id = p.id
AND r.runner_id = %d
AND p.dob <> '0000-00-00'
AND p.dob is not null
and r.result <> '00:00:00'
and ra.id = %d
and ra.distance_id <> 0
and ra.course_type_id = a.course_type_id
and ra.course_type_id = g.course_type_id
and r.id = %d", $runnerId, $raceId, $resultId);

			$result = $this->jdb->query($sql);

			if (!$result) {
				return new \WP_Error( 'ipswich_jaffa_api_updateAgeGrading',
					'Unknown error in updating age grading results from the database', array( 'status' => 500 ) );	
			}

			return true;
		}

		public function getCategoryId($runnerId, $date) {
		
			$sql = $this->jdb->prepare("select c.id
					FROM 					
					runners p, category c
					WHERE p.id = %d
					AND p.sex_id = c.sex_id
					AND TIMESTAMPDIFF(YEAR, p.dob, '%s') >= c.age_greater_equal
					AND TIMESTAMPDIFF(YEAR, p.dob, '%s') < c.age_less_than
					LIMIT 1", $runnerId, $date, $date);

			$id = $this->jdb->get_var($sql);

			return $id;
		}
	
		private function isCertificatedCourseAndResult($raceId, $courseNumber = '', $result) {
			// TODO
			// First determine if a valid event and result to get a PB
			if ($result == "00:00:00" || $result == "00:00" || $result == "" || $result == null)
				return false;
				
			$sql = $this->jdb->prepare("select
								distance_id, course_type_id
								from							
								race ra
								where
								ra.id = %d", $raceId);

			$race = $this->jdb->get_row($sql);
			
			return $race->distance_id > 0 && ($race->course_type_id == 1 || $race->course_type_id == 3 || $race->course_type_id == 6) ;
		}

		private function isPersonalBest($raceId, $runnerId, $result, $date) {				
			$sql = $this->jdb->prepare("select
								count(r.id)
								from
								runners p,
								race ra1,
								race ra2,
								results r
								where
								ra1.id = r.race_id AND
								ra1.distance_id = ra2.distance_id AND
								ra2.id = %d AND
								ra1.distance_id <> 0 AND
								r.runner_id = p.id AND
								r.result != '00:00:00' AND
                r.result != '' AND
								r.result <= %s AND
								r.runner_id = %d AND
								r.race_id <> %d AND
								ra1.date < '%s' AND
                ra1.course_type_id IN (1, 3, 6) AND
                ra2.course_type_id IN (1, 3, 6)
								ORDER BY result
								LIMIT 1", $raceId, $result, $runnerId, $raceId, $date);

			$count = $this->jdb->get_var($sql);

			return ($count == 0);
		}	
	
		private function isSeasonBest($raceId, $runnerId, $result, $date) {
			$sql = $this->jdb->prepare("select
								count(r.id)
								from
								runners p,
								race ra,
								race ra2,
								results r
								where
								ra.id = r.race_id AND
								ra.distance_id = ra2.distance_id AND
								ra2.id = %d AND
								ra.distance_id <> 0 AND
								r.runner_id = p.id AND
								r.result != '00:00:00' AND
                r.result != '' AND
								r.result <= %s AND
								r.runner_id = %d AND
								YEAR(ra.date) = YEAR('%s') AND
								ra.date < '%s' AND
								r.race_id <> %d AND
                ra.course_type_id IN (1, 3, 6) AND
                ra2.course_type_id IN (1, 3, 6)
								ORDER BY result
								LIMIT 1", $raceId, $result, $runnerId, $date, $date, $raceId);

			$count = $this->jdb->get_var($sql);

			return ($count == 0);
		}			

		private function getStandardTypeId($catgeoryId, $result, $raceId) {
			$sql = $this->jdb->prepare("SELECT
									s.standard_type_id
								  FROM
									standard_type st,
									standards s,
									race ra
								  WHERE
									s.standard_type_id = st.id AND
									s.category_id = %d AND
									s.distance_id = ra.distance_id AND
									ra.id = %d AND
									'%s' <= s.standard AND
									st.obsolete = 0
								  ORDER BY
									s.standard
								   LIMIT 1", $catgeoryId, $raceId, $result);

			$standard = $this->jdb->get_var($sql);

			if (empty($standard))
				$standard = 0;

			return $standard;
		}

		private function isNewStandard($resultId) {
			// -- Match results of the same runner
			// -- Match results of the same distance
			// -- Find results with the same standard or better
			// -- Find results in the same age category
			// -- Find results only for first claim club
			// -- Only use the new standards - those 5+
			$sql = $this->jdb->prepare("SELECT  count(r2.id)
									   FROM results r1, results r2, race ra1, race ra2, runners p, category c1, category c2
									   WHERE r1.id = %d
							   AND r1.id != r2.id
							   AND r1.runner_id = r2.runner_id
							   AND r1.race_id = ra1.id
							   AND r2.race_id = ra2.id
							   AND ra1.distance_id = ra2.distance_id
							   AND r2.standard_type_id < r1.standard_type_id
							   AND r2.standard_type_id > 4
							   AND r2.runner_id = p.id
							   AND p.sex_id = c1.sex_id
							   AND (year(from_days(to_days(ra1.date)-to_days(p.dob) + 1)) >= c1.age_greater_equal
									   AND  year(from_days(to_days(ra1.date)-to_days(p.dob) + 1)) <  c1.age_less_than)
							   AND p.sex_id = c2.sex_id
							   AND (year(from_days(to_days(ra2.date)-to_days(p.dob) + 1)) >= c2.age_greater_equal
									   AND  year(from_days(to_days(ra2.date)-to_days(p.dob) + 1)) <  c2.age_less_than)
							   AND c1.id = c2.id",
							   $resultId);

			$count = $this->jdb->get_var($sql);

			return ($count == 0);
		}

		private function saveStandardCertificate($resultId) {
			$sql = $this->jdb->prepare("insert into standard_certificates set result_id=%d, issued = 0", $resultId);

			$result = $this->jdb->query($sql);

			if (!$result) {
				return new \WP_Error( 'ipswich_jaffa_api_isNewStandard',
						'Unknown error in inserting standard certificate in to the database', array( 'status' => 500 ) );	
			}
			
			return true;
		}
		
		public function getClubRecords($distanceId) {
			$sql = $this->jdb->prepare("           
				SELECT r.runner_id as runnerId, p.Name as runnerName, e.id as eventId, e.Name as eventName, ra.date, r.result, c.code as categoryCode, ra.id as raceId, ra.description, ra.venue
				FROM results AS r
        INNER JOIN race ra ON r.race_id = ra.id
				JOIN (
				  SELECT r1.runner_id, r1.result, MIN(ra1.date) AS earliest
				  FROM results AS r1 
          INNER JOIN race ra1 on r1.race_id = ra1.id
				  JOIN (
					SELECT MIN(r2.result) AS quickest, r2.category_id
					FROM results r2
					INNER JOIN race ra
					ON r2.race_id = ra.id
					INNER JOIN events e
					ON ra.event_id = e.id
					INNER JOIN `distance` d
					ON ra.distance_id = d.id
					INNER JOIN `runners` p2
					ON r2.runner_id = p2.id
					WHERE r2.result != '00:00:00' and d.id = %d and r2.category_id <> 0
          AND (ra.course_type_id NOT IN (2, 4, 5, 7) OR ra.course_type_id IS NULL)
					GROUP BY r2.category_id
				   ) AS rt
				   ON r1.result = rt.quickest and r1.category_id = rt.category_id
				   GROUP BY r1.runner_id, r1.result, r1.category_id
				   ORDER BY r1.result asc
				) as rd
				ON r.runner_id = rd.runner_id AND r.result = rd.result AND ra.date = rd.earliest
				
				INNER JOIN events e ON ra.event_id = e.id
				INNER JOIN runners p ON r.runner_id = p.id
				INNER JOIN category c ON r.category_id = c.id      
				WHERE c.age_less_than is NOT NULL and ra.distance_id = %d             
				ORDER BY c.age_less_than, c.sex_id", $distanceId, $distanceId);
				
			$results = $this->jdb->get_results($sql, OBJECT);
			
			if ($this->jdb->num_rows == 0)
				return null;

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getClubRecords',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
    public function getCountyChampions() {
			$sql = "           
				SELECT r.runner_id as runnerId, p.Name as runnerName, e.id as eventId, e.Name as eventName, ra.date, r.result, c.code as categoryCode, ra.id as raceId, ra.description, d.id as distanceId, d.distance
				FROM results AS r
        INNER JOIN race ra ON r.race_id = ra.id				
        INNER JOIN distance d ON ra.distance_id = d.id
				INNER JOIN events e ON ra.event_id = e.id
				INNER JOIN runners p ON r.runner_id = p.id
				INNER JOIN category c ON r.category_id = c.id      
				WHERE r.county_champion = 1 
				ORDER BY ra.date desc, categoryCode asc";
				
			$results = $this->jdb->get_results($sql, OBJECT);
			
			if ($this->jdb->num_rows == 0)
				return null;

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getCountyChampions',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getResultRankings($distanceId, $year = 0, $sexId = 0)	{		

			// Get the results
			if ($year != 0)
			{
				$dateQuery1 = "  WHERE ra1.date >= '$year-01-01' and ra1.date <= '$year-12-31'";
				$dateQuery2 = "  AND ra2.date >= '$year-01-01' and ra2.date <= '$year-12-31'";
			}
			else
			{
				$dateQuery1 = "";
				$dateQuery2 = "";
			}

			if ($sexId != 0)
			{
				$sexQuery = " AND p2.sex_id = $sexId";
			}
			else
			{
				$sexQuery = "";
			}
		
			$sql = "SET @cnt := 0;";
			
			$this->jdb->query($sql);		

			$sql = "
				SELECT @cnt := @cnt + 1 AS rank, Ranking.* FROM (
					SELECT r.runner_id as runnerId, p.Name as name, ra3.id as raceId, e.Name as event, ra3.date, r.result
					FROM results AS r
					JOIN (
					  SELECT r1.runner_id, r1.result, MIN(ra1.date) AS earliest
					  FROM results AS r1
					  INNER JOIN race ra1 ON r1.race_id = ra1.id
					  JOIN (
						SELECT r2.runner_id, MIN(r2.result) AS quickest
						FROM results r2					
						INNER JOIN `race` ra2
						ON ra2.id = r2.race_id
						INNER JOIN `runners` p2
						ON r2.runner_id = p2.id
						WHERE r2.result != '00:00:00' 
						AND ra2.distance_id = $distanceId
            AND (ra2.course_type_id NOT IN (2, 4, 5, 7) OR ra2.course_type_id IS NULL)
						$sexQuery
						$dateQuery2
						GROUP BY r2.runner_id
					   ) AS rt
					   ON r1.runner_id = rt.runner_id AND r1.result = rt.quickest
					   $dateQuery1
					   GROUP BY r1.runner_id, r1.result
					   ORDER BY r1.result asc
					   LIMIT 100
					) as rd
					ON r.runner_id = rd.runner_id AND r.result = rd.result 
					INNER JOIN race ra3 ON r.race_id = ra3.id AND ra3.date = rd.earliest
					INNER JOIN runners p ON r.runner_id = p.id
					INNER JOIN events e ON ra3.event_id = e.id
					ORDER BY r.result asc
					LIMIT 100) Ranking";

			$results = $this->jdb->get_results($sql, OBJECT);
			
			if ($this->jdb->num_rows == 0)
				return null;

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getResultRankings',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getMemberResults($runnerId) {

			$sql = $this->jdb->prepare("select
					  e.id as eventId,
					  e.name as eventName,
					  ra.distance_id as distanceId,
					  r.id as id,
					  ra.date as date,
					  ra.description as raceName,
					  ra.id as raceId,
					  r.position as position,
					  r.result as time,
					  r.result as result,
					  r.personal_best as isPersonalBest,
					  r.season_best as isSeasonBest,
					  st.name as standard,
					  r.info as info,
					  CASE
					   WHEN ra.date >= '2017-01-01' THEN r.percentage_grading_2015
					   ELSE r.percentage_grading
					  END as percentageGrading,
					  ra.course_type_id AS courseTypeId
					from
					  runners p
					INNER JOIN results r 
					  ON r.runner_id = p.id					
					INNER JOIN race ra
					  ON ra.id = r.race_id
					INNER JOIN events e
					  ON ra.event_id = e.id
					LEFT JOIN standard_type st
					  ON r.standard_type_id = st.id					
					where					   
					  r.runner_id = %d
					ORDER BY date DESC", $runnerId);

			$results = $this->jdb->get_results($sql, OBJECT);
			
			if ($this->jdb->num_rows == 0)
				return null;
			
			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getMemberResults',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		} // end function GetMemberResults
		
		public function getMemberPBResults($runnerId) {

			$sql = $this->jdb->prepare("select
					  e.id as eventId,
					  e.name as eventName,
					  ra.distance_id as distanceId,
					  d.distance as distance,
					  r.id as id,
					  ra.date as date,
					  ra.description as raceName,
					  ra.id as raceId,
					  r.position as position,
					  r.result as time,		
					  r.result as result,	
					  r.info as info,
					  CASE
					   WHEN ra.date >= '2017-01-01' THEN r.percentage_grading_2015
					   ELSE r.percentage_grading
					  END as percentageGrading
					from
					  runners p
					INNER JOIN results r 
					  ON r.runner_id = p.id					
					INNER JOIN race ra
					  ON ra.id = r.race_id
					INNER JOIN events e
					  ON ra.event_id = e.id
					INNER JOIN distance d
					  ON ra.distance_id = d.id
					INNER JOIN(
						select ra.distance_id as distanceId, MIN(r.result) as pb
						from
						  results r 
						inner join race ra on ra.id = r.race_id
						where					   
						  r.runner_id = %d 
						  and r.personal_best = 1
						  and r.result != '00:00:00' 
						group by ra.distance_id
					) t on r.result = t.pb and ra.distance_id = t.distanceId
					where					   
					  r.runner_id = %d and r.personal_best = 1
					ORDER BY r.result ASC", $runnerId, $runnerId);

			$results = $this->jdb->get_results($sql, OBJECT);
			
			if ($this->jdb->num_rows == 0)
				return null;
			
			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getMemberPBResults',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
	
		public function getHeadToHeadResults($runnerIds) {
			$sql = "select
					  e.id as eventId,
					  e.name as eventName,
					  ra.distance_id as distanceId,
					  ra.date as date,
					  ra.description as raceName,
					  ra.id as raceId,";
					  
			$selectSql = "";
			$fromSql = " from results r1";
			$whereSql = "where ";
			for($i = 1; $i <= count($runnerIds); $i++) {
				$selectSql .= "r$i.position as position$i, ";
				$selectSql .= "r$i.result as time$i, ";
				$selectSql .= "r$i.percentage_grading_2015 as percentageGrading$i";
				
				if ($i != count($runnerIds)) {
					$selectSql .= ", ";
				}
				
				if ($i > 1) {
					  $fromSql .= " inner join results r$i on r1.race_id = r$i.race_id ";
				}
				
				$whereSql .= "r$i.runner_id = " . $runnerIds[$i - 1] . " AND r$i.position > 0";
				if ($i != count($runnerIds)) {
					$whereSql .= " AND ";
				}
			}
					 
          	$joinSql = "inner join race ra 
						on ra.id = r1.race_id "; 
			$joinSql .= "inner join events e
					    on ra.event_id = e.id ";	

			$orderSql = " order by date";
			
			$sql = $sql.$selectSql.$fromSql.$joinSql.$whereSql.$orderSql;
			
			$results = $this->jdb->get_results($sql, OBJECT);
			
			if ($this->jdb->num_rows == 0)
				return null;
			
			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getHeadToHeadResults',
						'Unknown error in reading results from the database', array( 'status' => 500 , 'sql' => $sql) );			
			}

			return $results;

		}
		
		public function getStandardCertificates($runnerId) {

			$sql = $this->jdb->prepare("SELECT st.name, e.name as 'event', d.distance, r.result, DATE_FORMAT( ra.date, '%%M %%e, %%Y' ) as 'date'
								  FROM standard_certificates sc
								  INNER JOIN results r ON sc.result_id = r.id
								  INNER JOIN standard_type st ON r.standard_type_id = st.id
								  INNER JOIN race ra ON ra.id = r.race_id
								  INNER JOIN events e ON e.id = ra.event_id
								  INNER JOIN distance d ON d.id = ra.distance_id
								  where r.runner_id = %d and ra.date > '2010-01-01'
								  order by st.name desc", $runnerId);

			$results = $this->jdb->get_results($sql, OBJECT);
			
			if ($this->jdb->num_rows == 0)
				return null;

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getStandardCertificates',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}
			
			return $results;
		}
		
		public function getRunnerRankings($runnerId, $sexId, $distances, $year = '') {	
			$results = array();
			
			foreach ($distances as $distanceId)
			{				
				$sql = "SET @cnt := 0;";
				
				$this->jdb->query($sql);		

				if (empty($year)) 
				{
					$sql = $this->jdb->prepare(
					"SELECT * FROM (
						SELECT @cnt := @cnt + 1 AS rank, Ranking.* FROM (
							SELECT r.id as resultId, e.Name as event, r.position, r.result, r.info, ra.date, c.code, p.Name as 'name', r.runner_id, ra.distance_id as distanceId
							FROM results AS r
							JOIN (
								SELECT r1.runner_id, r1.result, MIN(r1.racedate) AS earliest
								FROM results AS r1
								JOIN (
									SELECT r2.runner_id, MIN(r2.result) AS quickest
									FROM results r2
                  INNER JOIN race ra2
									ON r2.race_id = ra2.id
									INNER JOIN events e
									ON ra2.event_id = e.id									
									INNER JOIN `distance` d
									ON ra2.distance_id = d.id
									INNER JOIN `runners` p2
									ON r2.runner_id = p2.id
									WHERE r2.result != '00:00:00'
                  AND r2.result != ''
									AND d.id = %d
									AND p2.sex_id = %d
									GROUP BY r2.runner_id
								   ) AS rt
								ON r1.runner_id = rt.runner_id AND r1.result = rt.quickest
								GROUP BY r1.runner_id, r1.result
								ORDER BY r1.result asc
								LIMIT 100
							) as rd
							ON r.runner_id = rd.runner_id AND r.result = rd.result AND r.racedate = rd.earliest
							INNER JOIN race ra ON ra.id = r.race_id
              INNER JOIN events e ON ra.event_id = e.id              
							INNER JOIN runners p ON r.runner_id = p.id
							INNER JOIN category c ON r.category_id = c.id							
							ORDER BY r.result asc
							LIMIT 100) Ranking
						) Results
					HAVING Results.runner_id = %d", $distanceId, $sexId, $runnerId);
				}
				else
				{
					$sql = $this->jdb->prepare(
					"SELECT * FROM (
						SELECT @cnt := @cnt + 1 AS rank, Ranking.* FROM (
							SELECT r.id as resultId, e.Name as event, r.position, r.result, r.info, ra.date, c.code, p.Name as name, r.runner_id, ra.distance_id as distanceId
							FROM results AS r
							JOIN (
								SELECT r1.runner_id, r1.result, MIN(r1.racedate) AS earliest
								FROM results AS r1
								JOIN (
									SELECT r2.runner_id, MIN(r2.result) AS quickest
									FROM results r2
                  INNER JOIN race ra2
									ON r2.race_id = ra2.id
									INNER JOIN events e
									ON ra2.event_id = e.id									
									INNER JOIN `distance` d
									ON ra2.distance_id = d.id
									INNER JOIN `runners` p2
									ON r2.runner_id = p2.id
									WHERE r2.result != '00:00:00'
                  AND r2.result != ''
									AND d.id = %d
									AND p2.sex_id = %d
									AND year(r2.racedate) =%d
									GROUP BY r2.runner_id
								   ) AS rt
								ON r1.runner_id = rt.runner_id AND r1.result = rt.quickest
								GROUP BY r1.runner_id, r1.result
								ORDER BY r1.result asc
								LIMIT 100
							) as rd
							ON r.runner_id = rd.runner_id AND r.result = rd.result AND r.racedate = rd.earliest					
              INNER JOIN race ra ON ra.id = r.race_id
							INNER JOIN events e ON ra.event_id = e.id
							INNER JOIN runners p ON r.runner_id = p.id
							INNER JOIN category c ON r.category_id = c.id							
							WHERE year(ra.date) = %d
							ORDER BY r.result asc
							LIMIT 100) Ranking
						) Results
					HAVING Results.runner_id = %d", $distanceId, $sexId, $year, $year, $runnerId);		
				}
				
				$ranking = $this->jdb->get_row($sql, OBJECT);

				if ($ranking)
				{
					$results[] = $ranking;
				}				
			}		

			return $results;
		}
		
		public function getWMAPercentageRankings($sexId = 0, $distanceId = 0, $year = 0, $distinct = false) {	

			// Get the results
			if ($distanceId != 0)
			{
				$distanceQuery1 = " AND ra1.distance_id = $distanceId";
				$distanceQuery2 = " AND ra2.distance_id = $distanceId";
			}
			else
			{
				$distanceQuery2 = "";
				$distanceQuery1 = "";
			}

			if ($sexId != 0)
			{
				$sexQuery0 = " AND p.sex_id = $sexId";
				$sexQuery1 = " AND p2.sex_id = $sexId";
			}
			else
			{
				$sexQuery0 = "";
				$sexQuery1 = "";
			}
			
			if ($year != 0)
			{
				$yearQuery0 = " AND YEAR(ra.date) >= $year AND YEAR(ra.date) < ($year +1)";
				$yearQuery1 = " AND YEAR(ra1.date) >= $year AND YEAR(ra1.date) < ($year +1)";
				$yearQuery2 = " AND YEAR(ra2.date) >= $year AND YEAR(ra2.date) < ($year +1)";
			}
			else
			{
				$yearQuery0 = "";
				$yearQuery1 = "";
				$yearQuery2 = "";
			}
			
			$sql = "SET @cnt := 0;";
			
			$this->jdb->query($sql);		
			
			if ($distinct == false || $distinct == "false")
			{
				$sql = "
					select @cnt := @cnt + 1 as rank, ranking.* from (
						select r.runner_id as runnerId, 
						p.name,
						e.id as eventId,
						e.name as event,
						ra2.date,
						r.result,						
						CASE
							WHEN ra2.date >= '2017-01-01' OR $year = 0 THEN r.percentage_grading_2015
							ELSE r.percentage_grading
						END as percentageGrading
						from results as r
						inner join runners p on p.id = r.runner_id
						inner join race ra2 on ra2.id = r.race_id
						inner join events e on e.id = ra2.event_id
						where ((r.percentage_grading_2015 > 0 AND (ra2.date > '2017-01-01' OR $year = 0)) OR r.percentage_grading > 0)
						$sexQuery0
						$distanceQuery2
						$yearQuery2
						order by percentageGrading desc
						limit 500) ranking";
			} 
			else
			{
				if ($year == 0 || $year >= 2017)
				{
					$sql = "
					SELECT @cnt := @cnt + 1 AS rank, Ranking.* FROM (
						SELECT r.runner_id as runnerId, p.Name as name, e.id as eventId, e.Name as event, 
						ra.date,
						r.result,
						r.percentage_grading_2015 as percentageGrading
						FROM results AS r
						JOIN (
						  SELECT r1.runner_id, r1.result, MIN(ra1.date) AS earliest
						  FROM results AS r1
						  JOIN (
							SELECT r2.runner_id, MAX(r2.percentage_grading_2015) AS highest
							FROM results r2
							INNER JOIN race ra2
							ON r2.race_id = ra2.id						
							INNER JOIN `runners` p2
							ON r2.runner_id = p2.id
							WHERE r2.percentage_grading_2015 > 0
							$distanceQuery2
							$sexQuery1
							$yearQuery2
							GROUP BY r2.runner_id
						   ) AS rt
						   ON r1.runner_id = rt.runner_id AND r1.percentage_grading_2015 = rt.highest
						   INNER JOIN race ra1 ON r1.race_id = ra1.id 
						   $distanceQuery1
						   $yearQuery1
						   GROUP BY r1.runner_id, r1.result
						   ORDER BY r1.percentage_grading_2015 desc
						   LIMIT 100
						) as rd
						ON r.runner_id = rd.runner_id AND r.result = rd.result
						INNER JOIN race ra ON r.race_id = ra.id AND ra.date = rd.earliest
						INNER JOIN events e ON ra.event_id = e.id
						INNER JOIN runners p ON r.runner_id = p.id
						ORDER BY percentageGrading desc
						LIMIT 100) Ranking";
				}
				else
				{
					$sql = "
					SELECT @cnt := @cnt + 1 AS rank, Ranking.* FROM (
						SELECT r.runner_id as runnerId, p.Name as name, e.id as eventId, e.Name as event, 
						ra.date,
						r.result,
						r.percentage_grading as percentageGrading
						FROM results AS r
						JOIN (
						  SELECT r1.runner_id, r1.result, MIN(ra1.date) AS earliest
						  FROM results AS r1
						  JOIN (
							SELECT r2.runner_id, MAX(r2.percentage_grading) AS highest
							FROM results r2
							INNER JOIN race ra2
							ON r2.race_id = ra2.id						
							INNER JOIN `runners` p2
							ON r2.runner_id = p2.id
							WHERE r2.percentage_grading > 0
							$distanceQuery2
							$sexQuery1
							$yearQuery2
							GROUP BY r2.runner_id
						   ) AS rt
						   ON r1.runner_id = rt.runner_id AND r1.percentage_grading = rt.highest
						   INNER JOIN race ra1 ON r1.race_id = ra1.id 
						   $distanceQuery1
						   $yearQuery1
						   GROUP BY r1.runner_id, r1.result
						   ORDER BY r1.percentage_grading desc
						   LIMIT 100
						) as rd
						ON r.runner_id = rd.runner_id AND r.result = rd.result
						INNER JOIN race ra ON r.race_id = ra.id AND ra.date = rd.earliest
						INNER JOIN events e ON ra.event_id = e.id
						INNER JOIN runners p ON r.runner_id = p.id
						ORDER BY percentageGrading desc
						LIMIT 100) Ranking";
				}				
			}
						
			$results = $this->jdb->get_results($sql, OBJECT);

			if ($this->jdb->num_rows == 0)
				return null;
			
			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getWMAPercentageRankings',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}
			
			return $results;
		}
		
		public function getAveragePercentageRankings($sexId, $year = 0, $numberOfRaces = 5, $numberOfResults = 50) {

			$sql = "set @cnt := 0, @runnerId := 0, @rank := 0;";
			
			$this->jdb->query($sql);

			// Membership year changed in 2015 to be from 1st March
			if ($year == 2015) {
				$yearQuery = "AND ra.date >= '2015-01-01' AND ra.date < '2016-03-01'";
			
			} else if ($year > 2015) {
				$nextYear = $year + 1;
				$yearQuery = "AND ra.date >= '$year-03-01' AND ra.date < '$nextYear-03-01'";						
			} else {					
				if ($year == 0) {			
					$yearQuery = "";
				} else {
					$yearQuery = "AND YEAR(ra.date) = $year";
				}
			}
			
			$sql = "select @rank := @rank + 1 AS rank, Results.* FROM (
					select runner_id as runnerId, name, ROUND(avg(ranktopX.percentage_grading_2015),2) as topXAvg from (
					select * from (
					select @cnt := if (@runnerId = ranking.runner_id, @cnt + 1, 1) as rank, @runnerId := ranking.runner_id, ranking.* from (
										
										select r.runner_id, p.name, e.id, e.name as event, r.racedate, r.result, r.percentage_grading_2015
										from results as r
										inner join runners p on p.id = r.runner_id
										inner join race ra on ra.id = r.race_id
										inner join events e on e.id = ra.event_id
										where r.percentage_grading_2015 > 0
										AND p.sex_id = $sexId					
										$yearQuery
										order by r.runner_id asc, r.percentage_grading_2015 desc) ranking			
					) as rank2
					where rank2.rank <= $numberOfRaces	
					) ranktopX
					group by ranktopX.runner_id
					having count(*) = $numberOfRaces
					order by topXAvg desc
					LIMIT $numberOfResults) Results";
			
						
			$results = $this->jdb->get_results($sql, OBJECT);
			
			if ($this->jdb->num_rows == 0)
				return null;

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getAveragePerformanceRankings',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}
			
			return $results;
		}
		
		public function getGrandPrixPoints($year, $sexId) {
			$nextYear = $year + 1;

			$sql = "SELECT
                  p.id as runnerId,
                  p.name,
				  p.dob as dateOfBirth,
                  ra.id as raceId,
				  e.id as eventId,
				  e.name as eventName,
				  ra.description,
				  ra.distance_id as distanceId,
				  r.position as position,
				  r.result as result
                FROM
                  results r,
                  race ra,
                  runners p,
				  events e
                WHERE                  
                  ra.date >= '$year-04-01' and ra.date < '$nextYear-04-01'
                  AND r.runner_id = p.id                  
                  AND $sexId = p.sex_id
                  AND ra.id = r.race_id
                  AND ra.grand_prix = 1
				  AND e.id = ra.event_id
                  AND year(FROM_DAYS(TO_DAYS(ra.date) - TO_DAYS(p.dob))) >= 16
                ORDER BY ra.date, ra.id, r.position asc, r.result asc";
			
						
			$results = $this->jdb->get_results($sql, OBJECT);
			
			if ($this->jdb->num_rows == 0)
				return null;

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getGrandPrixPoints',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}
			
			return $results;
		}
		
		public function getMeetings($eventId) {

			$sql = $this->jdb->prepare(
					'SELECT m.id as id, m.name as name, m.from_date as fromDate, m.to_date as toDate
					FROM `meeting` m
					WHERE m.event_id = %d', $eventId);

			$results = $this->jdb->get_results($sql, OBJECT);
			
			if ($this->jdb->num_rows == 0)
				return null;

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getMeetings',
						'Unknown error in reading meeting from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getMeeting($meetingId) {

			$sql = $this->jdb->prepare(
					'SELECT m.id as id, m.name as name, m.from_date as fromDate, m.to_date as toDate, r.id as raceId, r.description as description
					FROM `meeting` m 
					LEFT JOIN `race` r on r.meeting_id = m.id
					WHERE m.id = %d', $meetingId);

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getMeeting',
						'Unknown error in reading meeting from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function insertMeeting($meeting, $eventId) {			
			$sql = $this->jdb->prepare('insert into `meeting`(`event_id`, `from_date`, `to_date`, `name`) values(%d, %s, %s, %s)', $eventId, $meeting['fromDate'], $meeting['toDate'], $meeting['name']);

			$result = $this->jdb->query($sql);

			if ($result)
			{
				return $this->getMeeting($this->jdb->insert_id);
			}

			return new \WP_Error( 'ipswich_jaffa_api_insertMeeting',
						'Unknown error in inserting meeting in to the database', array( 'status' => 500 ) );
		}
		
		public function updateMeeting($meetingId, $field, $value) {		

			// Only name and website may be changed.
			if ($field == 'name' || $field == 'from_date' || $field == 'to_date') 
			{
				$result = $this->jdb->update( 
					'meeting', 
					array( 
						$field => $value
					), 
					array( 'id' => $meetingId ), 
					array( 
						'%s'
					), 
					array( '%d' ) 
				);

				if ($result)
				{
					return $this->getMeeting($meetingId);
				}
				
				return new \WP_Error( 'ipswich_jaffa_api_updateMeeting',
						'Unknown error in updating meeting in to the database'.$sql, array( 'status' => 500 ) );
			}

			return new \WP_Error( 'ipswich_jaffa_api_updateMeeting',
						'Field in meeting may not be updated', array( 'status' => 500 ) );
		}
		
		public function deleteMeeting($meetingId) {
			$sql = $this->jdb->prepare('DELETE FROM meeting WHERE id = %d;', $meetingId);

			$result = $this->jdb->query($sql);

			if (!$result) {			
				return new \WP_Error( 'ipswich_jaffa_api_deleteMeeting',
						'Unknown error in deleting meeting from the database', array( 'status' => 500 ) );			
			}

			return true;
		}
		
		public function getMeetingRaces($meetingId) {
			$sql = $this->jdb->prepare(
					'SELECT ra.id, ra.date, ra.description
					FROM `race` ra
					WHERE ra.meeting_id = %d			
					ORDER BY ra.date, ra.description', $meetingId);	

			$results = $this->jdb->get_results($sql, OBJECT);
			
			if ($this->jdb->num_rows == 0)
				return null;	
			
			if (!$results) {			
				return new \WP_Error( 'ipswich_jaffa_api_getMeetingRaces',
						'Unknown error in getting meeting races from the database', array( 'status' => 500 ) );			
			}
			
			return $results;
		}
		
		public function getAllRaceResults($distanceId) {
			$sql = $this->jdb->prepare(
					"SELECT p.name, p.id, ra.date, ra.id as 'raceId', ra.description as 'raceDescription', e.name as 'eventName', c.id as 'categoryId', c.code as 'categoryCode', r.result, r.position, ra.course_type_id as 'courseTypeId'
					FROM `results` r 
					inner join race ra on ra.id = r.race_id 
					INNER JOIN runners p ON p.id = r.runner_id 
					INNER JOIN events e ON e.id = ra.event_id 
					INNER JOIN category c ON c.id = r.category_id 
					WHERE ra.distance_id = %d AND c.id > 0 AND r.result <> '00:00:00'
					order by category_id asc, ra.date asc, r.result asc", $distanceId);	

			$results = $this->jdb->get_results($sql, OBJECT);
			
			if ($this->jdb->num_rows == 0)
				return null;	
			
			if (!$results) {			
				return new \WP_Error( 'ipswich_jaffa_api_getAllRaceResults',
						'Unknown error in getting all race results from the database', array( 'status' => 500 ) );			
			}
			
			return $results;
		}
		
		public function getRaceDetails($raceIds) {

			$raceIdString = join(",",$raceIds);
			
			$sql = "SELECT ra.id, e.id AS eventId, e.Name as eventName, ra.description as description, ra.date, ra.course_type_id AS courseTypeId, c.description AS courseType, ra.area, ra.county, ra.country_code AS countryCode, ra.conditions, ra.venue, d.distance, ra.grand_prix as isGrandPrixRace, ra.course_number as courseNumber, ra.meeting_id as meetingId, d.result_measurement_unit_type_id as resultMeasurementUnitTypeId
			FROM `events` e
			INNER JOIN `race` ra ON ra.event_id = e.id
			LEFT JOIN `distance` d ON ra.distance_id = d.id
			LEFT JOIN `course_type` c ON ra.course_type_id = c.id
			WHERE ra.id in ($raceIdString)
			ORDER BY ra.date";

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getRaceDetails',
						'Unknown error in reading race from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getRunnerOfTheMonthWinnners($year = 0, $month = 0) {
			if ($year > 0 || $month > 0) {
				$sql = "SELECT romw.category, romw.month, romw.year, r.name, r.id
				from runners r, runner_of_the_month_winners romw
				where r.id = romw.runner_id 
				AND romw.year = $year 
				AND romw.month = $month
				order by romw.year desc , romw.month desc";
			} else {
				$sql = "SELECT romw.category, romw.month, romw.year, r.name, r.id
				from runners r, runner_of_the_month_winners romw
				where r.id = romw.runner_id
				order by romw.year desc, romw.month desc";
			}

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new \WP_Error( 'ipswich_jaffa_api_getRunnerOfTheMonthWinnners',
						'Unknown error in reading results from the database', array( 'status' => 500 , 'sql' => $sql) );			
			}

			return $results;
		}
		
		public function mergeEvents($fromEventId, $toEventId) {
			$sql = $this->jdb->prepare("update results set event_id = %d WHERE event_id = %d",
			$toEventId, $fromEventId);

			$result = $this->jdb->query($sql);

			if ($result === false) {
				return new \WP_Error( 'ipswich_jaffa_api_mergeEvents1',
					'Unknown error in merging events from the database', array( 'status' => 500 ) );	
			}
			
			$sql = $this->jdb->prepare("update race set event_id = %d WHERE event_id = %d",
			$toEventId, $fromEventId);

			$result = $this->jdb->query($sql);

			if ($result === false) {
				return new \WP_Error( 'ipswich_jaffa_api_mergeEvents2',
					'Unknown error in merging events from the database', array( 'status' => 500 ) );	
			}
			
			$this->deleteEvent($fromEventId, false);

			return true;
		}

		public function getLeagues() {

		$sql = 'SELECT l.id, l.name, l.starting_year as startingYear, l.course_type_id as courseTypeId, count( ra.id ) AS numberOfRaces
			FROM `leagues` l 
			LEFT JOIN `race` ra on  ra.league_id = l.id
			GROUP BY l.id, l.name, l.starting_year 
			ORDER BY startingYear DESC, name ASC';

		$results = $this->jdb->get_results($sql, OBJECT);

		if (!$results)	{			
			return new \WP_Error( 'ipswich_jaffa_api_getLeagues',
					'Unknown error in reading leagues from the database', array( 'status' => 500 ) );			
		}

		return $results;
	}

		public function getLeague($id) {

		$sql = $this->jdb->prepare("SELECT l.id as id, l.name as name, l.starting_year as startingYear, l.course_type_id as courseTypeId,
		e.id as eventId, e.name as eventName, ra.id as raceId, ra.description as raceName, ra.date as raceDate, ra.venue as raceVenue,
		count( r.id ) AS numberOfResults
			FROM `leagues` l 
			INNER JOIN `race` ra on  ra.league_id = l.id
			INNER JOIN `events` e on ra.event_id = e.id 
			LEFT JOIN `results` r on r.race_id = ra.id
			WHERE l.id = %d
			GROUP BY l.id, l.name, l.starting_year, e.id, e.name, ra.id, ra.description, ra.date, ra.venue
			ORDER BY ra.date, ra.description ASC", $id);

		$results = $this->jdb->get_results($sql, OBJECT);

		if (!$results)	{			
			return new \WP_Error( 'ipswich_jaffa_api_getLeague',
					'Unknown error in reading league from the database', array( 'status' => 500, 'id' => $id ) );			
		}

		return $results;
	}
	
	public function insertLeague($league)	{			
		$sql = $this->jdb->prepare('INSERT INTO leagues (`name`, `starting_year`, `course_type_id`) VALUES(%s, %s, %d);',
		 $league['name'], $league['startingYear'], $league['courseTypeId']);

		$result = $this->jdb->query($sql);

		if ($result)
		{
			return $this->getLeague($this->jdb->insert_id);
		}

		return new \WP_Error( 'ipswich_jaffa_api_insertLeague',
					'Unknown error in inserting league in to the database', array( 'status' => 500 ) );
	}

		public function updateLeague($leagueId, $field, $value) {		

		// Only name and website may be changed.
		if ($field == 'name' || $field == 'starting_year') 
		{
			$result = $this->jdb->update( 
				'leagues', 
				array( 
					$field => $value
				), 
				array( 'id' => $leagueId ), 
				array( 
					'%s'
				), 
				array( '%d' ) 
			);

			if ($result)
			{
				// Get updated event
				return $this->getLeague($leagueId);
			}
			
			return new \WP_Error( 'ipswich_jaffa_api_updateLeague',
					'Unknown error in updating league in to the database'.$sql, array( 'status' => 500 ) );
		}

		return new \WP_Error( 'ipswich_jaffa_api_updateLeague',
					'Field in league may not be updated', array( 'status' => 500 ) );
	}

	}
?>
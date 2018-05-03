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

namespace IpswichJAFFARunningClubAPI\V3;
	
require_once plugin_dir_path( __FILE__ ) .'config.php';

class Ipswich_JAFFA_Results_Data_Access {		

	private $jdb;

	public function __construct() {
		
		// Needs $this->dbh = mysql_connect( $this->dbhost, $this->dbuser, $this->dbpassword, false,65536 );
		$this->jdb = new \wpdb(JAFFA_RESULTS_DB_USER, JAFFA_RESULTS_DB_PASSWORD, JAFFA_RESULTS_DB_NAME, DB_HOST);		
		$this->jdb->show_errors();
	}
		
	public function getRunnerOfTheMonthWinnners($year = 0, $month = 0) {
		if ($year > 0 || $month > 0) {
			$sql = "SELECT romw.id, romw.category, romw.month, romw.year, r.name, r.id as runner_id
			from runners r, runner_of_the_month_winners romw
			where r.id = romw.runner_id 
			AND romw.year = $year 
			AND romw.month = $month
			order by romw.year desc , romw.month desc";
		} else {
			$sql = "SELECT romw.id, romw.category, romw.month, romw.year, r.name, r.id as runner_id
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
	
	public function getRunnerOfTheMonthVotes($year, $month) {		
	
		$sql = "SELECT * FROM (
					SELECT r2.name as 'nomination', w.reason, w.category, w.voter_id as 'voterId'  FROM `runner_of_the_month_votes` w inner join runners r2 on r2.id = w.runner_id where month = $month and year = $year
				ORDER BY created) x
				GROUP BY nomination, category, voterId
				ORDER BY category, nomination, voterId";

		$results = $this->jdb->get_results($sql, OBJECT);

		if (!$results)	{			
			return new \WP_Error( 'ipswich_jaffa_api_getRunnerOfTheMonthVotes',
					'Unknown error in reading results from the database', array( 'status' => 500 , 'sql' => $sql) );			
		}

		return $results;
	}
	
			
	public function updateRunnerOfTheMonthWinnners($runnerOfTheMonthId, $field, $value) {		
		// Only name and website may be changed.
		if ($field == 'runnerId') 
		{
			$result = $this->jdb->update( 
				'runner_of_the_month_winners', 
				array( 
					'runner_id' => $value
				), 
				array( 'Id' => $runnerOfTheMonthId ), 
				array( 
					'%d'
				), 
				array( '%d' ) 
			);

			if ( $result !== false ) 
			{
				return true;
			}
			
			return new \WP_Error( 'ipswich_jaffa_api_updateRunnerOfTheMonthWinnners',
					'Unknown error in updating winner in to the database', array( 'status' => 500 ) );
		}

		return new \WP_Error( 'ipswich_jaffa_api_updateRunnerOfTheMonthWinnners',
					'Field in runner of the month winner may not be updated', array( 'status' => 500 , 'Field' => $field, 'Value' => $value) );
	}
}
?>
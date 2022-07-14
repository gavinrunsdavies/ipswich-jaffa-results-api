<?php

namespace IpswichJAFFARunningClubAPI\V2\Leagues;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';

use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class LeaguesDataAccess extends DataAccess
{
    public function getLeagues()
    {
        $sql = 'SELECT l.id, l.name, l.starting_year as startingYear, l.course_type_id as courseTypeId, count( ra.id ) AS numberOfRaces, l.final_position as finalPosition
			FROM `leagues` l
			LEFT JOIN `race` ra on  ra.league_id = l.id
			GROUP BY l.id, l.name, l.starting_year
			ORDER BY startingYear DESC, name ASC';

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getLeague(int $id)
    {
        $sql = $this->resultsDatabase->prepare("SELECT l.id as id, l.name as name, l.starting_year as startingYear, l.course_type_id as courseTypeId,
		l.final_position as leagueFinalPosition, e.id as eventId, e.name as eventName, ra.id as raceId, ra.description as raceName, ra.date as raceDate, ra.venue as raceVenue,
		ra.meeting_id as meetingId,
		count( r.id ) AS numberOfResults
			FROM `leagues` l
			LEFT JOIN `race` ra on  ra.league_id = l.id
			LEFT JOIN `events` e on ra.event_id = e.id
			LEFT JOIN `results` r on r.race_id = ra.id
			WHERE l.id = %d
			GROUP BY l.id, l.name, l.starting_year, e.id, e.name, ra.id, ra.description, ra.date, ra.venue
			ORDER BY ra.date, ra.description ASC", $id);

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function insertLeague($league)
    {
        $sql = $this->resultsDatabase->prepare(
            'INSERT INTO leagues (`name`, `starting_year`, `course_type_id`) VALUES(%s, %s, %d);',
            $league['name'],
            $league['startingYear'],
            $league['courseTypeId']
        );

        return $this->insertEntity(__METHOD__, $sql, function ($id) {
			return $this->getLeague($id);
		});
    }

    public function updateLeague(int $leagueId, string $field, string $value)
    {
        return $this->updateEntity(__METHOD__, 'leagues', $field, $value, $leagueId, function ($id) {
			return $this->getLeague($id);
		});
    }

    public function deleteLeague(int $leagueId, bool $deleteRaceAssociations)
    {
        $sql = $this->resultsDatabase->prepare('SELECT COUNT(r.id) FROM race r WHERE r.league_id = %d LIMIT 1', $leagueId);

        $exists = $this->resultsDatabase->get_var($sql); // $resultsDatabase->get_var returns a single value from the database. In this case 1 if the find term exists and 0 if it does not.

        if ($exists != 0) {
            if (empty($deleteRaceAssociations)) {
                return new \WP_Error(
                    __METHOD__,
                    'League cannot be deleted; a number of races are associated with this league. Delete the existing races for this league and try again.',
                    array('status' => 403)
                );
            }

            $sql = $this->resultsDatabase->prepare('UPDATE race r SET r.league_id = NULL WHERE r.league_id = %d;', $leagueId);

            $result = $this->resultsDatabase->query($sql);

            if (is_null($result) || !empty($this->resultsDatabase->last_error)) {
                return new \WP_Error(
                    __METHOD__,
                    'Unknown error in deleting league races from the database',
                    array(
                        'status' => 500,
                        'last_query' => $this->resultsDatabase->last_query,
                        'last_error' => $this->resultsDatabase->last_error
                    )
                );
            }
        }

        $sql = $this->resultsDatabase->prepare('DELETE FROM leagues WHERE id = %d', $leagueId);

        $result = $this->resultsDatabase->query($sql);

        if (is_null($result) || !empty($this->resultsDatabase->last_error)) {
            return new \WP_Error(
                __METHOD__,
                'Unknown error in deleting league from the database',
                array(
                    'status' => 500,
                    'last_query' => $this->resultsDatabase->last_query,
                    'last_error' => $this->resultsDatabase->last_error
                )
            );
        }
    }
}

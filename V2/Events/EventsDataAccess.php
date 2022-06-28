<?php

namespace IpswichJAFFARunningClubAPI\V2\Events;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';

use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class EventsDataAccess extends DataAccess
{
    public function getEventRaceInsightsByYear(int $eventId)
    {
        $sql = $this->resultsDatabase->prepare("
        SELECT YEAR(race.date) as year, d.distance, count(r.id) as count, MIN(NULLIF(NULLIF(r.result, '00:00:00'), '')) as min, MAX(r.result) as max, 
        SUBSTR(SEC_TO_TIME(AVG((substring(r.result, 1, 2) * 3600) + (substring(r.result, 4, 2) * 60) + substring(r.result, 7, 2))), 1, 8) as mean
        FROM `results` r
        INNER JOIN race race ON r.race_id = race.id
        LEFT JOIN distance d ON d.id = race.distance_id
        WHERE race.event_id = %d
        GROUP BY year, distance        
        ORDER BY year", $eventId);

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getEventRaceInsightsByDistance(int $eventId)
    {
        $sql = $this->resultsDatabase->prepare("
        SELECT d.distance, count(r.id) as count,  MIN(NULLIF(NULLIF(r.result, '00:00:00'), '')) as min, MAX(r.result) as max, SUBSTR(SEC_TO_TIME(AVG((substring(r.result, 1, 2) * 3600) + (substring(r.result, 4, 2) * 60) + substring(r.result, 7, 2))), 1, 8) as mean 
        FROM `race` race 
        INNER JOIN `distance` d on race.distance_id = d.id 
        INNER JOIN `results` r ON race.id = r.race_id 
        WHERE race.event_id = %d 
        GROUP BY distance", $eventId);

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getEvent(int $eventId)
    {
        $sql = $this->resultsDatabase->prepare("SELECT e.id, e.name, e.website FROM `events` e	WHERE e.id = %d", $eventId);

        return $this->executeResultQuery(__METHOD__, $sql);
    }

    public function getEvents()
    {
        $sql = 'SELECT e.id as id, e.name as name, e.website, MAX(ra.date) as lastRaceDate, count( r.id ) AS count
			FROM `events` e
			LEFT JOIN `race` ra on ra.event_id = e.id
			LEFT JOIN `results` r ON ra.id = r.race_id
			GROUP BY e.id, e.name, e.website
			ORDER BY lastRaceDate DESC, e.name ASC ';

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function insertEvent($event)
    {
        $sql = $this->resultsDatabase->prepare('INSERT INTO events (`name`, `website`) VALUES(%s, %s);', $event['name'], $event['website']);

        return $this->insertEntity(__METHOD__, $sql, function ($id) {
			return $this->getEvent($id);
		});
    }

    public function updateEvent(int $eventId, string $field, string $value)
    {
        return $this->updateEntity(__METHOD__, 'events', $field, $value, $eventId, function ($id) {
			return $this->getEvent($id);
		});
    }

    public function mergeEvents(int $fromEventId, int $toEventId)
    {
        $sql = $this->resultsDatabase->prepare(
            "update race set event_id = %d WHERE event_id = %d",
            $toEventId,
            $fromEventId
        );

        $result = $this->resultsDatabase->query($sql);

        if (is_null($result) || !empty($this->resultsDatabase->last_error)) {
            return new \WP_Error(
                __METHOD__,
                'Unknown error in merging events from the database',
                array(
                    'status' => 500,
                    'last_query' => $this->resultsDatabase->last_query,
                    'last_error' => $this->resultsDatabase->last_error
                )
            );
        }

        return $this->deleteEvent($fromEventId);
    }

    public function deleteEvent(int $eventId)
    {
        $sql = $this->resultsDatabase->prepare('SELECT COUNT(r.id) FROM results r INNER JOIN race ra ON ra.id = r.race_id WHERE ra.event_id = %d LIMIT 1;', $eventId);

        $exists = $this->resultsDatabase->get_var($sql); // $resultsDatabase->get_var returns a single value from the database. In this case 1 if the find term exists and 0 if it does not.

        if ($exists != 0) {
            return new \WP_Error(
                __METHOD__,
                'Event cannot be deleted; a number results are associated with this event. Delete the existing results for this event and try again.',
                array(
                    'status' => 500,
                    'last_query' => $this->resultsDatabase->last_query,
                    'last_error' => $this->resultsDatabase->last_error
                )
            );
        }

        $sql = $this->resultsDatabase->prepare('DELETE FROM events WHERE id = %d', $eventId);

        return $this->executeQuery(__METHOD__, $sql);
    }

    public function getEventTopAttendees(int $eventId)
    {
        $sql = $this->resultsDatabase->prepare("
        select t1.name,
        t1.year,        
        sum(t2.count) as runningTotal
        FROM
        (
            SELECT p.name as name, YEAR(race.date) as year, count(r.id) as count
            FROM race race  
            INNER JOIN results r ON r.race_id = race.id
            INNER JOIN runners p ON p.id = r.runner_id
            wHERE race.event_id = %d
            GROUP BY name, year) as t1
        INNER JOIN
        (
            SELECT p.name as name, YEAR(race.date) as year, count(r.id) as count
            FROM race race  
            INNER JOIN results r ON r.race_id = race.id
            INNER JOIN runners p ON p.id = r.runner_id
            wHERE race.event_id = %d
            GROUP BY name, year) as t2
        on t1.name=t2.name and t1.year >= t2.year
        group by t1.name, t1.year  
        ORDER BY t1.year ASC", $eventId, $eventId);

        return $this->executeResultsQuery(__METHOD__, $sql);
    }
}

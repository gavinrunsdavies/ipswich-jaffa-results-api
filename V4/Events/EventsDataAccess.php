<?php

namespace IpswichJAFFARunningClubAPI\V4\Events;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V4/DataAccess.php';

use IpswichJAFFARunningClubAPI\V4\DataAccess as DataAccess;

class EventsDataAccess extends DataAccess
{

    public function getEvents()
    {
        $sql = 'SELECT e.id as id, e.name as name, e.website, MAX(ra.date) as lastRaceDate, count(r.id) AS count
			FROM `events` e
			LEFT JOIN `race` ra on ra.event_id = e.id
			LEFT JOIN `results` r ON ra.id = r.race_id
			GROUP BY e.id, e.name, e.website
			ORDER BY lastRaceDate DESC, e.name ASC';

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function insertEvent($event)
    {
        $sql = $this->resultsDatabase->prepare('INSERT INTO events (`name`, `website`) VALUES(%s, %s);', $event['name'], $event['website']);

        $this->executeQuery(__METHOD__, $sql);
        $result = $this->resultsDatabase->query($sql);

        if ($result) {
            return $this->getEvent($this->resultsDatabase->insert_id);
        }

        return $result;
    }

    public function getEvent(int $eventId)
    {
        $sql = $this->resultsDatabase->prepare("SELECT e.id, e.name, e.website FROM `events` e WHERE e.id = %d", $eventId);

        return $this->executeResultQuery(__METHOD__, $sql);
    }

    public function updateEvent(int $eventId, string $field, string $value)
    {
        if ($field == 'name' || $field == 'website') {
            $result = $this->resultsDatabase->update(
                'events',
                array(
                    $field => $value,
                ),
                array('id' => $eventId),
                array(
                    '%s',
                ),
                array('%d')
            );

            if ($result) {
                return $this->getEvent($eventId);
            }

            return $result;
        }

        return new \WP_Error(
            __METHOD__,
            'Field in event may not be updated',
            array('status' => 500)
        );
    }

    public function deleteEvent(int $eventId)
    {
        $sql = $this->resultsDatabase->prepare('SELECT COUNT(r.id) FROM results r INNER JOIN race ra ON ra.id = r.race_id WHERE ra.event_id = %d LIMIT 1;', $eventId);

        $exists = $this->resultsDatabase->get_var($sql); // $resultsDatabase->get_var returns a single value from the database. In this case 1 if the find term exists and 0 if it does not.

        if ($exists != 0) {
            return new \WP_Error(
                __METHOD__,
                'Event cannot be deleted; a number results are associated with this event. Delete the existing results for this event and try again.',
                array('status' => 500)
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
        on t1.name = t2.name and t1.year >= t2.year
        group by t1.name, t1.year  
        ORDER BY t1.year ASC", $eventId, $eventId);

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function mergeEvents(int $fromEventId, int $toEventId)
    {
        $sql = $this->resultsDatabase->prepare(
            "update race set event_id = %d WHERE event_id = %d",
            $toEventId,
            $fromEventId
        );

        $response = $this->resultsDatabase->query($sql);

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->deleteEvent($fromEventId);
    }
}

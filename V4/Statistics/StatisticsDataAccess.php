<?php

namespace IpswichJAFFARunningClubAPI\V4\Statistics;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH .'V4/DataAccess.php';

use IpswichJAFFARunningClubAPI\V4\DataAccess as DataAccess;

class StatisticsDataAccess extends DataAccess
{ 
	public function getMeanPercentageGradingByMonth() 
	{
		$sql = "SELECT DATE_FORMAT(race.date, '%Y-%m-01') as date, c.code as categoryCode, ROUND(AVG(r.percentage_grading_2015), 2) as meanGrading
				FROM race race
				inner join results r on r.race_id = race.id
				INNER join category c on c.id = r.category_id
				where r.percentage_grading_2015 > 0
				group by date, categoryCode
				ORDER BY date, categoryCode";

        return $this->executeResultsQuery(__METHOD__, $sql);
	}

    public function getEventTopAttendees(int $eventId) 
	{
		$sql = $this->jdb->prepare("
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

    public function getResultsByYearAndCounty()
    {
        $sql = "SELECT YEAR(ra.date) as year, ra.county, count(r.id) as count 
        FROM `race` ra 
        INNER join results r on ra.id = r.race_id 
        WHERE ra.county IS NOT NULL 
        GROUP BY YEAR(ra.date), ra.county 
        ORDER BY `year` ASC";

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getResultsByYearAndCountry()
    {
        $sql = "SELECT YEAR(ra.date) as year, ra.country_code, count(r.id) as count FROM `race` ra INNER join results r on ra.id = r.race_id WHERE ra.country_code IS NOT NULL GROUP BY YEAR(ra.date), ra.country_code ORDER BY `year` ASC";

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getResultsCountByYear()
    {
        $sql = "SELECT YEAR(ra.date) as year, count(r.id) as count FROM results r INNER JOIN race ra ON ra.id = r.race_id GROUP BY YEAR(ra.date) ORDER BY `year` DESC";

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getPersonalBestTotals()
    {
        $sql = "SELECT p.id as runnerId, p.name, count(r.id) as count, MIN(ra.date) AS firstPB, MAX(ra.date) AS lastPB FROM `results` r inner join runners p on r.runner_id = p.id INNER JOIN race ra ON ra.id = r.race_id where r.personal_best = 1 group by runnerId, p.name order by count DESC limit 50";

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getPersonalBestTotalByYear()
    {
        $sql = "SELECT count(*) AS count, YEAR(ra.date) as year from results r INNER JOIN race ra ON ra.id = r.race_id where r.personal_best = 1 GROUP by year order by year desc";

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getTopAttendedRaces()
    {
        $sql = "SELECT e.id as eventId, e.name, ra.date, count(r.id) as count
					FROM `results` r
					INNER JOIN race ra ON ra.id = r.race_id
					inner join events e on ra.event_id = e.id
					group by eventId, e.name, ra.date
					order by count desc limit 50";

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getTopMembersRacing()
    {
        $sql = "SELECT p.id as runnerId, p.name, count(r.id) as count FROM `results` r inner join runners p on r.runner_id = p.id group by runnerId, p.name order by count desc limit 50";

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getGroupedRunnerResultsCount(int $groupSize, int $minimumResultCount)
    {
        $sql = "SELECT b.range,
        COALESCE(SUM(CASE WHEN gender = 2 THEN count END), 0) AS male,
        COALESCE(SUM(CASE WHEN gender = 3 THEN count END), 0) AS female
        FROM (
            SELECT
            concat($groupSize * floor(count / $groupSize) + 1, '-', $groupSize * floor(count / $groupSize) + $groupSize) AS `range`,
            a.gender,
            count(*) AS `count`
                FROM (
                SELECT r.runner_id AS runnerId, p.sex_id AS gender, count(r.id) AS count 
                FROM `results` r 
                INNER JOIN runners p ON p.id = r.runner_id
                WHERE p.sex_id <> 1
                GROUP BY runnerId, gender
                HAVING count > $minimumResultCount) a
                GROUP BY 1, 2) b
        GROUP BY 1
        ORDER BY CAST(b.range as SIGNED)";

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getResultCountByRunnerByYear(int $year, int $limit)
    {
        if ($year > 0) {
            $sql = "SELECT p.id as runnerId, p.name, count(r.id) as count 
                    FROM results r
                    INNER JOIN race race ON race.id = r.race_id
                    inner join runners p on r.runner_id = p.id 
                    where YEAR(race.date) = $year
                    group by runnerId, p.name 
                    order by count desc limit $limit";
        } else {
            $sql = "SELECT p.id as runnerId, p.name, count(r.id) as count 
                    FROM results r
                    inner join runners p on r.runner_id = p.id 
                    group by runnerId, p.name 
                    order by count desc limit $limit";
        }

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getClubResultsCount(int $year, int $limit)
    {
        if ($year > 0) {
            $sql = "SELECT e.id as eventId, e.name, race.date, race.description, count(r.id) AS count
                    FROM results r
                    INNER JOIN race race ON race.id = r.race_id
                    INNER JOIN events e ON race.event_id = e.id
                    WHERE year(race.date) = $year
                    GROUP BY eventId, e.name, race.date, race.description
                    HAVING count > 0
                    ORDER BY race.date ASC
                    LIMIT $limit";
        } else {
            $sql = "SELECT 
                    YEAR(race.date) as year, 
                    MONTH(race.date) as month, 
                    DATE_FORMAT(race.date, '%%Y-%%m-01') as monthYear, 
                    count(r.id) AS count, 
                    COALESCE(sum(case when race.course_type_id IS NULL OR race.course_type_id = 0 then 1 end), 0) as unknown,
                    COALESCE(sum(case when race.course_type_id = 1 then 1 end), 0) as road,
                    COALESCE(sum(case when race.course_type_id = 2 then 1 end), 0) as 'multi-terrain',
                    COALESCE(sum(case when race.course_type_id = 3 then 1 end), 0) as track,
                    COALESCE(sum(case when race.course_type_id = 5 then 1 end), 0) as xc,
                    COALESCE(sum(case when race.course_type_id = 9 then 1 end), 0) as 'virtual',
                    COALESCE(sum(case when race.course_type_id = 4 OR race.course_type_id = 6 OR race.course_type_id = 7 OR race.course_type_id = 8 then 1 end), 0) as other
                    FROM results r
                    INNER JOIN race race ON race.id = r.race_id                    
                    GROUP BY year, month, monthYear
                    ORDER BY date ASC
                    LIMIT $limit";
        }

        return $this->executeResultsQuery(__METHOD__, $sql);
    }

    public function getTopMembersRacingByYear()
    {
        $sql = "select YEAR(ra.date) AS year, count(r.id) AS count, p.id as runnerId, p.name from results r inner join runners p on p.id = r.runner_id INNER JOIN race ra ON ra.id = r.race_id group by year, runnerId, p.name order by count DESC, year ASC LIMIT 10";

        return $this->executeResultsQuery(__METHOD__, $sql);
    }
}
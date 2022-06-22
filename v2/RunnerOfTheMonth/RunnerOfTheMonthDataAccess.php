<?php

namespace IpswichJAFFARunningClubAPI\V2\RunnerOfTheMonth;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/DataAccess.php';

use IpswichJAFFARunningClubAPI\V2\DataAccess as DataAccess;

class RunnerOfTheMonthDataAccess extends DataAccess
{
    public function insertRunnerOfTheMonthWinners(int $runnerId, string $category, int $month, int $year)
    {
        $sql = $this->resultsDatabase->prepare("insert into runner_of_the_month_winners set runner_id=%d, category='%s', month=%d, year=%d",
            $runnerId, $category, $month, $year);

        return $this->executeQuery(__METHOD__, $sql);
    }

    public function insertRunnerOfTheMonthVote($vote)
    {
        $sql = $this->resultsDatabase->prepare("insert into runner_of_the_month_votes
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

        return $this->executeQuery(__METHOD__, $sql);
    }

    public function getRunnerOfTheMonthWinnners(int $year = 0, int $month = 0)
    {
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

        return $this->executeResultsQuery(__METHOD__, $sql);
    }
}

<?php

namespace IpswichJAFFARunningClubAPI\V2\Meetings;

class Meeting
{
    public $meeting;
    public $races;
    public $teams;
    public $event;

    public function __construct($meeting, $races, $teams, $event)
    {
        $this->meeting = $meeting;
        $this->races = $races;
        $this->teams = $teams;
        $this->event = $event;
    }
}

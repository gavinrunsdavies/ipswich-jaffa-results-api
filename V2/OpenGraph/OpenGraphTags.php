<?php

namespace IpswichJAFFARunningClubAPI\V2\OpenGraph;

class OpenGraphTags
{
    private $racesCommand;
    private $meetingsCommand;

    public function __construct($db = null)
    {
        if ($db !== null) {
            $this->racesCommand = new \IpswichJAFFARunningClubAPI\V2\Races\RacesCommand($db);
            $this->meetingsCommand = new \IpswichJAFFARunningClubAPI\V2\Meetings\MeetingsCommand($db);
        }
    }

    public function register()
    {
        add_action('wp_head', array($this, 'render'), 5);
    }

    public function render()
    {
        if (!$this->shouldRender()) {
            return;
        }

        $raceId = $this->getRaceId();
        if (empty($raceId)) {
            return;
        }

        $raceData = $this->getRaceData($raceId);
        if (empty($raceData)) {
            return;
        }

        $title = $this->buildTitle($raceData);
        $description = $this->buildDescription($raceData);
        $image = $this->buildImage($raceData);
        $url = $this->buildUrl();

        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
        echo '<meta property="og:image" content="' . esc_url($image) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta property="og:type" content="website" />' . "\n";
    }

    private function shouldRender(): bool
    {
        $raceId = $this->getRaceId();
        if (empty($raceId)) {
            return false;
        }

        $requestUri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if ($requestUri !== '' && strpos($requestUri, 'race-results') !== false) {
            return true;
        }

        if (is_page()) {
            $slug = get_page_uri(get_the_ID());
            return $slug !== '' && strpos((string) $slug, 'race-results') !== false;
        }

        return false;
    }

    private function getRaceId()
    {
        if (isset($_GET['raceId']) && is_scalar($_GET['raceId'])) {
            return sanitize_text_field(wp_unslash($_GET['raceId']));
        }

        return '';
    }

    private function getRaceData($raceId)
    {
        if ($this->racesCommand === null || $this->meetingsCommand === null) {
            return array();
        }

        $race = $this->racesCommand->getRace((int) $raceId);
        if (is_wp_error($race) || empty($race)) {
            return array();
        }

        $meetingData = $this->meetingsCommand->getMeetingForRace((int) $raceId);
        if (is_wp_error($meetingData) || empty($meetingData)) {
            return array();
        }

        return array(
            'eventName' => isset($meetingData->event->name) ? sanitize_text_field($meetingData->event->name) : '',
            'meetingSubtitle' => $this->buildMeetingSubtitle($meetingData->meeting),
            'report' => isset($meetingData->meeting->report) ? wp_strip_all_tags($meetingData->meeting->report) : '',
            'image' => isset($meetingData->meeting->image) ? esc_url($meetingData->meeting->image) : '',
        );
    }

    private function buildTitle(array $raceData): string
    {
        $eventName = !empty($raceData['eventName']) ? $raceData['eventName'] : 'Race results';
        return $eventName . ' — Ipswich JAFFA Results';
    }

    private function buildDescription(array $raceData): string
    {
        if (!empty($raceData['report'])) {
            return $this->truncateForSocial($raceData['report']);
        }

        if (!empty($raceData['meetingSubtitle'])) {
            return $this->truncateForSocial($raceData['meetingSubtitle']);
        }

        return 'View the race results and meeting details for this event.';
    }

    private function buildMeetingSubtitle($meeting): string
    {
        $subtitleParts = array();

        if (!empty($meeting->name) && empty($meeting->eventName)) {
            $subtitleParts[] = $meeting->name;
        } elseif (!empty($meeting->name) && !empty($meeting->eventName) && strtolower(trim($meeting->name)) !== strtolower(trim($meeting->eventName))) {
            $subtitleParts[] = $meeting->name;
        }

        if (!empty($meeting->fromDate)) {
            try {
                $date = new \DateTime($meeting->fromDate);
                $subtitleParts[] = $date->format('j F Y');
            } catch (\Exception $ex) {
                $subtitleParts[] = $meeting->fromDate;
            }
        }

        return implode(' · ', $subtitleParts);
    }

    private function truncateForSocial(string $text): string
    {
        $text = wp_strip_all_tags($text);
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if (strlen($text) <= 160) {
            return $text;
        }

        return substr($text, 0, 157) . '…';
    }

    private function buildImage(array $raceData): string
    {
        if (!empty($raceData['image'])) {
            return $raceData['image'];
        }

        $siteIcon = get_site_icon_url();
        return !empty($siteIcon) ? $siteIcon : '';
    }

    private function buildUrl(): string
    {
        $requestUri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        return home_url($requestUri);
    }
}

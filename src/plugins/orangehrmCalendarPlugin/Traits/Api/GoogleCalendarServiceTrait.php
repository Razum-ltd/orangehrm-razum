<?php

namespace OrangeHRM\Calendar\Traits\Api;

use Exception;
use Google_Client;

trait GoogleCalendarServiceTrait
{
    /**
     * @var \Google_Service_Calendar|null
     */
    private ?\Google_Service_Calendar $calendarService = null;

    /**
     * @throws Exception
     * @return Google_Client
     */
    private function getClient(): Google_Client
    {
        try {
            $client = new Google_Client();
            $client->setApplicationName("Orange HRM");
            $client->setAccessType('offline');
            $client->setIncludeGrantedScopes(true);
            $client->addScope([
                \Google_Service_Calendar::CALENDAR,
                \Google_Service_Calendar::CALENDAR_EVENTS,
            ]);
            $client->setSubject('klemen.komel@razum.si'); // for impresonating a user - must have a domain wide delegation set-up
            // Change the credentials.json file for a different service account
            $client->setAuthConfig(__DIR__ . '/../../config/credentials.json');
            return $client;
        } catch (Exception $e) {
            throw new Exception("Error creating Google Client: " . $e->getMessage());
        }
    }

    /**
     * @return \Google_Service_Calendar
     */
    public function getCalendarService(): \Google_Service_Calendar
    {
        if ($this->calendarService == null) {
            $this->setCalendarService(new \Google_Service_Calendar($this->getClient()));
        }
        return $this->calendarService;
    }

    /**
     * @param \Google_Service_Calendar $calendarService
     * @return self
     */
    public function setCalendarService(\Google_Service_Calendar $calendarService): self
    {
        $this->calendarService = $calendarService;

        return $this;
    }
}

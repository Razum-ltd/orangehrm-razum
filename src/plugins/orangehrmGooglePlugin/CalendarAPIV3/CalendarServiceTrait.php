<?php

namespace OrangeHRM\Google\CalendarAPIV3;

use OrangeHRM\Google\Exception\CalendarServiceException;

trait CalendarServiceTrait
{
    /**
     * @var \Google_Service_Calendar|null
     */
    private ?\Google_Service_Calendar $calendarService = null;

    /**
     * @throws CalendarServiceException
     * @return \Google_Client
     */
    private function getClient(): \Google_Client
    {
        try {
            $client = new \Google_Client();
            $client->setApplicationName("Orange HRM");
            $client->setAccessType('offline');
            $client->setIncludeGrantedScopes(true);
            $client->addScope([
                Google\Service\Calendar::CALENDAR,
                Google\Service\Calendar::CALENDAR_EVENTS,
            ]);
            // Change the credentials.json file for a different service account
            $client->setAuthConfig('../config/credentials.json');
            return $client;
        } catch (\Exception $e) {
            throw CalendarServiceException::cannotCreateClient();
        }
    }

    /**
     * @return \Google_Service_Calendar
     */
    public function getCalendarService(): \Google_Service_Calendar
    {
        if (!$this->calendarService instanceof \Google_Service_Calendar) {
            $this->calendarService = new \Google_Service_Calendar($this->getClient());
        }
        return $this->calendarService;
    }

    /**
     * @param \Google_Service_Calendar $calendarService
     */
    public function setCalendarService(\Google_Service_Calendar $calendarService): void
    {
        $this->calendarService = $calendarService;
    }
}
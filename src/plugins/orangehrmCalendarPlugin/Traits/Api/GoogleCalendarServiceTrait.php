<?php

namespace OrangeHRM\Calendar\Traits\Api;

use Exception;
use Google_Client;
use OrangeHRM\Calendar\Exception\CalendarServiceException;

trait GoogleCalendarServiceTrait
{
    /**
     * @var \Google\Service\Calendar|null
     */
    private ?\Google\Service\Calendar $calendarService = null;

    /**
     * @throws CalendarServiceException
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
                Google\Service\Calendar::CALENDAR,
                Google\Service\Calendar::CALENDAR_EVENTS,
            ]);
            // Change the credentials.json file for a different service account
            $client->setAuthConfig('../config/credentials.json');
            return $client;
        } catch (Exception $e) {
            throw CalendarServiceException::cannotCreateClient();
        }
    }

    /**
     * @return \Google\Service\Calendar
     */
    public function getCalendarService(): \Google\Service\Calendar
    {
        if ($this->calendarService == null) {
            $this->setCalendarService(new \Google\Service\Calendar($this->getClient()));
        }
        return $this->calendarService;
    }

    /**
     * @param \Google\Service\Calendar $calendarService
     * @return self
     */
    public function setCalendarService(\Google\Service\Calendar $calendarService): self
    {
        $this->calendarService = $calendarService;

        return $this;
    }
}

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


            $dir = __DIR__;
            $keyFilePath = $dir . '/../../config/credentials.json';
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $keyFilePath);

            $client = new Google_Client();
            $client->setApplicationName('Orange HRM');
            $client->setIncludeGrantedScopes(true);
            $client->setAccessType("offline");
            // TODO create and change to generic razum mail to use services connected to that service account
            $client->setSubject("rok.first@razum.si");
            $client->setScopes([
                \Google_Service_Calendar::CALENDAR,
                \Google_Service_Calendar::CALENDAR_EVENTS,
                \Google_Service_Calendar::CALENDAR_READONLY
            ]);

            $config = json_decode(file_get_contents(getenv("GOOGLE_APPLICATION_CREDENTIALS")), true);
            $client->setAuthConfig($config);
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

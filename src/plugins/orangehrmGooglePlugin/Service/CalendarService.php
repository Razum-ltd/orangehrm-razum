<?php
namespace OrangeHRM\Google\Service;

use Google\Service\Calendar\EventDateTime;
use OrangeHRM\Google\Exception\CalendarServiceException;

class CalendarService
{
    /**
     * @var \Google_Service_Calendar|null
     */
    private ?\Google_Service_Calendar $calendarService = null;

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
     * @param $options
     * @return \Google_Service_Calendar_Event
     */
    private function createEvent(
        $options = [
            'title' => null,
            'description' => null,
            'start' => EventDateTime::NULL_VALUE,
            'end' => EventDateTime::NULL_VALUE,
            'status' => 'confirmed'
        ]
    ) {
        $event = new \Google_Service_Calendar_Event();
        $event->setSummary($options['title']);
        $event->setDescription($options['description']);
        $event->setStatus($options['status']);
        $event->setStart($options['start']);
        if ($options['end']) {
            $event->setEnd($options['end']);
        }
        return $event;
    }

    /**
     * @return \Google_Service_Calendar_Event[]
     */
    public function getEvents(
        $calendarId = "primary",
        $optParams = [
            'maxResults' => 10,
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'timeMin' => date('c'),
        ]
    ): array {
        $results = $this->getCalendarService()->events->listEvents($calendarId, $optParams);
        return $results->getItems();
    }

    /**
     * @param string $calendarId
     * @param \Google_Service_Calendar_Event $event
     * @return \Google_Service_Calendar_Event
     */
    public function createNewEvent($calendarId = "primary", $event)
    {
        return $this->calendarService->events->insert($calendarId, $event);
    }

    /**
     * @param \OrangeHRM\Entity\User $user
     * @param \OrangeHRM\Entity\Leave $leave
     * @return ?\Google_Service_Calendar_Event
     */
    public function createNewLeaveEvent($user, $leave)
    {
        $calendarId = "primary"; // change this to the corrent email address / address for the calendar
        $employee = $user->getEmployee();

        if ($leave->getStatus() == 'Cancelled') {
            return;
        }

        $event = $this->createEvent([
            'title' => $employee->getFirstName() . ' ' . $employee->getLastName() . ' - ' . $leave->getLeaveType()->getName(),
            'description' => $leave->getLeaveType()->getName(),
            'start' => [
                'dateTime' => $leave->getStartTime()->format('c'),
                'timeZone' => 'Europe/London',
            ],
            'end' => [
                'dateTime' => $leave->getEndTime()->format('c'),
                'timeZone' => 'Europe/London',
            ],
        ]);

        return $this->calendarService->events->insert($calendarId, $event);
    }
}
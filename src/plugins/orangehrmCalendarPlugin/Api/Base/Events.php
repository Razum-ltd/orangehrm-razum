<?php

namespace OrangeHRM\Calendar\Api\Base;

use OrangeHRM\Entity\Leave;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Calendar\Traits\Api\GoogleCalendarServiceTrait;
use Google\Service\Calendar\EventDateTime;
use Google_Service_Calendar_Event;

class Events
{
    use GoogleCalendarServiceTrait;
    public const CALENDAR_LEAVE_ID = 'klemen.komel@gmail.com';
    public const EVENT_STATUS_CONFIRMED = 'confirmed';
    public const EVENT_STATUS_TENTATIVE = 'tentative';
    public const EVENT_STATUS_CANCELLED = 'cancelled';
    public const EVENT_VISIBILITY_DEFAULT = 'default';
    public const EVENT_VISIBILITY_PUBLIC = 'public';
    public const EVENT_VISIBILITY_PRIVATE = 'private';
    public const EVENT_VISIBILITY_CONFIDENTIAL = 'confidential';

    /**
     * @param $options
     * @return Google_Service_Calendar_Event
     */
    private function newEvent(
        $options = [
            'title' => null,
            'description' => null,
            'start' => EventDateTime::NULL_VALUE,
            'end' => EventDateTime::NULL_VALUE,
            'status' => 'confirmed'
        ]
    ) {
        $event = new Google_Service_Calendar_Event();
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
     * @param string $calendarId
     * @param string $eventId
     * @return Google_Service_Calendar_Event
     */
    public function get($calendarId, $eventId)
    {
        return $this->getCalendarService()->events->get($calendarId, $eventId);
    }

    /**
     * @param string $calendarId
     * @param string $eventId
     */
    public function delete($calendarId, $eventId)
    {
        $this->getCalendarService()->events->delete($calendarId, $eventId);
    }


    /**
     * @param string $calendarId
     * @param array $optParams
     * @return Google_Service_Calendar_Event[]
     */
    public function list($calendarId, $optParams = [])
    {
        $events = [];
        $list = $this->getCalendarService()->events->listEvents($calendarId, $optParams);

        while (true) {
            $events = array_merge($events, $list->getItems());
            $pageToken = $list->getNextPageToken();
            if ($pageToken) {
                $optParams = ['pageToken' => $pageToken];
                $list = $this->getCalendarService()->events->listEvents($calendarId, $optParams);
            } else {
                break;
            }
        }
        return $events;
    }

    /**
     * @param string $calendarId
     * @param Google_Service_Calendar_Event $event
     * @return Google_Service_Calendar_Event
     */
    public function insert($calendarId, $event)
    {
        return $this->getCalendarService()->events->insert($calendarId, $event);
    }

    /**
     * @param string $calendarId
     * @param Google_Service_Calendar_Event $event
     * @return Google_Service_Calendar_Event
     */
    public function update($calendarId, $event)
    {
        return $this->getCalendarService()->events->update($calendarId, $event->getId(), $event);
    }

    /**
     * @param Employee $employee
     * @param Leave $leave
     * @return ?Google_Service_Calendar_Event
     */
    public function createNewLeaveEvent($employee, $leave)
    {
        if ($leave->getStatus() != Leave::LEAVE_STATUS_LEAVE_APPROVED || $leave->getStatus() != Leave::LEAVE_STATUS_LEAVE_TAKEN) {
            return;
        }

        $event = $this->newEvent();
        $event->setSummary(self::CreateEventTitle($employee, $leave));
        $event->setDescription($leave->getLeaveType()->getName());
        $event->setStatus(self::EVENT_STATUS_CONFIRMED);
        $event->setVisibility(self::EVENT_VISIBILITY_PUBLIC);

        self::SetEventTime($event, $leave);

        return $this->insert(self::CALENDAR_LEAVE_ID, $event);
    }

    /**
     * Formattes the title of the event using the employee and leave type
     * @param mixed $employee
     * @param mixed $leave
     * @return string
     */
    public static function CreateEventTitle(&$employee, &$leave)
    {
        return $employee->getFirstName() . ' ' . $employee->getLastName() . ' - ' . $leave->getLeaveType()->getName();
    }

    /**
     * @param Google_Service_Calendar_Event $event
     * @param Leave $leave
     */
    public static function SetEventTime(&$event, &$leave)
    {
        if ($leave->getDurationType() == Leave::DURATION_TYPE_FULL_DAY) {
            $event->setStart((new EventDateTime())->setDate($leave->getStartTime()->format('Y-m-d')));
            $event->setEnd((new EventDateTime())->setDate($leave->getEndTime()->format('Y-m-d')));
        } else {
            $event->setStart((new EventDateTime())->setDateTime($leave->getStartTime()->format('Y-m-d H:i:s')));
            $event->setEnd((new EventDateTime())->setDateTime($leave->getEndTime()->format('Y-m-d H:i:s')));
        }
    }
}

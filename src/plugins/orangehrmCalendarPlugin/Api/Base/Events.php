<?php

namespace OrangeHRM\Calendar\Api\Base;

use OrangeHRM\Config\Config;
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
            'status' => self::EVENT_STATUS_CONFIRMED
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
        $event = new Google_Service_Calendar_Event();
        self::SetEventTime($event, $leave);

        $event->setSummary(self::CreateEventTitle($employee, $leave));
        $event->setStatus(self::EVENT_STATUS_CONFIRMED);
        $event->setVisibility(self::EVENT_VISIBILITY_PUBLIC);

        $baseUrl = Config::PRODUCT_MODE === Config::MODE_PROD ? "https://hrm.dev.razum.si" : "http://localhost:8000";
        $htmlLink = $baseUrl . '/web/index.php/leave/viewLeaveRequest/' . $leave->getId();
        $event->setHangoutLink($htmlLink);

        $event->setDescription("Tip: {$leave->getLeaveType()->getName()}
        Čas: {$leave->getLengthDays()}dni, {$leave->getLengthHours()}ur
        Za: {$leave->getEmployee()->getFirstName()} {$leave->getEmployee()->getLastName()}
        Od: {$leave->getStartTime()->format('H:i:s')}
        Do: {$leave->getEndTime()->format('H:i:s')}
        Za več informacij si odpri povezavo: <a href='{$htmlLink}'>{$htmlLink}</a>");

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
        $start = new EventDateTime();
        $end = new EventDateTime();
        $date = $leave->getDate()->format('Y-m-d');
        if ($leave->getDurationType() !== Leave::DURATION_TYPE_FULL_DAY) {
            $start->setDateTime(
                \DateTime::createFromFormat('Y-m-d H:i:s', $date . " " . $leave->getStartTime()->format('H:i:s'), $leave->getStartTime()->getTimezone())
                    ->format(\DateTime::RFC3339)
            );
            $end->setDateTime(
                \DateTime::createFromFormat('Y-m-d H:i:s', $date . " " . $leave->getEndTime()->format('H:i:s'), $leave->getEndTime()->getTimezone())
                    ->format(\DateTime::RFC3339)
            );
        } else {
            $start->setDate($date);
            $end->setDate($date);
        }

        $start->setTimeZone('Europe/Ljubljana');
        $end->setTimeZone('Europe/Ljubljana');

        $event->setStart($start);
        $event->setEnd($end);
    }
}

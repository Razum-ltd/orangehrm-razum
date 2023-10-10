<?php

namespace OrangeHRM\Calendar\Service;

use DateTime;
use Doctrine\ORM\EntityRepository;
use OrangeHRM\Calendar\Api\Base\Events;
use OrangeHRM\Core\Traits\ORM\EntityManagerHelperTrait;
use OrangeHRM\Entity\Leave;
use Google\Service\Calendar\EventDateTime;
use Google_Service_Calendar_Event;

class CalendarService
{
    use EntityManagerHelperTrait;

    public const LEAVE_STATUS_FOR_SYNC = [
        LEAVE::LEAVE_STATUS_LEAVE_TAKEN,
        LEAVE::LEAVE_STATUS_LEAVE_APPROVED
    ];

    protected Events $googleEvents;
    protected EntityRepository $leaveRepository;

    /** @var Leave[] */
    protected array $employeeLeaves;

    /** @var Google_Service_Calendar_Event[] */
    protected array $googleCalendarEvents;

    public function __construct($googleEvents = new Events())
    {
        $this->googleEvents = $googleEvents;
        $this->leaveRepository = $this->getEntityManager()->getRepository(Leave::class);
        $this->employeeLeaves = $this->leaveRepository->findAll();
        $this->googleCalendarEvents = $this->googleEvents->list(Events::CALENDAR_LEAVE_ID);
    }
    /**
     * Sync the employee leaves with the google calendar events.
     * @return array
     */
    public function syncEmployeeAbsence()
    {
        $completed = [];
        $errors = [];
        try {
            if ($this->employeeLeaves && count($this->employeeLeaves) > 0) {
                foreach ($this->employeeLeaves as $employeeLeave) {
                    if ($employeeLeave) {
                        $googleEvent = $this->findGoogleEventById($employeeLeave->getGoogleEventId());
                        // No google calendar event yet, create it.
                        if (!$googleEvent) {
                            // The function check if the event should be created based on the leave status. This could be improved...
                            $this->createNewGoogleEventFromEmployeeLeave($employeeLeave);
                            continue;
                        }
                        // The event exists on the calendar but the status has changed in the db, delete it from google calendar.
                        if ((in_array($employeeLeave->getStatus(), self::LEAVE_STATUS_FOR_SYNC))) {
                            $this->googleEvents->delete(Events::CALENDAR_LEAVE_ID, $googleEvent->getId());
                            continue;
                        }
                        // The event should be on the calendar, but is all the data correct?
                        $this->checkIfGoogleEventHasCorrectData($employeeLeave, $googleEvent);
                        // The event is correct, add it to the completed array.
                        $employee = $employeeLeave->getEmployee();
                        $completed[] = Events::CreateEventTitle($employee, $employeeLeave);
                    }
                }
            }
        } catch (\Exception $error) {
            $errors[] = $error->getMessage();
        }
        return [
            'completed' => $completed,
            'errors' => $errors
        ];
    }


    /**
     * @param Leave $leave
     * @param Google_Service_Calendar_Event $event
     * @return void
     */
    private function checkIfGoogleEventHasCorrectData(&$leave, &$event)
    {
        $employee = $leave->getEmployee();
        $neededEventTitle = Events::CreateEventTitle($employee, $leave);

        if (
                // If the leave is a full day leave, the event should not contain hours
            ($leave->getDurationType() == Leave::DURATION_TYPE_FULL_DAY && $this->checkIfDatetimeContainsHours($event->getStart())) ||
                // If the leave is not a full day leave, the event should contain hours
            ($leave->getDurationType() != Leave::DURATION_TYPE_FULL_DAY && !$this->checkIfDatetimeContainsHours($event->getStart())) ||
                // If the event title is not correct, update it
            ($event->getSummary() != $neededEventTitle)
        ) {
            $event->setSummary($neededEventTitle);
            Events::SetEventTime($event, $leave);
            $this->googleEvents->update(Events::CALENDAR_LEAVE_ID, $event);
        }
    }

    /**
     * If the datetime provided contains hours, it means that the leave is not a full day leave
     * @param EventDateTime $datetime
     * @return bool
     */
    private function checkIfDatetimeContainsHours($datetime)
    {
        $timeToCheck = $datetime->getDateTime() ?? $datetime->getDate();
        $dateTime = new DateTime($timeToCheck);
        return $dateTime->format('H:i:s') != '00:00:00';
    }

    /**
     * @param Leave $leave
     */
    private function createNewGoogleEventFromEmployeeLeave(&$leave)
    {
        $event = $this->googleEvents->createNewLeaveEvent($leave->getEmployee(), $leave);
        if ($event) {
            $leave->setGoogleEventId($event->getId());
            $this->getEntityManager()->persist($leave);
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param string $googleEventId
     * @return Google_Service_Calendar_Event|null
     */
    private function findGoogleEventById($googleEventId)
    {
        if ($googleEventId) {
            foreach ($this->googleCalendarEvents as $googleCalendarEvent) {
                if ($googleCalendarEvent->getId() === $googleEventId) {
                    return $googleCalendarEvent;
                }
            }
        }
        return null;
    }
}

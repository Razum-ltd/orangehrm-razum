<?php
namespace OrangeHRM\Google\Service;

use Doctrine\ORM\EntityRepository;
use OrangeHRM\Core\Traits\ORM\EntityManagerHelperTrait;
use OrangeHRM\Entity\Leave;
use OrangeHRM\Google\Api\Calendar\Events;

class CalendarService
{
    use EntityManagerHelperTrait;

    const LEAVE_STATUS_FOR_SYNC = [
        LEAVE::LEAVE_STATUS_LEAVE_APPROVED,
        LEAVE::LEAVE_STATUS_LEAVE_TAKEN
    ];

    private Events $googleEvents;
    private EntityRepository $leaveRepository;

    /** @var Leave[] */
    private array $employeeLeaves;

    /** @var \Google_Service_Calendar_Event[] */
    private array $googleCalendarEvents;


    public function __construct($googleEvents = new Events())
    {
        $this->googleEvents = $googleEvents;
        $this->leaveRepository = $this->getEntityManager()->getRepository(Leave::class);
        $this->employeeLeaves = $this->getAllEmployeeLeaves();
        $this->googleCalendarEvents = $this->getEventsFromLeaveCalendar();
    }

    public function syncEmployeeAbsence()
    {
        foreach ($this->employeeLeaves as $employeeLeave) {
            $googleEvent = $this->findGoogleEventById($employeeLeave->getGoogleEventId());
            // No google calendar event yet, create it.
            if (!$googleEvent) {
                // THe function check if the event should be created based on the leave status. This could be improved...
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
        }
    }


    /**
     * @param Leave $leave
     * @param \Google_Service_Calendar_Event $event
     * @return void
     */
    private function checkIfGoogleEventHasCorrectData(&$leave, &$event)
    {
        $eventNeedsToUpdate = false;
        if ($leave->getDurationType() == Leave::DURATION_TYPE_FULL_DAY) {
            if ($this->checkIfDatetimeContainsHours($event->getStart())) {
                // update the event
                $eventNeedsToUpdate = true;
            }
        } else {
            if (!$this->checkIfDatetimeContainsHours($event->getStart())) {
                // update the event
                $eventNeedsToUpdate = true;
            }
        }
        $employee = $leave->getEmployee();
        $neededEventTitle = Events::CreateEventTitle($employee, $leave);
        if ($event->getSummary() != $neededEventTitle) {
            $eventNeedsToUpdate = true;
            $event->setSummary($neededEventTitle);
        }

        if ($eventNeedsToUpdate) {
            $this->googleEvents->update(Events::CALENDAR_LEAVE_ID, $event);
        }
    }

    /**
     * If the datetime provided contains hours, it means that the leave is not a full day leave
     * @param \Google\Service\Calendar\EventDateTime $datetime
     * @return bool
     */
    private function checkIfDatetimeContainsHours($datetime)
    {
        $timeToCheck = $datetime->getDateTime() ?? $datetime->getDate();
        $dateTime = new \DateTime($timeToCheck);
        return $dateTime->format('H:i:s') != '00:00:00';
    }

    /**
     * @param Leave $leave
     */
    private function createNewGoogleEventFromEmployeeLeave($leave)
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
     * @return \Google_Service_Calendar_Event|null
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

    /**
     * @return \Google_Service_Calendar_Event[]
     */
    private function getEventsFromLeaveCalendar()
    {
        return $this->googleEvents->list(Events::CALENDAR_LEAVE_ID);
    }

    /**
     * @return Leave[]
     */
    private function getAllEmployeeLeaves()
    {
        return $this->leaveRepository->findAll();
    }
}
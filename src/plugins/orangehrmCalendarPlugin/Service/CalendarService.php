<?php

namespace OrangeHRM\Calendar\Service;

use DateTime;
use Doctrine\ORM\EntityRepository;
use OrangeHRM\Calendar\Api\Base\Events;
use OrangeHRM\Core\Traits\LoggerTrait;
use OrangeHRM\Core\Traits\ORM\EntityManagerHelperTrait;
use OrangeHRM\Entity\Leave;
use Google\Service\Calendar\EventDateTime;
use Google_Service_Calendar_Event;

class CalendarService
{
    use EntityManagerHelperTrait;
    use LoggerTrait;

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
        $this->employeeLeaves = $this->leaveRepository->findBy([]); // get all leave records
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
        if ($this->employeeLeaves && count($this->employeeLeaves) > 0) {
            foreach ($this->employeeLeaves as $employeeLeave) {
                try {
                    $googleEvent = $this->findGoogleEventById($employeeLeave->getGoogleEventId());
                    // No google calendar event yet, create it.
                    if (!$googleEvent) {
                        if (!in_array($employeeLeave->getStatus(), self::LEAVE_STATUS_FOR_SYNC)) {
                            $this->getLogger()->info("No event for leave: " . $employeeLeave->getId() . " as the status is not correct.");
                            continue;
                        }
                        // Create the event as the status is OK.
                        // If the event is not created, throw an exception. (the google_event_id will not be added in the db)
                        $this->getLogger()->info("Creating event for leave: " . $employeeLeave->getId());
                        $this->createNewGoogleEventFromEmployeeLeave($employeeLeave);
                        $this->addToArray($completed, $employeeLeave);
                        continue;
                    }
                    // The event exists on the calendar but the status has changed in the db, delete it from google calendar.
                    if (!(in_array($employeeLeave->getStatus(), self::LEAVE_STATUS_FOR_SYNC))) {
                        $this->getLogger()->info("Deleting event: " . $googleEvent->getId() . " for leave: " . $employeeLeave->getId() . " as the status is not correct.");
                        $this->googleEvents->delete(Events::CALENDAR_LEAVE_ID, $googleEvent->getId());
                        $this->addToArray($completed, $employeeLeave);
                        continue;
                    }
                    $this->checkIfGoogleEventHasCorrectData($employeeLeave, $googleEvent);
                    $this->addToArray($completed, $employeeLeave);
                } catch (\Exception $error) {
                    $this->getLogger()->error($error->getMessage());
                    $errors[] = $error->getMessage();
                }
            }
        }
        $this->getEntityManager()->flush();
        return [
            'completed' => $completed,
            'errors' => $errors,
        ];
    }

    /**
     * Add the event to the completed array (for the response)
     * @param array $completed
     * @param Leave $employeeLeave
     */
    private function addToArray(&$array, &$employeeLeave)
    {
        $employee = $employeeLeave->getEmployee();
        $array[] = Events::CreateEventTitle($employee, $employeeLeave);
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
            $this->getLogger()->info("Updating event: " . $event->getId() . " for leave: " . $leave->getId());
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

        if (!$event) {
            throw new \Exception('Could not create event for leave: ' . $leave->getId());
        }

        $leave->setGoogleEventId($event->getId());
        $this->getEntityManager()->persist($leave);
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

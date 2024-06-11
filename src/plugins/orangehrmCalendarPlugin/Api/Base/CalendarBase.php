<?php


namespace OrangeHRM\Calendar\Api\Base;

use Exception;
use Google\Service\Calendar\EventDateTime;
use OrangeHRM\Calendar\Traits\Api\GoogleCalendarServiceTrait;
use OrangeHRM\Config\Config;
use OrangeHRM\Entity\Leave;
use OrangeHRM\Entity\LeaveRequest;

class CalendarBase
{
    use GoogleCalendarServiceTrait;

    private string $calendarId;


    public function __construct()
    {
        $this->calendarId = getenv("GOOGLE_CALENDAR_ID");
    }

    public const EVENT_TIME_FORMAT = "H:i:s";

    public const TIMEZONE = "+02:00";

    /**
     * Characters allowed in the ID are those used in base32hex encoding, i.e. lowercase letters a-v and digits 0-9, see section 3.1.2 in RFC2938
     * Id convention is leaveevent{leave_id}
     */
    public const EVENT_ID_PREFIX = "leaveevent";

    public const PARTIAL_DAY_LEAVE = 6;
    public const LEAVE_STATUS_HOLIDAY = 5;
    public const LEAVE_STATUS_WEEKEND = 4;

    /**
     * @param LeaveRequest $leaveRequest
     */
    public function createEvent($leaveRequest)
    {
        if (!$leaveRequest instanceof LeaveRequest)
            return;


        $leaves = [];

        foreach ($leaveRequest->getLeaves() as $leave) {

            // Filter out weekends and holidays
            $status = $leave->getStatus();
            if ($status === self::LEAVE_STATUS_HOLIDAY || $status === self::LEAVE_STATUS_WEEKEND) {
                continue;
            }
            $leaves[] = $leave;
        }

        // Local dev =  "http://localhost:8000/web/index.php/leave/viewLeaveRequest/"
        //$htmlLink = Config::PRODUCT_MODE === Config::MODE_PROD ? "https://hrm.dev.razum.si" : "http://localhost:8000/web/index.php/leave/viewLeaveRequest/" . $leaveRequest->getId();
        $htmlLink = "https://hrm.dev.razum.si/web/index.php/leave/viewLeaveRequest/" . $leaveRequest->getId();

        $event = new \Google_Service_Calendar_Event();

        $event->setId(self::EVENT_ID_PREFIX . $leaveRequest->getId());
        $event->setColorId('6');
        $event->setSummary(self::normalizeEventName($leaveRequest));
        $event->setDescription("Tip: {$leave->getLeaveType()->getName()}
            Za: {$leave->getEmployee()->getFirstName()} {$leave->getEmployee()->getLastName()}
            Za več informacij si odpri povezavo: <a href='{$htmlLink}'>{$htmlLink}</a>");

        $event->setStart(self::normalizeEventDateFromLeave($leaves[0], true));
        $event->setEnd(self::normalizeEventDateFromLeave(end($leaves), false));

        $event->setStatus("confirmed");
        $event->setVisibility("public");

        try {
            $newEvent = $this->getCalendarService()->events->insert($this->calendarId, $event);
        } catch (\Google_Service_Exception $e) {
            return $e;
        }

        return $newEvent;
    }


    /**
     * @param LeaveRequest $leaveRequest
     */
    public function deleteEvent($leaveRequest)
    {
        if (!$leaveRequest instanceof LeaveRequest)
            return;

        $id = $leaveRequest->getId();

        if (!$id)
            return;

        $eventId = self::EVENT_ID_PREFIX . $id;

        try {
            $this->getCalendarService()->events->delete($this->calendarId, $eventId);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

    }

    /**
     * @param Leave $leave Leave object
     * @param bool $start flag if we are dealing with start date or end date of leave 
     */
    public function normalizeEventDateFromLeave($leave, $start)
    {
        if (!$leave instanceof Leave) {
            throw new Exception("leave is not instance of Entity\Leave");
        }

        $leaveType = $leave->getLeaveType()->getId();

        // All leave types have full day duration, except short term leave.
        $leaveTypeAllDay = $leaveType !== self::PARTIAL_DAY_LEAVE;

        $date = $leave->getDate();

        $eventDate = new EventDateTime($date);

        // Non all-day leave types require DateTime instead of Date
        if (!$leaveTypeAllDay) {
            $leaveDate = $date->format("Y-m-d");
            /**  
             * Google says that we should format to RFC3339 format for setDateTime object
             * but they don't like it. So we structure the object like it's in the example
             * https://developers.google.com/calendar/api/v3/reference/events/insert
             * */
            if ($start) {
                $leaveStartTime = $leave->getStartTime()->format(self::EVENT_TIME_FORMAT);
                $eventDate->setDateTime($leaveDate . "T" . $leaveStartTime . self::TIMEZONE);
            } else {
                $leaveEndTime = $leave->getEndTime()->format(self::EVENT_TIME_FORMAT);
                $eventDate->setDateTime($leaveDate . "T" . $leaveEndTime . self::TIMEZONE);
            }
        } else {
            if ($start) {
                $eventDate->setDate($date->format('Y-m-d'));
            }
            /**  
             * For event end date, we need to add +1 day
             * The (exclusive) end time of the event. For a recurring event, this is the end time of the first instance.
             */ else {
                $eventDate->setDate($date->modify('+1 day')->format('Y-m-d'));
            }
        }

        return $eventDate;
    }

    /**
     * @param LeaveRequest $leaveRequest
     * Returns Event summary ready format like 'First Name - Leave Type'
     */
    public function normalizeEventName($leaveRequest)
    {
        $leaveType = strtok($leaveRequest->getLeaveType()->getName(), "-");

        return $leaveRequest->getEmployee()->getFirstName()
            . " "
            . $leaveRequest->getEmployee()->getLastName()
            . " - "
            . $leaveType;
    }
}
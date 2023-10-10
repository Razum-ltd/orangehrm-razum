<?php

namespace OrangeHRM\Calendar\Api\Base;

use OrangeHRM\Calendar\Traits\Api\GoogleCalendarServiceTrait;
use Google_Service_Calendar_Calendar;

class Calendars
{
    use GoogleCalendarServiceTrait;

    /**
     * Clears a primary calendar. This operation deletes all events associated with the primary calendar of an account.
     * @param string $calendarId
     */
    public function clear($calendarId = "primary")
    {
        $this->getCalendarService()->calendars->clear($calendarId);
    }

    /**
     * Deletes a secondary calendar. Use calendars.clear for clearing all events on primary calendars.
     * @param string $calendarId
     */
    public function delete($calendarId)
    {
        $this->getCalendarService()->calendars->delete($calendarId);
    }

    /**
     * Returns metadata for a calendar.
     * @param string $calendarId
     * @return Google_Service_Calendar_Calendar
     */
    public function get($calendarId)
    {
        return $this->getCalendarService()->calendars->get($calendarId);
    }

    /**
     * @param string $calendarId
     * @param Google_Service_Calendar_Calendar $calendar
     * @return Google_Service_Calendar_Calendar
     */
    public function update($calendarId, $calendar)
    {
        return $this->getCalendarService()->calendars->update($calendarId, $calendar);
    }
}

<?php

namespace OrangeHRM\Google\Api\Calendar;

class CalendarList
{
    use CalendarServiceTrait;

    /**
     * @param string $calendarId
     * @return \Google_Service_Calendar_CalendarListEntry
     */
    public function delete($calendarId)
    {
        return $this->getCalendarService()->calendarList->delete($calendarId);
    }

    /**
     * @param string $calendarId
     * @return \Google_Service_Calendar_CalendarListEntry
     */
    public function get($calendarId)
    {
        return $this->getCalendarService()->calendarList->get($calendarId);
    }

    /**
     * @param array $optParams
     * @return \Google_Service_Calendar_CalendarList
     */
    public function list($optParams = [])
    {
        return $this->getCalendarService()->calendarList->listCalendarList($optParams);
    }
}
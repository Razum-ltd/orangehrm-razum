<?php

namespace OrangeHRM\Calendar\Traits\Service;

use OrangeHRM\Framework\Services;
use OrangeHRM\Calendar\Service\CalendarService;
use OrangeHRM\Core\Traits\ServiceContainerTrait;

trait CalendarServiceTrait
{
    use ServiceContainerTrait;

    /**
     * @return CalendarService
     */
    protected function getCalendarService()
    {
        return $this->getContainer()->get(Services::CALENDAR_SERVICE);
    }
}

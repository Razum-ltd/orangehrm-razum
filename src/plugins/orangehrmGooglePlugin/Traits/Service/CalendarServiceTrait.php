<?php
namespace OrangeHRM\Google\Traits\Service;

use OrangeHRM\Core\Traits\ServiceContainerTrait;

trait CalendarServiceTrait
{
    use ServiceContainerTrait;

    /**
     * @return \OrangeHRM\Google\Service\CalendarService
     */
    protected function getCalendarService()
    {
        return $this->getContainer()->get(Services::CALENDAR_SERVICE);
    }
}
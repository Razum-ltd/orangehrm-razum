<?php
namespace OrangeHRM\Google\Api\Calendar;

use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\ResourceEndpoint;
use OrangeHRM\Core\Traits\ServiceContainerTrait;
use OrangeHRM\Google\Service\CalendarService;

class CalendarAPI extends Endpoint implements ResourceEndpoint
{
    use ServiceContainerTrait;

    /**
     * @return CalendarService
     */
    private function getCalendarService()
    {
        return $this->getContainer()->get(Services::CALENDAR_SERVICE);
    }
    /**
     * @OA\Get(
     *     path="/api/v2/google/calendar/sync",
     *     tags={"Google/Calendar"},
     *     @OA\Response(response="200")
     * )
     * @inheritDoc
     */
    public function getOne()
    {
        $this->getCalendarService()->syncEmployeeAbsence();
    }
    public function delete()
    {
        $this->getNotImplementedException();
    }
    public function update()
    {
        $this->getNotImplementedException();
    }

    public function getValidationRuleForGetOne()
    {
        $this->getNotImplementedException();
    }
    public function getValidationRuleForUpdate()
    {
        $this->getNotImplementedException();
    }
    public function getValidationRuleForDelete()
    {
        $this->getNotImplementedException();
    }
}
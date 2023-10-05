<?php
namespace OrangeHRM\Google\Api\Calendar;

use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointResourceResult;
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
     *     @OA\Response(
     *         response="200",
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Message for the sync result",
     *             ),
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 description="Message of the error",
     *             ),
     *         )
     *     )
     * )
     * @inheritDoc
     */
    public function getOne(): EndpointResourceResult
    {
        try {
            $this->getCalendarService()->syncEmployeeAbsence();
            return new EndpointResourceResult(ArrayModel::class, [
                "message" => "Sync completed"
            ]);
        } catch (\Exception $error) {
            return new EndpointResourceResult(ArrayModel::class, [
                "message" => "Error during sync",
                "error" => $error->getMessage()
            ]);
        }
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
<?php
namespace OrangeHRM\Google\Api\Calendar;

use OrangeHRM\Core\Api\V2\CrudEndpoint;
use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointResourceResult;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Google\Traits\Service\CalendarServiceTrait;

class CalendarAPI extends Endpoint implements CrudEndpoint
{
    use CalendarServiceTrait;

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
    public function getAll(): EndpointResourceResult
    {
        try {
            return new EndpointResourceResult(
                ArrayModel::class,
                $this->getCalendarService()->syncEmployeeAbsence()
            );
        } catch (\Exception $error) {
            return new EndpointResourceResult(ArrayModel::class, [
                "message" => "Error during sync",
                "error" => $error->getMessage()
            ]);
        }
    }

    public function getValidationRuleForGetAll(): ParamRuleCollection
    {
        return new ParamRuleCollection();
    }
    public function getOne()
    {
        $this->getNotImplementedException();
    }
    public function getValidationRuleForGetOne()
    {
        $this->getNotImplementedException();
    }
    public function create()
    {
        $this->getNotImplementedException();
    }
    public function getValidationRuleForCreate()
    {
        $this->getNotImplementedException();
    }

    public function delete()
    {
        $this->getNotImplementedException();
    }
    public function getValidationRuleForDelete()
    {
        $this->getNotImplementedException();
    }
    public function update()
    {
        $this->getNotImplementedException();
    }
    public function getValidationRuleForUpdate()
    {
        $this->getNotImplementedException();
    }
}
<?php

namespace OrangeHRM\Calendar\Api;

use OrangeHRM\Core\Api\V2\CollectionEndpoint;
use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointResourceResult;
use OrangeHRM\Core\Api\V2\EndpointResult;
use OrangeHRM\Core\Api\V2\Exception\NotImplementedException;
use OrangeHRM\Core\Api\V2\Model\ArrayModel;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Calendar\Traits\Service\CalendarServiceTrait;

class CalendarAPI extends Endpoint implements CollectionEndpoint
{
    use CalendarServiceTrait;

    /**
     * @inheritDoc
     */
    public function getAll(): EndpointResourceResult
    {
        $service = $this->getCalendarService();
        return new EndpointResourceResult(
            ArrayModel::class,
            [var_dump($service)] // $service->syncEmployeeAbsence()
        );
        /* try {

        } catch (\Exception $error) {
            return new EndpointResourceResult(ArrayModel::class, [$error->getMessage()]);
        } */
    }
    /**
     * @inheritDoc
     */
    public function getValidationRuleForGetAll(): ParamRuleCollection
    {
        return new ParamRuleCollection();
    }
    /**
     * @inheritDoc
     * @throws NotImplementedException
     */
    public function create(): EndpointResourceResult
    {
        throw new NotImplementedException();
    }
    /**
     * @inheritDoc
     * @throws NotImplementedException
     */
    public function getValidationRuleForCreate(): ParamRuleCollection
    {
        throw new NotImplementedException();
    }
    /**
     * @inheritDoc
     * @throws NotImplementedException
     */
    public function delete(): EndpointResourceResult
    {
        throw new NotImplementedException();
    }
    /**
     * @inheritDoc
     * @throws NotImplementedException
     */
    public function getValidationRuleForDelete(): ParamRuleCollection
    {
        throw new NotImplementedException();
    }
    /**
     * @inheritDoc
     * @throws NotImplementedException
     */
    public function update(): EndpointResult
    {
        throw $this->getNotImplementedException();
    }
    /**
     * @inheritDoc
     * @throws NotImplementedException
     */
    public function getValidationRuleForUpdate(): ParamRuleCollection
    {
        throw $this->getNotImplementedException();
    }
}

<?php

namespace OrangeHRM\Attendance\Api;

use OrangeHRM\Attendance\Traits\Service\AttendanceCorrectionServiceTrait;
use OrangeHRM\Core\Api\V2\CollectionEndpoint;
use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointCollectionResult;
use OrangeHRM\Core\Api\V2\EndpointResult;
use OrangeHRM\Core\Api\V2\Exception\NotImplementedException;
use OrangeHRM\Core\Api\V2\Model\ArrayModel;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;

class AttendanceCorrectionAPI extends Endpoint implements CollectionEndpoint
{
    use AttendanceCorrectionServiceTrait;

    public function getAll(): EndpointResult
    {
        $model = [];
        try {
            $this->getAttendanceCorrectionService()
                ->runCorrection();
            $model['message'] = 'Attendance correction completed successfully';
        } catch (\Throwable $th) {
            $model['error'] = $th->getMessage();
        }

        return new EndpointCollectionResult(
            ArrayModel::class,
            [$model]
        );
    }

    public function getValidationRuleForGetAll(): ParamRuleCollection
    {
        return new ParamRuleCollection();
    }

    public function create(): EndpointResult
    {
        throw new NotImplementedException();
    }

    public function getValidationRuleForCreate(): ParamRuleCollection
    {
        throw new NotImplementedException();
    }

    public function delete(): EndpointResult
    {
        throw new NotImplementedException();
    }

    public function getValidationRuleForDelete(): ParamRuleCollection
    {
        throw new NotImplementedException();
    }
}

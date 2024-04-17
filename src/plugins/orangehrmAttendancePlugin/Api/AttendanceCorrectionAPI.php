<?php

namespace OrangeHRM\Attendance\Api;

use OrangeHRM\Attendance\Api\Model\AttendanceRecordModel;
use OrangeHRM\Attendance\Traits\Service\AttendanceCorrectionServiceTrait;
use OrangeHRM\Core\Api\V2\CollectionEndpoint;
use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointCollectionResult;
use OrangeHRM\Core\Api\V2\EndpointResult;
use OrangeHRM\Core\Api\V2\Exception\NotImplementedException;
use OrangeHRM\Core\Api\V2\Model\ArrayModel;
use OrangeHRM\Core\Api\V2\RequestParams;
use OrangeHRM\Core\Api\V2\Validator\ParamRule;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Core\Api\V2\Validator\Rule;
use OrangeHRM\Core\Api\V2\Validator\Rules;

class AttendanceCorrectionAPI extends Endpoint implements CollectionEndpoint
{
    use AttendanceCorrectionServiceTrait;

    public const PARAMETER_ATTENDANCE_CORRECTION_DATE = "date";

    public function getAll(): EndpointResult
    {

        $date = $this->getRequestParams()->getDateTimeOrNull(
            RequestParams::PARAM_TYPE_QUERY,
            'date'
        );

        $model = [];
        try {
            $model['message'] = $this->getAttendanceCorrectionService()->runCorrection($date);
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
        return new ParamRuleCollection(
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(
                    self::PARAMETER_ATTENDANCE_CORRECTION_DATE,
                    new Rule(Rules::DATE)
                )
            )
        );
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

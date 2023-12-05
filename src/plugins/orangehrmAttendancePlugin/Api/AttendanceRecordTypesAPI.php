<?php

namespace OrangeHRM\Attendance\Api;

use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointResourceResult;
use OrangeHRM\Core\Api\V2\EndpointResult;
use OrangeHRM\Core\Api\V2\Model\ArrayModel;
use OrangeHRM\Core\Api\V2\ResourceEndpoint;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Entity\AttendanceRecord;

class AttendanceRecordTypesAPI extends Endpoint implements ResourceEndpoint
{
    public function getOne(): EndpointResult
    {
        return new EndpointResourceResult(ArrayModel::class, []);
    }

    public function getValidationRuleForGetOne(): ParamRuleCollection
    {
        return new ParamRuleCollection();
    }

    public function update(): EndpointResult
    {
        return new EndpointResourceResult(ArrayModel::class, []);
    }

    public function getValidationRuleForUpdate(): ParamRuleCollection
    {
        return new ParamRuleCollection();
    }

    public function delete(): EndpointResult
    {
        return new EndpointResourceResult(ArrayModel::class, []);
    }

    public function getValidationRuleForDelete(): ParamRuleCollection
    {
        return new ParamRuleCollection();
    }

    public function getAll(): EndpointResult
    {
        return new EndpointResourceResult(
            ArrayModel::class,
            [
                AttendanceRecord::ATTENDANCE_TYPE_WORK_TIME,
                AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME,
                AttendanceRecord::ATTENDANCE_TYPE_OVER_TIME,
                AttendanceRecord::ATTENDANCE_TYPE_OFF_TIME,
                AttendanceRecord::ATTENDANCE_TYPE_HOLIDAY,
            ]
        );
    }

    public function getValidationRuleForGetAll(): ParamRuleCollection
    {
        return new ParamRuleCollection();
    }

    public function create(): EndpointResult
    {
        return new EndpointResourceResult(ArrayModel::class, []);
    }

    public function getValidationRuleForCreate(): ParamRuleCollection
    {
        return new ParamRuleCollection();
    }
}

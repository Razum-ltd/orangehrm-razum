<?php
/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software; you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation; either
 * version 2 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program;
 * if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor,
 * Boston, MA  02110-1301, USA
 */

namespace OrangeHRM\Attendance\Api;

use OrangeHRM\Attendance\Api\Model\EmployeeLatestAttendanceRecordModel;
use OrangeHRM\Attendance\Traits\Service\AttendanceServiceTrait;
use OrangeHRM\Core\Api\CommonParams;
use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointResourceResult;
use OrangeHRM\Core\Api\V2\EndpointResult;
use OrangeHRM\Core\Api\V2\ParameterBag;
use OrangeHRM\Core\Api\V2\RequestParams;
use OrangeHRM\Core\Api\V2\ResourceEndpoint;
use OrangeHRM\Core\Api\V2\Validator\ParamRule;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Core\Api\V2\Validator\Rule;
use OrangeHRM\Core\Api\V2\Validator\Rules;
use OrangeHRM\Core\Traits\Auth\AuthUserTrait;
use OrangeHRM\Core\Traits\Service\DateTimeHelperTrait;
use OrangeHRM\Entity\AttendanceRecord;

class EmployeeLatestAttendanceRecordAPI extends Endpoint implements ResourceEndpoint
{
    use AttendanceServiceTrait;
    use AuthUserTrait;
    use DateTimeHelperTrait;

    /**
     * @OA\Get(
     *     path="/api/v2/attendance/records/latest",
     *     tags={"Attendance/Attendance Record Latest"},
     *     @OA\Parameter(
     *         name="empNumber",
     *         in="query",
     *         required=false,
     *         description="Employee Number",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/Attendance-EmployeeLatestAttendanceRecordModel"
     *             ),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response="404", ref="#/components/responses/RecordNotFound")
     * )
     *
     * @inheritDoc
     */
    public function getOne(): EndpointResult
    {
        $employeeNumber = $this->getRequestParams()->getInt(
            RequestParams::PARAM_TYPE_QUERY,
            CommonParams::PARAMETER_EMP_NUMBER,
            $this->getAuthUser()->getEmpNumber()
        );

        $attendanceRecord = $this->getAttendanceService()
            ->getAttendanceDao()
            ->getLatestAttendanceRecordByEmployeeNumber($employeeNumber);

        $breakRecords = $this->getAttendanceService()->getAttendanceDao()->getBreakPunchRecordList(
            $employeeNumber,
            $attendanceRecord->getPunchInUtcTime()
        );

        $breakRecordsArray = [];

        foreach ($breakRecords as $breakRecord) {
            if ($breakRecord instanceof AttendanceRecord) {
                $metaRecord = [
                    'id' => $breakRecord->getId(),
                    'breakNote' => $breakRecord->getPunchInNote(),
                    'startTime' => $this->getDateTimeHelper()->formatDateTimeToTimeString($breakRecord->getPunchInUserTime()),
                    'endTime' => $this->getDateTimeHelper()->formatDateTimeToTimeString($breakRecord->getPunchOutUtcTime())
                ];

                $breakRecordsArray[] = $metaRecord;
            }
        }

        $this->throwRecordNotFoundExceptionIfNotExist($attendanceRecord, AttendanceRecord::class);

        return new EndpointResourceResult(EmployeeLatestAttendanceRecordModel::class, $attendanceRecord, new ParameterBag($breakRecordsArray));
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForGetOne(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            new ParamRule(
                CommonParams::PARAMETER_ID,
            ),
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(
                    CommonParams::PARAMETER_EMP_NUMBER,
                    new Rule(Rules::IN_ACCESSIBLE_EMP_NUMBERS)
                )
            ),
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(
                    EmployeeAttendanceRecordAPI::PARAMETER_ATTENDANCE_TYPE,
                    new Rule(Rules::STRING_TYPE),
                ),
                true
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function update(): EndpointResult
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForUpdate(): ParamRuleCollection
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function delete(): EndpointResult
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForDelete(): ParamRuleCollection
    {
        throw $this->getNotImplementedException();
    }
}

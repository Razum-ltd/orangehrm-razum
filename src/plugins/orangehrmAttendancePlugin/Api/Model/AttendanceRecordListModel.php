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

namespace OrangeHRM\Attendance\Api\Model;

use OrangeHRM\Core\Api\V2\Serializer\CollectionNormalizable;
use OrangeHRM\Core\Api\V2\Serializer\ModelConstructorArgsAwareInterface;
use OrangeHRM\Core\Traits\Service\DateTimeHelperTrait;
use OrangeHRM\Core\Traits\Service\NumberHelperTrait;
use OrangeHRM\Entity\AttendanceRecord;

/**
 * @OA\Schema(
 *     schema="Attendance-AttendanceRecordListModel",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(
 *         property="punchIn",
 *         type="object",
 *         @OA\Property(property="userDate", type="string", format="date"),
 *         @OA\Property(property="userTime", type="string"),
 *         @OA\Property(property="offset", type="string"),
 *         @OA\Property(property="note", type="string")
 *     ),
 *     @OA\Property(
 *         property="punchOut",
 *         type="object",
 *         @OA\Property(property="userDate", type="string", format="date"),
 *         @OA\Property(property="userTime", type="string"),
 *         @OA\Property(property="offset", type="string"),
 *         @OA\Property(property="note", type="string")
 *     ),
 *     @OA\Property(property="duration", type="integer"),
 * )
 */
class AttendanceRecordListModel implements CollectionNormalizable, ModelConstructorArgsAwareInterface
{
    use NumberHelperTrait;
    use DateTimeHelperTrait;

    /**
     * @var array
     */
    private array $attendanceRecords;

    public function __construct(array $attendanceRecords)
    {
        $this->attendanceRecords = $attendanceRecords;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        $result = [];

        // Filter work records so we can properly map breaks to the work records
        $workAttendanceRecords = array_filter($this->attendanceRecords, function ($ar) {
            return $ar['attendanceType'] !== AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME;
        });

        foreach ($workAttendanceRecords as $employeeAttendanceRecord) {
            
            // Break records matched by date of current work record
            $breaks = array_filter($this->attendanceRecords, function ($ar) use ($employeeAttendanceRecord) {
                return $ar['attendanceType'] === AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME &&
                    $this->getDateTimeHelper()->formatDateTimeToYmd($ar['punchInTime']) ===
                    $this->getDateTimeHelper()->formatDateTimeToYmd($employeeAttendanceRecord['punchInTime']);
            });

            $result[] = [
                'id' => $employeeAttendanceRecord['id'],
                'punchIn' => [
                    'userDate' => $this->getDateTimeHelper()->formatDateTimeToYmd(
                        $employeeAttendanceRecord['punchInTime']
                    ),
                    'userTime' => $this->getDateTimeHelper()->formatDateTimeToTimeString(
                        $employeeAttendanceRecord['punchInTime']
                    ),
                    'offset' => $employeeAttendanceRecord['punchInTimeOffset'],
                    'note' => $employeeAttendanceRecord['punchInNote'],
                ],
                'punchOut' => [
                    'userDate' => $this->getDateTimeHelper()->formatDateTimeToYmd(
                        $employeeAttendanceRecord['punchOutTime']
                    ),
                    'userTime' => $this->getDateTimeHelper()->formatDateTimeToTimeString(
                        $employeeAttendanceRecord['punchOutTime']
                    ),
                    'offset' => $employeeAttendanceRecord['punchOutTimeOffset'],
                    'note' => $employeeAttendanceRecord['punchOutNote'],
                ],
                'attendanceType' => $employeeAttendanceRecord['attendanceType'],
                'duration' => $this->getNumberHelper()
                    ->numberFormat((float) $employeeAttendanceRecord['total'] / 3600, 2),
                'breaks' => array_map(function ($break) {
                    $break['punchInTime'] = $this->getDateTimeHelper()->formatDateTimeToTimeString(
                        $break['punchInTime']
                    );
                    $break['punchOutTime'] = $this->getDateTimeHelper()->formatDateTimeToTimeString(
                        $break['punchOutTime']
                    );
                    return $break;
                }, $breaks)
            ];
        }
        return $result;
    }
}

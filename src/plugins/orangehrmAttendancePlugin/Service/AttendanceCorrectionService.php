<?php

namespace OrangeHRM\Attendance\Service;

use OrangeHRM\Attendance\Dto\AttendanceRecordSearchFilterParams;
use OrangeHRM\Attendance\Traits\Service\AttendanceServiceTrait;
use OrangeHRM\Core\Traits\LoggerTrait;
use OrangeHRM\Entity\AttendanceRecord;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Leave\Traits\Service\LeaveRequestServiceTrait;
use OrangeHRM\Leave\Dto\EmployeeLeaveSearchFilterParams;

class AttendanceCorrectionService
{
    use LeaveRequestServiceTrait;
    use AttendanceServiceTrait;
    use LoggerTrait;

    /** @var AttendanceRecord[] $allEmployeeAttendance */
    private ?array $allEmployeeAttendance = null;

    /** @var \OrangeHRM\Entity\Leave[] $allEmployeeLeaves */
    private ?array $allEmployeeLeaves = null;

    public function __construct()
    {
        // get current date in datetime
        $currentDate = \DateTime::createFromFormat('Y-m-d', date('Y-m-d'));

        // set all employee attendance
        $attendanceRecordSearchFilterParams = new AttendanceRecordSearchFilterParams();
        $attendanceRecordSearchFilterParams->setFromDate($currentDate);
        $attendanceRecordSearchFilterParams->setToDate($currentDate);
        $this->allEmployeeAttendance = $this->getAttendanceService()
            ->getAttendanceDao()
            ->getAttendanceRecordList($attendanceRecordSearchFilterParams);

        // set all employee leaves
        $employeeLeaveSearchFilterParams = new EmployeeLeaveSearchFilterParams();
        $employeeLeaveSearchFilterParams->setFromDate($currentDate);
        $employeeLeaveSearchFilterParams->setToDate($currentDate);
        $this->allEmployeeLeaves = $this->getLeaveRequestService()
            ->getLeaveRequestDao()
            ->getEmployeeLeaves($employeeLeaveSearchFilterParams);
    }

    public function runCorrection()
    {
        try {
            $this->checkEmployeeWorkHours();
            $this->checkEmployeeBreak();
        } catch (\Exception $e) {
            $this->getLogger()->error($e->getLine() . " - " . $e->getMessage());
            $this->getLogger()->error($e->getTraceAsString());
        }
    }

    private function addBreak(Employee $employee)
    {
        // Get employee attendance records
        $records = $this->groupAttendanceRecordsByEmployee()[$employee->getEmployeeId()];
        // Employee did not punch in or punch out
        if (count($records) === 0) {
            return;
        }
        // get employee leaves
        $employeeLeaves = $this->allEmployeeLeaves;
        // check if employee had any leaves
        if (count($employeeLeaves) > 0) {
            // if employee had leaves, note the times so the break is not added on those times
            // loop over the records an fix the punch in and punch out times so that the break can be added
            // create a new attendance record for the break with type AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME
        } else {
            // if employee did not have any leaves, create a new attendance record with punch in and punch out with total time of 30 minuts
            // with type AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME
        }
        // add the break record to the database
    }

    private function addPunchOut(Employee $employee)
    {
        // get the first record of the employee
        // calculate the punch out time (the total work time must be 8 hours)
        // create a new attendance record with punch in and punch out with total time of 8 hours
        // with type AttendanceRecord::ATTENDANCE_TYPE_WORK_TIME
        // add the record to the database
        $records = $this->groupAttendanceRecordsByEmployee()[$employee->getEmployeeId()];
        if (count($records) === 0) {
            return; // Employee did not punch in
        }
    }

    private function checkEmployeeWorkHours()
    {
        // Check if punched in
        // Check if employee is absent or partialy absent
        // Check if employee is not absent or partially absent and forgot to work for 8 hours
        // Check if punched out
    }

    private function checkEmployeeBreak()
    {
        // check if employee took break
        // loop over $this->groupAttendanceRecordsByEmployee()
        // if employee has no break, add break
        $grouppedRecords = $this->groupAttendanceRecordsByEmployee();
        if ($grouppedRecords) {
            foreach ($grouppedRecords as $records) {
                // check if employee has break
                $hasBreak = false;
                array_map(function ($record) use (&$hasBreak) {
                    if ($record->getAttendanceType() === AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME) {
                        $hasBreak = true;
                    }
                }, $records);
                if (!$hasBreak) {
                    $this->addBreak($records[0]);
                }
            }
        }
    }

    private function isEmployeeAbsent(AttendanceRecord $attendanceRecord): bool
    {
        $employee = $attendanceRecord->getEmployee();
        if ($this->allEmployeeLeaves && count($this->allEmployeeLeaves) > 0) {
            foreach ($this->allEmployeeLeaves as $employeeLeave) {
                if ($employeeLeave->getEmployee()->getEmployeeId() === $employee->getEmployeeId()) {
                    return true;
                }
            }
        }
        return false;
    }

    private function groupAttendanceRecordsByEmployee()
    {
        static $grouppedAttendanceRecords = null;
        if ($grouppedAttendanceRecords === null) {
            $grouppedAttendanceRecords = [];
            if ($this->allEmployeeAttendance && count($this->allEmployeeAttendance) > 0) {
                foreach ($this->allEmployeeAttendance as $attendanceRecord) {
                    $employee = $attendanceRecord->getEmployee();
                    if (!isset($grouppedAttendanceRecords[$employee->getEmployeeId()])) {
                        $grouppedAttendanceRecords[$employee->getEmployeeId()] = [];
                    }
                    $grouppedAttendanceRecords[$employee->getEmployeeId()][] = $attendanceRecord;
                }
            }
        }
        return $grouppedAttendanceRecords;
    }
}

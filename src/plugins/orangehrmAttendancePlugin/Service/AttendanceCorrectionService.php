<?php

namespace OrangeHRM\Attendance\Service;

use OrangeHRM\Attendance\Dto\AttendanceRecordSearchFilterParams;
use OrangeHRM\Attendance\Traits\Service\AttendanceServiceTrait;
use OrangeHRM\Core\Traits\LoggerTrait;
use OrangeHRM\Entity\AttendanceRecord;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Entity\Leave;
use OrangeHRM\Leave\Traits\Service\LeaveRequestServiceTrait;
use OrangeHRM\Leave\Dto\EmployeeLeaveSearchFilterParams;

class AttendanceCorrectionService
{
    use LeaveRequestServiceTrait;
    use AttendanceServiceTrait;
    use LoggerTrait;

    const WORK_HOURS = 28800; // 8 hours in seconds

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
        $grouppedRecords = $this->groupAttendanceRecordsByEmployee();
        $grouppedLeaves = $this->groupLeavesByEmployee();
        if ($grouppedRecords) {
            /** @var AttendanceRecord[] $records */
            foreach ($grouppedRecords as $employeeId => $records) {
                // Check if punched in
                if (count($records) === 0) {
                    continue; // Employee did not punch in
                }
                /** @var Leave[] $employeeLeaves */
                $employeeLeaves = $grouppedLeaves[$employeeId] ?? [];
                if (count($employeeLeaves) > 0) {
                    $totalWorkTime = 0;
                    foreach ($records as $record) {
                        $startTime = $record->getPunchInUserTime();
                        $endTime = $record->getPunchOutUserTime();
                        $totalWorkTime += $endTime->getTimestamp() - $startTime->getTimestamp();
                    }
                    $totalLeaveTime = 0;
                    foreach ($employeeLeaves as $leave) {
                        $start = $leave->getStartTime();
                        $end = $leave->getEndTime();
                        $totalLeaveTime += $end->getTimestamp() - $start->getTimestamp();
                    }
                    // check if employee has 8 hours of work, mind the leaves
                    if (($totalWorkTime + $totalLeaveTime) < self::WORK_HOURS) {
                        $this->addPunchOut($records[0]->getEmployee());
                    }
                } else {
                    $totalWorkTime = 0;
                    foreach ($records as $record) {
                        $startTime = $record->getPunchInUserTime();
                        $endTime = $record->getPunchOutUserTime();
                        $totalWorkTime += $endTime->getTimestamp() - $startTime->getTimestamp();
                    }
                    // check if employee has 8 hours of work
                    if ($totalWorkTime < self::WORK_HOURS) {
                        $this->addPunchOut($records[0]->getEmployee());
                    }
                }

                // Check if punched out (last record should have a state of punch out)
                /** @var AttendanceRecord */
                $lastRecord = $records[count($records) - 1];
                if (
                    ($lastRecord->getState() === AttendanceRecord::STATE_PUNCHED_IN) || // Employee did not punch out
                    ($lastRecord->getAttendanceType() === AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME) // Employee forgot in state of break
                ) {
                    $this->addPunchOut($lastRecord->getEmployee());
                }
            }
        }
    }

    private function checkEmployeeBreak()
    {
        // check if employee took break, if employee has no break, add break
        $grouppedRecords = $this->groupAttendanceRecordsByEmployee();
        if ($grouppedRecords) {
            foreach ($grouppedRecords as $records) {
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

    private function groupLeavesByEmployee()
    {
        static $grouppedLeaves = null;
        if ($grouppedLeaves === null) {
            $grouppedLeaves = [];
            if ($this->allEmployeeLeaves && count($this->allEmployeeLeaves) > 0) {
                foreach ($this->allEmployeeLeaves as $leave) {
                    $employee = $leave->getEmployee();
                    if (!isset($grouppedLeaves[$employee->getEmployeeId()])) {
                        $grouppedLeaves[$employee->getEmployeeId()] = [];
                    }
                    $grouppedLeaves[$employee->getEmployeeId()][] = $leave;
                }
            }
        }
        return $grouppedLeaves;
    }
}

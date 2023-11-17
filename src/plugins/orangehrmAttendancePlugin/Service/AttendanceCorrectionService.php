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

        // create break record stub
        $breakRecord = new AttendanceRecord();
        $breakRecord->setEmployee($employee);
        $breakRecord->setAttendanceType(AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME);
        $breakRecord->setState(AttendanceRecord::STATE_PUNCHED_OUT);
        $breakRecord->setPunchInNote(AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME);
        $breakRecord->setPunchOutNote(AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME);

        // check if employee had any leaves
        if (count($employeeLeaves) > 0) {
            // find a time frame of 30 minutes between work time and leaves
            // set that time to the $breakRecord
            // TODO: implement this
        } else {
            // add 30 minutes of break time to the $breakRecord at 11:30 am
            $breakRecord->setPunchInUserTime(\DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 11:30:00'));
            $breakRecord->setPunchOutUserTime(\DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 12:00:00'));
        }
        // clear the work time of the employee for the break time (30 minutes)
        $this->clearEmployeeAttendanceForTimeFrame($employee, $breakRecord->getPunchInUserTime(), $breakRecord->getPunchOutUserTime());
        // add the break record to the database
        /** @var \OrangeHRM\Attendance\Dao\AttendanceDao $attendanceDao */
        $attendanceDao = $this->getAttendanceService()
            ->getAttendanceDao();
        $attendanceDao->savePunchRecord($breakRecord);
    }

    private function addPunchOut(Employee $employee)
    {
        $records = $this->groupAttendanceRecordsByEmployee()[$employee->getEmployeeId()];
        if (count($records) === 0) {
            return; // Employee did not punch in
        }
        $totalWorkTime = 0;
        $totalBreakTime = 0;
        /** @var AttendanceRecord $record */
        foreach ($records as $record) {
            $startTime = $record->getPunchInUserTime();
            $endTime = $record->getPunchOutUserTime();
            $totalWorkTime += $endTime->getTimestamp() - $startTime->getTimestamp();
            if ($record->getAttendanceType() === AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME) {
                $totalBreakTime += $endTime->getTimestamp() - $startTime->getTimestamp();
            }
        }

        $totalLeaveTime = 0;
        $leaves = $this->groupLeavesByEmployee()[$employee->getEmployeeId()] ?? [];
        if ($leaves) {
            foreach ($leaves as $leave) {
                $start = $leave->getStartTime();
                $end = $leave->getEndTime();
                $totalLeaveTime += $end->getTimestamp() - $start->getTimestamp();
            }
        }

        // check if the employee has 8 hours of work time combined with leave time
        if ($totalWorkTime + $totalBreakTime + $totalLeaveTime >= self::WORK_HOURS) {
            return; // Employee has 8 hours of work time
        }

        // calculate the needed remaining work time
        $remainingWorkTime = self::WORK_HOURS - ($totalWorkTime + $totalBreakTime + $totalLeaveTime);
        // get the last end time of employee (from leave or from attendance record)
        /** @var AttendanceRecord $lastRecord */
        $lastRecord = $records[count($records) - 1];
        $lastRecordEndTime = $lastRecord->getPunchOutUserTime() ?? $lastRecord->getPunchInUserTime(); // user may have forgot to punch out
        $lastEndTime = $lastRecordEndTime;
        if ($leaves) {
            /** @var Leave $lastLeave */
            $lastLeave = $leaves[count($leaves) - 1];
            $lastLeaveEndTime = $lastLeave->getEndTime();
            $lastEndTime = $lastRecordEndTime > $lastLeaveEndTime ? $lastRecordEndTime : $lastLeaveEndTime;
        }
        // calculate the punch out time
        $punchOutTime = $lastEndTime->getTimestamp() + $remainingWorkTime;
        // create a new attendance record with punch in and punch out with total time of 8 hours
        $punchOutRecord = new AttendanceRecord();
        $punchOutRecord->setEmployee($employee);
        $punchOutRecord->setAttendanceType(AttendanceRecord::ATTENDANCE_TYPE_WORK_TIME);
        $punchOutRecord->setState(AttendanceRecord::STATE_PUNCHED_OUT);
        $punchOutRecord->setPunchInUserTime($lastEndTime);
        $punchOutRecord->setPunchOutUserTime(\DateTime::createFromFormat('U', $punchOutTime));
        $this->getAttendanceService()->getAttendanceDao()->savePunchRecord($punchOutRecord);
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

    private function clearEmployeeAttendanceForTimeFrame(Employee $employee, \DateTime $from, \DateTime $to)
    {
        // get all attendance records for the employee
        $records = $this->groupAttendanceRecordsByEmployee()[$employee->getEmployeeId()];
        // get all records that are between the time frames
        $records = array_filter($records, function ($record) use ($from, $to) {
            $punchInTime = $record->getPunchInUserTime();
            $punchOutTime = $record->getPunchOutUserTime();
            return ($punchInTime >= $from && $punchInTime <= $to) || ($punchOutTime >= $from && $punchOutTime <= $to);
        });
        if (count($records) === 1) {
            // if one record, modify the record so it ends before the $from and create a new record that starts after the $to

        } else if (count($records) > 1) {
            // if more that one record, modify the first record so it ends before the $from and modify the last record so it starts after the $to

        } else {
            // no records in that time frame... all ok.
        }
    }
}

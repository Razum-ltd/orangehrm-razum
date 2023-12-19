<?php

namespace OrangeHRM\Attendance\Service;

use OrangeHRM\Attendance\Dto\AttendanceRecordSearchFilterParams;
use OrangeHRM\Attendance\Traits\Service\AttendanceServiceTrait;
use OrangeHRM\Core\Service\DateTimeHelperService;
use OrangeHRM\Core\Traits\LoggerTrait;
use OrangeHRM\Entity\AttendanceRecord;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Entity\Leave;
use OrangeHRM\Leave\Traits\Service\LeaveRequestServiceTrait;
use OrangeHRM\Leave\Dto\EmployeeLeaveSearchFilterParams;
use OrangeHRM\Pim\Dto\EmployeeSearchFilterParams;
use OrangeHRM\Pim\Traits\Service\EmployeeServiceTrait;

class AttendanceCorrectionService
{
    use LeaveRequestServiceTrait;
    use AttendanceServiceTrait;
    use EmployeeServiceTrait;
    use LoggerTrait;

    public const TIMEZONE = 'Europe/Ljubljana';
    public const WORK_HOURS = 28800; // 8 hours in seconds
    public const BREAK_TIME = 1800; // 30 minutes in seconds

    /** @var AttendanceRecord[] $allEmployeeAttendance */
    private ?array $allEmployeeAttendance = null;

    public function __construct()
    {
        // set all employee attendance
        $attendanceRecordSearchFilterParams = new AttendanceRecordSearchFilterParams();
        $attendanceRecordSearchFilterParams->setFromDate(
            $this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 00:00:00')
        );
        $attendanceRecordSearchFilterParams->setToDate(
            $this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 23:59:59')
        );
        $attendanceRecordSearchFilterParams->setSortField('punchInUserTime');
        $attendanceRecordSearchFilterParams->setSortOrder('ASC');
        $this->allEmployeeAttendance = $this->getAttendanceService()
            ->getAttendanceDao()
            ->getAttendanceRecords($attendanceRecordSearchFilterParams);
    }

    public function runCorrection()
    {
        $messages = [];
        // if the current time is over 17:00 we can run the correction
        /* if (!(date('H:i:s') > '17:00:00')) {
            throw new \Exception('Correction can be run after 17:00.');
        } */
        $messages[] = $this->checkEmployeesBreak();
        $messages[] = $this->checkEmployeesAttendance();

        return $messages;
    }

    private function checkEmployeesAttendance()
    {
        $employeeSearchParam = new EmployeeSearchFilterParams();
        $employees = $this->getEmployeeService()
            ->getEmployeeDao()
            ->getEmployeeList($employeeSearchParam);

        $messages = [];
        if ($employees) {
            /** @var Employee $employee */
            foreach ($employees as $employee) {
                $message = $this->checkEmployeeHours($employee);
                if ($message) {
                    $messages[] = $message;
                }
            }
        }
        return $messages;
    }

    private function checkEmployeeHours(Employee $employee)
    {
        $grouppedRecords = $this->groupAttendanceRecordsByEmployee();
        if (!$grouppedRecords || count($grouppedRecords) === 0 || !array_key_exists($employee->getEmpNumber(), $grouppedRecords)) {
            return; // No attendance records for this employee
        }
        $records = $grouppedRecords[$employee->getEmpNumber()];
        if (count($records) === 0) {
            return; // Employee did not punch in, we do not need to check
        }
        $employeeLeaves = $this->getLeaveForEmployeeEmp(
            $employee->getEmpNumber(),
            $this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 00:00:00'),
            $this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 23:59:59')
        );

        // in some cases the employee has 8 hours of leave time, so we can skip the check
        if (count($employeeLeaves) > 0) {
            $totalLeaveTime = 0;
            /** @var Leave $leave */
            foreach ($employeeLeaves as $leave) {
                if ($leave->getLeaveType() === Leave::DURATION_TYPE_FULL_DAY) {
                    return; // employee has whole day leave, no need to check
                }
                $start = $leave->getStartTime();
                $end = $leave->getEndTime();
                $totalLeaveTime += $end->getTimestamp() - $start->getTimestamp();
            }
            if ($totalLeaveTime >= self::WORK_HOURS) {
                return; // Employee has 8 hours of leave time
            }
        }

        /** @var AttendanceRecord $lastRecord */
        $lastRecord = $records[count($records) - 1];

        $totalWorkTime = 0;
        $totalBreakTime = 0;
        $totalLeaveTime = 0;

        if ($records) {
            /** @var AttendanceRecord $record */
            foreach ($records as $record) {
                $startTime = $record->getPunchInUserTime() ?? new \DateTime();
                $endTime = $record->getPunchOutUserTime() ?? new \DateTime();
                $time = ($endTime->getTimestamp() - $startTime->getTimestamp()) / 3600;
                if ($record->getAttendanceType() === AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME) {
                    $totalBreakTime += $time;
                } else {
                    $totalWorkTime += $time;
                }
            }
        }

        if ($employeeLeaves) {
            /** @var Leave $leave */
            foreach ($employeeLeaves as $leave) {
                switch ($leave->getLeaveType()) {
                    case Leave::DURATION_TYPE_FULL_DAY:
                        $length = 8;
                        break;
                    case Leave::DURATION_TYPE_HALF_DAY_PM:
                    case Leave::DURATION_TYPE_HALF_DAY_AM:
                        $length = 4;
                        break;
                    default:
                        $length = $leave->getLengthHours() ?? 0;
                        break;
                }
                $totalLeaveTime += $length;
            }
        }

        if (($totalBreakTime + $totalWorkTime + $totalLeaveTime) >= self::WORK_HOURS) {
            // check if the last record has a punch out time
            if (!$lastRecord->getPunchOutUserTime()) {
                // set the checkout time 8 hours from the first record punch in time
                $startTime = $records[0]->getPunchInUserTime();
                $lastRecord->setPunchOutUserTime($this->getDateWithTimeZone('U', $startTime->getTimestamp() + self::WORK_HOURS));
                $lastRecord->setPunchOutUtcTime($this->getDateWithUTCTimeZone($lastRecord->getPunchOutUserTime()));
                $lastRecord->setPunchOutTimezoneName(self::TIMEZONE);
                $this->getAttendanceService()->getAttendanceDao()->savePunchRecord($lastRecord);
                return "Employee {$employee->getFirstName()} {$employee->getLastName()} has been corrected.";
            }
            return;
        }

        // current user does not have 8 hours of work time
        if ($lastRecord->getAttendanceType() === AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME) {
            // user forgot to checkout from break
            // set break punch out time to break start time + 30 minutes
            $startTime = $lastRecord->getPunchInUserTime();
            $lastRecord->setPunchOutUserTime($this->getDateWithTimeZone('U', $startTime->getTimestamp() + self::BREAK_TIME));
            $this->getAttendanceService()->getAttendanceDao()->savePunchRecord($lastRecord);
            // create a new attendance record with the start time of the last record and the calculated punch out time
            $punchOutRecord = new AttendanceRecord();
            $punchOutRecord->setEmployee($employee);
            $punchOutRecord->setAttendanceType(AttendanceRecord::ATTENDANCE_TYPE_WORK_TIME);
            $punchOutRecord->setState(AttendanceRecord::STATE_PUNCHED_OUT);
            $punchOutRecord->setPunchInUserTime($lastRecord->getPunchOutUserTime());
            $punchOutRecord->setPunchInUtcTime($this->getDateWithUTCTimeZone($punchOutRecord->getPunchInUserTime()));
            $punchOutRecord->setPunchInTimezoneName(self::TIMEZONE);
            $punchOutRecord->setPunchOutUserTime($this->getDateWithTimeZone('U', $lastRecord->getPunchOutUserTime()->getTimestamp() + (self::BREAK_TIME + $totalWorkTime + $totalLeaveTime)));
            $punchOutRecord->setPunchOutUtcTime($this->getDateWithUTCTimeZone($punchOutRecord->getPunchOutUserTime()));
            $punchOutRecord->setPunchOutTimezoneName(self::TIMEZONE);
            $this->getAttendanceService()->getAttendanceDao()->savePunchRecord($punchOutRecord);
        } else {
            // user forgot to checkout from work
            // set the checkout time 8 hours from the first record punch in time
            $startTime = $records[0]->getPunchInUserTime();
            $lastRecord->setState(AttendanceRecord::STATE_PUNCHED_OUT);
            $lastRecord->setPunchOutUserTime($this->getDateWithTimeZone('U', ($startTime->getTimestamp() + (self::WORK_HOURS + $totalBreakTime + $totalLeaveTime))));
            $lastRecord->setPunchOutUtcTime($this->getDateWithUTCTimeZone($lastRecord->getPunchOutUserTime()));
            $lastRecord->setPunchOutTimezoneName(self::TIMEZONE);
            $this->getAttendanceService()->getAttendanceDao()->savePunchRecord($lastRecord);
        }
        return "Employee {$employee->getFirstName()} {$employee->getLastName()} has been corrected.";
    }

    private function checkEmployeesBreak()
    {
        $messages = [];
        /** @var array<AttendanceRecord[]> $grouppedRecords */
        $grouppedRecords = $this->groupAttendanceRecordsByEmployee();
        if ($grouppedRecords) {
            foreach ($grouppedRecords as $records) {
                $hasBreak = false;
                foreach ($records as $record) {
                    if ($record->getAttendanceType() === AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME) {
                        $hasBreak = true;
                    }
                }
                if (!$hasBreak) {
                    $canAddBreak = true;
                    /** @var Employee $employee */
                    $employee = $records[0]->getEmployee();
                    // Get employee attendance records
                    $grouppedRecords = $this->groupAttendanceRecordsByEmployee();
                    if (!$grouppedRecords || count($grouppedRecords) === 0 || !array_key_exists($employee->getEmpNumber(), $grouppedRecords)) {
                        break; // No attendance records for this employee
                    }
                    $records = $grouppedRecords[$employee->getEmpNumber()];
                    // Employee did not punch in or punch out
                    if (count($records) === 0) {
                        break;
                    }
                    // get employee leaves
                    /** @var Leave[] $employeeLeaves */
                    $employeeLeaves = $this->getLeaveForEmployeeEmp(
                        $employee->getEmpNumber(),
                        $records[0]->getPunchInUserTime(),
                        $records[count($records) - 1]->getPunchOutUserTime()
                    );
                    if (count($employeeLeaves) > 0) {
                        foreach ($employeeLeaves as $leave) {
                            if ($leave->getLeaveType() === Leave::DURATION_TYPE_FULL_DAY) {
                                // employee has whole day leave, no need to add break
                                $canAddBreak = false;
                                break;
                            }
                        }
                    }
                    // check if we can add a break from 11:30 to 12:00
                    // no need to run if we already know we can't add break
                    if (count($employeeLeaves) > 0 && !$canAddBreak) {
                        foreach ($employeeLeaves as $leave) {
                            $start = $leave->getStartTime();
                            $end = $leave->getEndTime();
                            if ($start->format('H:i:s') <= '11:30:00' && $end->format('H:i:s') >= '12:00:00') {
                                $canAddBreak = false;
                            }
                        }
                    }
                    if (!$canAddBreak) {
                        break; // can't add break
                    }
                    // create break record stub
                    $breakRecord = new AttendanceRecord();
                    $breakRecord->setEmployee($employee);
                    $breakRecord->setAttendanceType(AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME);
                    $breakRecord->setState(AttendanceRecord::STATE_PUNCHED_OUT);
                    $breakRecord->setPunchInNote(AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME);
                    $breakRecord->setPunchOutNote(AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME);
                    // set 30 minutes of break time to the $breakRecord at 11:30 am
                    $breakRecord->setPunchInUserTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 11:30:00'));
                    $breakRecord->setPunchInTimezoneName(self::TIMEZONE);
                    $breakRecord->setPunchOutUserTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 12:00:00'));
                    $breakRecord->setPunchOutTimezoneName(self::TIMEZONE);
                    // add the break record to the database
                    $this->getAttendanceService()
                        ->getAttendanceDao()
                        ->savePunchRecord($breakRecord);
                    $messages[] = "Break added to {$employee->getFirstName()} {$employee->getLastName()}.";
                }
            }
        }
        return $messages;
    }

    private function groupAttendanceRecordsByEmployee()
    {
        $grouppedAttendanceRecords = [];
        if ($this->allEmployeeAttendance && count($this->allEmployeeAttendance) > 0) {
            /** @var AttendanceRecord $attendanceRecord */
            foreach ($this->allEmployeeAttendance as $attendanceRecord) {
                $employee = $attendanceRecord->getEmployee();
                if (!isset($grouppedAttendanceRecords[$employee->getEmpNumber()])) {
                    $grouppedAttendanceRecords[$employee->getEmpNumber()] = [];
                }
                $grouppedAttendanceRecords[$employee->getEmpNumber()][] = $attendanceRecord;
            }
        }
        return $grouppedAttendanceRecords;
    }

    /**
     * Retrieve the employee leave for time period
     * @param int $employeeEmp
     * @param mixed $from
     * @param mixed $to
     * @return array
     */
    private function getLeaveForEmployeeEmp(int $employeeEmp, $from, $to)
    {
        $filter = new EmployeeLeaveSearchFilterParams();
        $filter->setEmpNumber($employeeEmp);
        $filter->setFromDate($from);
        $filter->setToDate($to);
        $filter->setStatuses([
            Leave::LEAVE_STATUS_LEAVE_TAKEN,
            Leave::LEAVE_STATUS_LEAVE_APPROVED
        ]);

        return $this->getLeaveRequestService()
            ->getLeaveRequestDao()
            ->getEmployeeLeaves($filter);
    }

    /**
     * New DateTime instance with predefined timezone
     * @param mixed $format
     * @param mixed $time
     * @return \DateTime
     */
    private function getDateWithTimeZone($format, $time)
    {
        $dateTime = \DateTime::createFromFormat($format, $time);
        if ($dateTime === false) {
            throw new \Exception('Invalid date format.');
        }
        $dateTime->setTimezone(new \DateTimeZone(self::TIMEZONE));
        return $dateTime;
    }

    /**
     * Get a clone of the datetime with UTC timezone
     * @param \DateTime $datetime
     * @return \DateTime
     */
    private function getDateWithUTCTimeZone(\DateTime $datetime, $originalTimeZone = self::TIMEZONE)
    {
        $cloned = clone $datetime;
        $cloned->setTimezone(new \DateTimeZone($originalTimeZone));
        return $cloned->setTimezone(
            new \DateTimeZone(DateTimeHelperService::TIMEZONE_UTC)
        );
    }
}

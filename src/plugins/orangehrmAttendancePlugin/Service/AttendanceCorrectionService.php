<?php

namespace OrangeHRM\Attendance\Service;

use DateTime;
use OrangeHRM\Attendance\Dto\AttendanceRecordSearchFilterParams;
use OrangeHRM\Attendance\Traits\Service\AttendanceServiceTrait;
use OrangeHRM\Core\Service\DateTimeHelperService;
use OrangeHRM\Core\Traits\LoggerTrait;
use OrangeHRM\Entity\AttendanceRecord;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Entity\Leave;
use OrangeHRM\Leave\Traits\Service\HolidayServiceTrait;
use OrangeHRM\Leave\Traits\Service\LeaveRequestServiceTrait;
use OrangeHRM\Leave\Dto\EmployeeLeaveSearchFilterParams;
use OrangeHRM\Pim\Dto\EmployeeSearchFilterParams;
use OrangeHRM\Pim\Traits\Service\EmployeeServiceTrait;

class AttendanceCorrectionService
{
    use LeaveRequestServiceTrait;
    use AttendanceServiceTrait;
    use EmployeeServiceTrait;
    use HolidayServiceTrait;
    use LoggerTrait;


    public const TIMEZONE = 'Europe/Ljubljana';
    public const WORK_HOURS = 28800; // 8 hours in seconds
    public const BREAK_TIME = 1800; // 30 minutes in seconds

    public const AUTOMATIC_PUNCH_IN_NOTE_WORK = "WORK_IN";
    public const AUTOMATIC_PUNCH_IN_NOTE_WORK_BEFORE_BREAK = "WORK_IN_BEFORE_BREAK";

    public const AUTOMATIC_PUNCH_IN_NOTE_WORK_AFTER_BREAK = "WORK_IN_AFTER_BREAK";

    public const AUTOMATIC_PUNCH_OUT_NOTE_WORK_BEFORE_BREAK = "WORK_OUT_BEFORE_BREAK";

    public const AUTOMATIC_PUNCH_OUT_NOTE_WORK_AFTER_BREAK = "WORK_OUT_AFTER_BREAK";

    public const AUTOMATIC_PUNCH_IN_NOTE_BREAK = "BREAK_IN";

    public const AUTOMATIC_PUNCH_OUT_NOTE_BREAK = "BREAK_OUT";

    /** @var AttendanceRecord[] $allEmployeeAttendance */
    private ?array $allEmployeeAttendance = null;

    public function __construct()
    {
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
        $today = new DateTime();
        $isHoliday = $this->getHolidayService()->isHoliday($today);
        $isWeekend = $this->isWeekend($today);

        if ($isHoliday || $isWeekend) {
            $this->getLogger()->alert('Tried to run correction on a holiday or a weekend.');
            throw new \Exception('Correction cannot be run for holiday or weekend.');
        }

        $messages = [];

        // Old functionality. Deprecated.
        // if the current time is over 17:00 we can run the correction
        /*if (!(date('H:i:s') > '17:00:00')) {
            $this->getLogger()->alert('Tried to run the correction before 17:00.');
            throw new \Exception('Correction can be run after 17:00.');
        }*/
        //$messages[] = $this->checkEmployeesBreak();
        //$messages[] = $this->checkEmployeesAttendance();

        $messages[] = $this->getAutomaticAttendanceCandidatesAndExecute();

        return $messages;
    }

    private function getAutomaticAttendanceCandidatesAndExecute()
    {
        $result = new \stdClass();
        $result->success = array(["Employees that has been automatically punched in and out"]);
        $result->error = array(["Attendance record already exists. Skipping employees:"]);

        $employeeSearchParam = new EmployeeSearchFilterParams();

        // Filter employess that have automatic punch out enabled
        $employeeSearchParam->setAutomaticPunchOut(1);

        $employees = $this->getEmployeeService()
            ->getEmployeeDao()
            ->getEmployeeList($employeeSearchParam);

        foreach ($employees as $employee) {

            if ($employee instanceof Employee) {
                $empNumber = $employee->getEmpNumber();

                if ($employee && $empNumber) {
                    $leave = $this->getLeaveForEmployeeEmp(
                        $employee->getEmpNumber(),
                        $this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 00:00:00'),
                        $this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 23:59:59')
                    );
                }


                $todayStart = new DateTime('today midnight');
                $todayEnd = new DateTime('today 23:59:59');
                $attendanceRecordSearchParams = new AttendanceRecordSearchFilterParams();
                $attendanceRecordSearchParams->setEmployeeNumbers([$empNumber]);
                $attendanceRecordSearchParams->setFromDate($todayStart);
                $attendanceRecordSearchParams->setToDate($todayEnd);
                $recordExists = $this->getAttendanceService()->getAttendanceDao()->getAttendanceRecordList($attendanceRecordSearchParams);

                if ($recordExists && count($recordExists) > 0) {
                    $result->error[1][] = "{$employee->getFirstName()} {$employee->getLastName()} ({$employee->getEmployeeId()})";
                    continue;
                }

                if ($employee && !$leave) {
                    $result->success[1][] = $this->handleAutomaticAttendance($employee);
                }


            }
        }
        return $result;
    }


    private function handleAutomaticAttendance(Employee $employee)
    {

        // Work Record


        $workRecordBeforeBreak = new AttendanceRecord();
        $workRecordBeforeBreak->setEmployee($employee);
        $workRecordBeforeBreak->setPunchInTimeOffset('+2:00'); // Support EU/Ljubljana TZ only for now. Will implement timezones if needed
        $workRecordBeforeBreak->setPunchOutTimeOffset('+2:00'); // Support EU/Ljubljana TZ only for now. Will implement timezones if needed
        $workRecordBeforeBreak->setPunchInUtcTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 8:00:00'));
        $workRecordBeforeBreak->setPunchOutUtcTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 11:30:00'));
        $workRecordBeforeBreak->setPunchInUserTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 6:00:00')); // -2 because UTC
        $workRecordBeforeBreak->setPunchOutUserTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 9:30:00')); // -2 because UTC
        $workRecordBeforeBreak->setAttendanceType(AttendanceRecord::ATTENDANCE_TYPE_WORK_TIME);
        $workRecordBeforeBreak->setPunchInTimezoneName(self::TIMEZONE);
        $workRecordBeforeBreak->setPunchOutTimezoneName(self::TIMEZONE);
        $workRecordBeforeBreak->setState(AttendanceRecord::STATE_PUNCHED_OUT);
        $workRecordBeforeBreak->setPunchInNote(self::AUTOMATIC_PUNCH_IN_NOTE_WORK_BEFORE_BREAK);
        $workRecordBeforeBreak->setPunchOutNote(self::AUTOMATIC_PUNCH_OUT_NOTE_WORK_BEFORE_BREAK);

        $workRecordAfterBreak = new AttendanceRecord();
        $workRecordAfterBreak->setEmployee($employee);
        $workRecordAfterBreak->setPunchInTimeOffset('+2:00'); // Support EU/Ljubljana TZ only for now. Will implement timezones if needed
        $workRecordAfterBreak->setPunchOutTimeOffset('+2:00'); // Support EU/Ljubljana TZ only for now. Will implement timezones if needed
        $workRecordAfterBreak->setPunchInUtcTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 12:00:00'));
        $workRecordAfterBreak->setPunchOutUtcTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 16:00:00'));
        $workRecordAfterBreak->setPunchInUserTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 10:00:00')); // -2 because UTC
        $workRecordAfterBreak->setPunchOutUserTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 14:00:00')); // -2 because UTC
        $workRecordAfterBreak->setAttendanceType(AttendanceRecord::ATTENDANCE_TYPE_WORK_TIME);
        $workRecordAfterBreak->setPunchInTimezoneName(self::TIMEZONE);
        $workRecordAfterBreak->setPunchOutTimezoneName(self::TIMEZONE);
        $workRecordAfterBreak->setState(AttendanceRecord::STATE_PUNCHED_OUT);
        $workRecordAfterBreak->setPunchInNote(self::AUTOMATIC_PUNCH_IN_NOTE_WORK_AFTER_BREAK);
        $workRecordAfterBreak->setPunchOutNote(self::AUTOMATIC_PUNCH_OUT_NOTE_WORK_AFTER_BREAK);

        // Break record
        $breakRecord = new AttendanceRecord();
        $breakRecord->setEmployee($employee);
        $breakRecord->setPunchInTimeOffset('+2:00'); // Support EU/Ljubljana TZ only for now. Will implement timezones if needed
        $breakRecord->setPunchOutTimeOffset('+2:00'); // Support EU/Ljubljana TZ only for now. Will implement timezones if needed
        $breakRecord->setPunchInUtcTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 11:30:00'));
        $breakRecord->setPunchOutUtcTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 12:00:00'));
        $breakRecord->setPunchInUserTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 9:30:00')); // -2 because UTC
        $breakRecord->setPunchOutUserTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 10:00:00')); // -2 because UTC
        $breakRecord->setAttendanceType(AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME);
        $breakRecord->setPunchInTimezoneName(self::TIMEZONE);
        $breakRecord->setPunchOutTimezoneName(self::TIMEZONE);
        $breakRecord->setState(AttendanceRecord::STATE_PUNCHED_OUT);
        $breakRecord->setPunchInNote(self::AUTOMATIC_PUNCH_IN_NOTE_BREAK);
        $breakRecord->setPunchOutNote(self::AUTOMATIC_PUNCH_OUT_NOTE_BREAK);

        $this->getAttendanceService()->savePunchRecord($workRecordBeforeBreak);
        $this->getAttendanceService()->savePunchRecord($breakRecord);
        $this->getAttendanceService()->savePunchRecord($workRecordAfterBreak);



        return "{$employee->getFirstName()} {$employee->getLastName()} ({$employee->getEmployeeId()})";
    }

    private function isWeekend(DateTime $date)
    {
        return $date->format('w') == 0 || $date->format('w') == 6;
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
            $this->getLogger()->info("Employee {$employee->getFirstName()} {$employee->getLastName()} did not punch in (no records found).");
            return; // No attendance records for this employee
        }
        $records = $grouppedRecords[$employee->getEmpNumber()];
        if (count($records) === 0) {
            $this->getLogger()->info("Employee {$employee->getFirstName()} {$employee->getLastName()} did not punch in (no records found).");
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
                $this->getLogger()->info("Employee {$employee->getFirstName()} {$employee->getLastName()} has more than 8 hours of work time.\n Setting checkout time to 8 hours from the first record punch in time.");
                $startTime = $records[0]->getPunchInUserTime();
                $lastRecord->setPunchOutUserTime($this->getDateWithTimeZone('U', $startTime->getTimestamp() + self::WORK_HOURS));
                $lastRecord->setPunchOutUtcTime($this->getDateWithUTCTimeZone($lastRecord->getPunchOutUserTime()));
                $lastRecord->setPunchOutTimezoneName(self::TIMEZONE);
                $this->getAttendanceService()->getAttendanceDao()->savePunchRecord($lastRecord);
                return "Employee {$employee->getFirstName()} {$employee->getLastName()} has been corrected.";
            }
            return;
        }

        $this->getLogger()->info("Employee {$employee->getFirstName()} {$employee->getLastName()} has less than 8 hours of work time.");
        if ($lastRecord->getAttendanceType() === AttendanceRecord::ATTENDANCE_TYPE_BREAK_TIME) {
            $this->getLogger()->info("Employee {$employee->getFirstName()} {$employee->getLastName()} forgot to checkout from break.. Setting break checkout time to break start time + 30 minutes.");
            $startTime = $lastRecord->getPunchInUserTime();
            $lastRecord->setPunchOutUserTime($this->getDateWithTimeZone('U', $startTime->getTimestamp() + self::BREAK_TIME));
            $this->getAttendanceService()
                ->getAttendanceDao()
                ->savePunchRecord($lastRecord);

            $this->getLogger()->info("Create a new attendance record with the start time of the last record and the calculated punch out time for {$employee->getFirstName()} {$employee->getLastName()}.}");
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
            $this->getAttendanceService()
                ->getAttendanceDao()
                ->savePunchRecord($punchOutRecord);
        } else {
            $this->getLogger()->info("Employee {$employee->getFirstName()} {$employee->getLastName()} forgot to checkout.\n Setting checkout time to 8 hours from the first record punch in time.");
            $startTime = $records[0]->getPunchInUserTime();
            $lastRecord->setState(AttendanceRecord::STATE_PUNCHED_OUT);
            $lastRecord->setPunchOutUserTime($this->getDateWithTimeZone('U', ($startTime->getTimestamp() + (self::WORK_HOURS + $totalBreakTime + $totalLeaveTime))));
            $lastRecord->setPunchOutUtcTime($this->getDateWithUTCTimeZone($lastRecord->getPunchOutUserTime()));
            $lastRecord->setPunchOutTimezoneName(self::TIMEZONE);
            $this->getAttendanceService()
                ->getAttendanceDao()
                ->savePunchRecord($lastRecord);
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
                // Employee did not punch in or punch out
                if (count($records) === 0) {
                    break;
                }
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
                                $this->getLogger()->info("Break not added to {$employee->getFirstName()} {$employee->getLastName()} because of whole day leave.");
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
                                $this->getLogger()->info("Break not added to {$employee->getFirstName()} {$employee->getLastName()} because of leave.");
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
                    $breakRecord->setPunchInUserTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 9:30:00'));
                    $breakRecord->setPunchInTimezoneName(self::TIMEZONE);
                    $breakRecord->setPunchOutUserTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 10:00:00'));
                    $breakRecord->setPunchOutTimezoneName(self::TIMEZONE);
                    // add the break record to the database
                    $this->getAttendanceService()
                        ->getAttendanceDao()
                        ->savePunchRecord($breakRecord);
                    $this->getLogger()->info("Break added to {$employee->getFirstName()} {$employee->getLastName()}.\n Break start time: {$breakRecord->getPunchInUserTime()->format('Y-m-d H:i:s')}\n Break end time: {$breakRecord->getPunchOutUserTime()->format('Y-m-d H:i:s')}");
                    $messages[] = "Break added to {$employee->getFirstName()} {$employee->getLastName()}.";

                    // Check if we need to fix any overlapping records
                    $overlappingRecord = null;
                    foreach ($records as $record) {
                        if ($record->getPunchInUserTime()->format('H:i:s') <= '11:30:00' && $record->getPunchOutUserTime() && $record->getPunchOutUserTime()->format('H:i:s') >= '12:00:00') {
                            $overlappingRecord = $record;
                            break;
                        }
                    }
                    /** @var AttendanceRecord $overlappingRecord */
                    if ($overlappingRecord) {
                        $newRecord = clone $overlappingRecord;
                        $this->getLogger()->info("Fonud one record overlapping with break attendance record for {$employee->getFirstName()} {$employee->getLastName()}");
                        // set the punch out time of the overlapping record to 11:30 am
                        $overlappingRecord->setPunchOutUserTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 11:30:00'));
                        $overlappingRecord->setState(AttendanceRecord::STATE_PUNCHED_OUT);
                        $this->getLogger()->info("Setting punch out time of the overlapping record to 11:30 am for {$employee->getFirstName()} {$employee->getLastName()}");
                        $this->getAttendanceService()
                            ->getAttendanceDao()
                            ->savePunchRecord($overlappingRecord);
                        // create a new record with the punch in time of the overlapping record and the punch out time of the overlapping record + the break time
                        $newRecord->setPunchInUserTime($this->getDateWithTimeZone('Y-m-d H:i:s', date('Y-m-d') . ' 12:00:00'));
                        $newRecord->setPunchInUtcTime($this->getDateWithUTCTimeZone($newRecord->getPunchInUserTime()));
                        $newRecord->setPunchInTimezoneName(self::TIMEZONE);
                        if ($newRecord->getState() === AttendanceRecord::STATE_PUNCHED_OUT) {
                            // fix the punch out time of the new record
                            // move the punch out time for 30 minutes
                            $newRecord->setPunchOutUserTime($this->getDateWithTimeZone('U', $newRecord->getPunchOutUserTime()->getTimestamp() + self::BREAK_TIME));
                            $newRecord->setPunchOutUtcTime($this->getDateWithUTCTimeZone($newRecord->getPunchOutUserTime()));
                            $newRecord->setPunchOutTimezoneName(self::TIMEZONE);
                        }
                        $this->getAttendanceService()
                            ->getAttendanceDao()
                            ->savePunchRecord($newRecord);
                        $this->getLogger()->info("New record created for {$employee->getFirstName()} {$employee->getLastName()}.\n Punch in time: {$newRecord->getPunchInUserTime()->format('Y-m-d H:i:s')}\n Punch out time: {$newRecord->getPunchOutUserTime()->format('Y-m-d H:i:s')}");
                        $messages[] = "New record created for {$employee->getFirstName()} {$employee->getLastName()} to avoid the break.";
                    }
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

                // Skip employees that have automatic punch out disabled.
                if ($employee->getAutomaticPunchOut() !== 1) {
                    continue;
                }
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
        if ($cloned === false) {
            throw new \Exception('Invalid date format.');
        }
        if ($cloned->getTimezone()->getName() === DateTimeHelperService::TIMEZONE_UTC) {
            return $cloned;
        }
        if (!$cloned->getTimezone()) {
            $cloned->setTimezone(new \DateTimeZone($originalTimeZone));
        }
        $cloned->setTimezone(new \DateTimeZone(DateTimeHelperService::TIMEZONE_UTC));
        return $cloned;
    }
}
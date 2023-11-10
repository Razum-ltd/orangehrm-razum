<?php
namespace OrangeHRM\Time\Service;

class AttendanceCorrectionService
{
    public function __construct()
    {

    }
    public function run()
    {
        // run the service to add or correct employee attendance
    }
    private function getEmployeeLeavesForToday()
    {
        // get all employee leaves for today
    }
    private function getEmployeeAttendanceRecordsForToday()
    {
        // get all employee attendance records for today
    }
    private function correctEmployeeAttendanceIfEmployeeHasLeaveForToday(
        $employeeLeavesForToday,
        $employeeAttendanceRecordsForToday
    )
    {
        // correct employee attendance if employee has leave for today
        foreach ($employeeLeavesForToday as $employeeLeaveForToday) {
            foreach ($employeeAttendanceRecordsForToday as $employeeAttendanceRecordForToday) {
                if ($employeeLeaveForToday->getLeaveDate() == $employeeAttendanceRecordForToday->getAttendanceDate()) {
                    // correct employee attendance
                }
            }
        }
    }
    private function correctEmployeeAttendanceIfEmployeeDidNotCheckIn()
    {
        // correct employee attendance if employee did not check in
    }
    private function correctEmployeeAttendanceIfEmployeeDidNotCheckOut()
    {
        // correct employee attendance if employee did not check out
    }
    private function correctEmployeeAttendanceIfEmployeeDidNotUseBreak()
    {
        // correct employee attendance if employee did not use break
    }
}
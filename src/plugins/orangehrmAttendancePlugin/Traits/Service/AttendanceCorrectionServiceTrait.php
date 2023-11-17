<?php
namespace OrangeHRM\Attendance\Traits\Service;

use OrangeHRM\Attendance\Service\AttendanceCorrectionService;
use OrangeHRM\Core\Traits\ServiceContainerTrait;
use OrangeHRM\Framework\Services;

trait AttendanceCorrectionServiceTrait
{
    use ServiceContainerTrait;

    /**
     * @return AttendanceCorrectionService
     */
    protected function getAttendanceCorrectionService(): AttendanceCorrectionService
    {
        return $this->getContainer()->get(Services::ATTENDANCE_CORRECTION_SERVICE);
    }
}

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

namespace OrangeHRM\Leave\Dto;

use InvalidArgumentException;
use OrangeHRM\Entity\Leave;
use OrangeHRM\Leave\Traits\Service\LeaveRequestServiceTrait;
use OrangeHRM\ORM\ListSorter;
use OrangeHRM\Pim\Dto\Traits\SubunitIdChainTrait;

class LeaveRequestSearchFilterParams extends DateRangeSearchFilterParams
{
    use SubunitIdChainTrait;
    use LeaveRequestServiceTrait;

    public const ALLOWED_SORT_FIELDS = ['leave.date'];

    public const INCLUDE_EMPLOYEES_ONLY_CURRENT = 'onlyCurrent';
    public const INCLUDE_EMPLOYEES_ONLY_PAST = 'onlyPast';
    public const INCLUDE_EMPLOYEES_CURRENT_AND_PAST = 'currentAndPast';

    public const INCLUDE_EMPLOYEES = [
        self::INCLUDE_EMPLOYEES_ONLY_CURRENT,
        self::INCLUDE_EMPLOYEES_ONLY_PAST,
        self::INCLUDE_EMPLOYEES_CURRENT_AND_PAST,
    ];

    public const LEAVE_STATUSES = [
        Leave::LEAVE_STATUS_LEAVE_REJECTED,
        Leave::LEAVE_STATUS_LEAVE_CANCELLED,
        Leave::LEAVE_STATUS_LEAVE_PENDING_APPROVAL,
        Leave::LEAVE_STATUS_LEAVE_APPROVED,
        Leave::LEAVE_STATUS_LEAVE_TAKEN,
    ];

    /**
     * @var array|null
     */
    private ?array $statuses = null;

    /**
     * @var int|null
     */
    private ?int $empNumber = null;

    /**
     * @var int[]|null
     */
    protected ?array $empNumbers = null;

    /**
     * @var int|null
     */
    private ?int $subunitId = null;

    /**
     * @var int|null
     */
    private ?int $leaveTypeId = null;

    /**
     * @var string|null
     */
    private ?string $includeEmployees = self::INCLUDE_EMPLOYEES_ONLY_CURRENT;

    private ?int $disableMonthOverlap = null;

    public function __construct()
    {
        $this->setSortField('leave.date');
        $this->setSortOrder(ListSorter::DESCENDING);
    }

    /**
     * @return string[]|null
     */
    public function getStatuses(): ?array
    {
        return $this->statuses;
    }

    /**
     * @param array|null $statuses
     */
    public function setStatuses(?array $statuses): void
    {
        if (!empty(array_diff($statuses, self::LEAVE_STATUSES))) {
            throw new InvalidArgumentException();
        }
        $statusMap = $this->getLeaveRequestService()->getAllLeaveStatusesAssoc();
        $this->statuses = [];
        foreach ($statuses as $status) {
            $this->statuses[] = $statusMap[$status];
        }
    }

    /**
     * @return int|null
     */
    public function getEmpNumber(): ?int
    {
        return $this->empNumber;
    }

    /**
     * @param int|null $empNumber
     */
    public function setEmpNumber(?int $empNumber): void
    {
        $this->empNumber = $empNumber;
    }

    /**
     * @return int[]|null
     */
    public function getEmpNumbers(): ?array
    {
        return $this->empNumbers;
    }

    /**
     * @param int[]|null $empNumbers
     */
    public function setEmpNumbers(?array $empNumbers): void
    {
        $this->empNumbers = $empNumbers;
    }

    /**
     * @return int|null
     */
    public function getSubunitId(): ?int
    {
        return $this->subunitId;
    }

    /**
     * @param int|null $subunitId
     */
    public function setSubunitId(?int $subunitId): void
    {
        $this->subunitId = $subunitId;
    }

    /**
     * @return int|null
     */
    public function getLeaveTypeId(): ?int
    {
        return $this->leaveTypeId;
    }

    /**
     * @param int|null $leaveTypeId
     */
    public function setLeaveTypeId(?int $leaveTypeId): void
    {
        $this->leaveTypeId = $leaveTypeId;
    }

    /**
     * @return string|null
     */
    public function getIncludeEmployees(): ?string
    {
        return $this->includeEmployees;
    }

    /**
     * @param string|null $includeEmployees
     */
    public function setIncludeEmployees(?string $includeEmployees): void
    {
        $this->includeEmployees = $includeEmployees;
    }

    public function setDisableMonthOverlap(?int $disableMonthOverlap): void
    {
        $this->disableMonthOverlap = $disableMonthOverlap;
    }

    public function getDisableMonthOverlap(): ?int {
        return $this->disableMonthOverlap;
    }
}

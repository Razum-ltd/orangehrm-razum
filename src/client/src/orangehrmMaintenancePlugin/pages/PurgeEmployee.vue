<!--
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
 -->

<template>
  <div class="orangehrm-background-container">
    <attendance-correction />
    <br />
    <calendar-sync />
    <br />
    <purge-employee-records
      include-employees-param="onlyPast"
      :title-label="$t('maintenance.purge_employee_records')"
      :autocomplete-label="$t('maintenance.past_employee')"
      @search="onClickSearch"
    />
    <br />
    <selected-employee
      v-if="showPurgeableEmployee"
      :loading="isLoading"
      :selected-employee="selectedEmployee"
      :button-label="$t('maintenance.purge')"
      @submit="onClickPurge"
    />

    <br v-if="showPurgeableEmployee" />
    <maintenance-note :instance-identifier="instanceIdentifier" />

    <purge-confirmation
      ref="purgeDialog"
      :title="$t('maintenance.purge_employee')"
      :subtitle="$t('maintenance.purge_employee_warning')"
      :cancel-label="$t('general.no_cancel')"
      :confirm-label="$t('maintenance.yes_purge')"
    ></purge-confirmation>
  </div>
</template>

<script>
import {navigate} from '@/core/util/helper/navigation';
import {APIService} from '@/core/util/services/api.service';
import SelectedEmployee from '@/orangehrmMaintenancePlugin/components/SelectedEmployee';
import EmployeeRecords from '@/orangehrmMaintenancePlugin/components/EmployeeRecords';
import ConfirmationDialog from '@/core/components/dialogs/ConfirmationDialog';
import MaintenanceNote from '@/orangehrmMaintenancePlugin/components/MaintenanceNote';
import EmployeeLeaveCalendarSyncCardVue from '../../orangehrmCalendarPlugin/components/EmployeeLeaveCalendarSyncCard.vue';
import EmployeeAttendanceCorrectionCardVue from '../../orangehrmCalendarPlugin/components/EmployeeAttendanceCorrectionCard.vue';

const selectedEmployeeModel = {
  firstName: '',
  middleName: '',
  lastName: '',
  employeeId: '',
  empNumber: '',
};

export default {
  name: 'PurgeEmployee',
  components: {
    'purge-confirmation': ConfirmationDialog,
    'purge-employee-records': EmployeeRecords,
    'selected-employee': SelectedEmployee,
    'maintenance-note': MaintenanceNote,
    'calendar-sync': EmployeeLeaveCalendarSyncCardVue,
    'attendance-correction': EmployeeAttendanceCorrectionCardVue,
  },

  props: {
    instanceIdentifier: {
      type: String,
      default: null,
    },
  },

  setup() {
    const http = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/maintenance/purge',
    );

    return {
      http,
    };
  },

  data() {
    return {
      isLoading: false,
      showPurgeableEmployee: false,
      selectedEmployee: {...selectedEmployeeModel},
    };
  },

  methods: {
    onClickSearch(employee) {
      this.selectedEmployee = {...selectedEmployeeModel};
      if (employee) {
        this.selectedEmployee = {...employee};
        this.showPurgeableEmployee = true;
      } else {
        this.showPurgeableEmployee = false;
      }
    },
    onClickPurge(empNumber) {
      this.$refs.purgeDialog.showDialog().then((confirmation) => {
        if (confirmation === 'ok') {
          this.purgeEmployee(empNumber);
        }
      });
    },
    purgeEmployee(empNumber) {
      this.isLoading = true;
      this.http
        .deleteAll({
          empNumber: empNumber,
        })
        .then(() => {
          return this.$toast.success({
            title: this.$t('general.success'),
            message: this.$t('maintenance.purge_success'),
          });
        })
        .then(() => {
          this.showPurgeableEmployee = false;
          this.selectedEmployee = {...selectedEmployeeModel};
          this.isLoading = false;
          navigate('/maintenance/purgeEmployee');
        });
    },
  },
};
</script>

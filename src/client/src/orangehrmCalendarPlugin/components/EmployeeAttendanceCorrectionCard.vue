<template>
  <div class="orangehrm-card-container">
    <oxd-text tag="h6" class="orangehrm-main-title">
      Employee Attendance Correction
    </oxd-text>
    <oxd-divider class="orangehrm-divider" />
    <oxd-text tag="p">
      This will run the attendance correction process for all employees for
      today.
    </oxd-text>
    <oxd-divider class="orangehrm-divider" />
    <oxd-button
      display-type="secondary"
      icon-name="arrow-repeat"
      label="Run now"
      @click="onSubmitAction"
    />
  </div>
</template>

<script>
import {APIService} from '@/core/util/services/api.service';

export default {
  name: 'EmployeeLeaveCalendarSyncCard',
  setup() {
    const http = new APIService(
      window.appGlobal.baseUrl,
      `/api/v2/attendance/correction-service`,
    );
    return {
      http,
    };
  },
  methods: {
    onSubmitAction() {
      this.http
        .request({
          method: 'GET',
        })
        .then((response) => {
          if (response.status !== 200) {
            throw new Error('Problem with the request or network.');
          }
          const {data} = response.data;
          return data[0];
        })
        .then((data) => {
          const {error, message} = data;
          if (error) {
            this.$toast.warn({
              title: 'Some errors syncing the calendar',
              message: error,
            });
            return;
          }
          const text = Array.isArray(message) ? message.join('\n') : message;
          this.$toast.success({
            title: 'Attendance correction run successfully.',
            message: text,
          });
        })
        .catch((error) => {
          this.$toast.error({
            title: 'Error with the attendance correction.',
            message: error.message,
          });
        });
    },
  },
};
</script>

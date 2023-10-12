<template>
  <div class="orangehrm-card-container">
    <oxd-text tag="h6" class="orangehrm-main-title">
      Employee Leave Calendar Sync
    </oxd-text>
    <oxd-divider class="orangehrm-divider" />
    <oxd-text tag="p">
      This will sync the employee leave calendar
      <em>(google calendar)</em> with the employee leave entitlements.
    </oxd-text>
    <oxd-divider class="orangehrm-divider" />
    <oxd-button
      display-type="secondary"
      icon-name="arrow-repeat"
      label="Sync now"
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
      `/api/v2/calendar/sync`,
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
          if (typeof response.data['completed'] === 'object') {
            throw new Error(response.data.data);
          }
          return response.data.data;
        })
        .then((data) => {
          const {completed, errors} = data;
          if (errors.length) {
            this.$toast.warn({
              title: 'Some errors syncing the calendar',
              message: errors.join(', '),
            });
            return;
          }
          this.$toast.success({
            title: 'Calendar synced successfully',
            message: `Successfully synced ${completed?.length || '0'}`,
          });
        })
        .catch((error) => {
          this.$toast.error({
            title: 'Error syncing the calendar',
            message: error.message,
          });
        });
    },
  },
};
</script>

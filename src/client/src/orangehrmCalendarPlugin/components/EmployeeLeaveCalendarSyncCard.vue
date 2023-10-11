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
            this.$toast.saveError();
            return;
          }
          return response.json();
        })
        .then((data) => {
          console.log(data);
          this.$toast.saveSuccess();
        });
    },
  },
};
</script>

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
  <div v-show="date" class="oxd-table-card-cell">
    <div v-show="showHeader" class="header">
      {{ header.title }}
    </div>
    <div class="data">
      <oxd-icon
        v-if="type && type === 'BREAK_TIME'"
        name="clock"
        class="icon"
        size="large"
      />
      <div>
        <oxd-text tag="p">
          {{ date }}
          <!-- <small class="timezone"> GMT {{ offset ? offset : '00:00' }} </small> -->
        </oxd-text>
        <strong>
          {{ time }}
        </strong>
      </div>
    </div>
  </div>
</template>

<script>
import {onMounted, getCurrentInstance} from 'vue';
import {useInjectTableProps, OxdIcon} from '@ohrm/oxd';

export default {
  name: 'RecordCell',

  components: {
    OxdIcon,
  },

  props: {
    header: {
      type: Object,
      required: true,
    },
    date: {
      type: String,
      default: null,
    },
    time: {
      type: String,
      default: null,
    },
    offset: {
      type: String,
      default: null,
    },
    type: {
      type: String,
      required: true,
    },
  },

  setup(props) {
    const {screenState} = useInjectTableProps();

    onMounted(() => {
      if (props.type == 'BREAK_TIME') {
        const instance = getCurrentInstance();
        if (instance && instance.proxy) {
          const parentElement =
            instance.proxy.$el.parentNode.parentNode.parentNode;
          parentElement.classList.add('break');
        }
      }
    });

    return {
      screenState,
    };
  },

  computed: {
    showHeader() {
      return !(
        this.screenState.screenType === 'lg' ||
        this.screenState.screenType === 'xl'
      );
    },
  },
};
</script>

<style lang="scss" scoped>
.oxd-table-card-cell {
  display: block;

  .data {
    display: flex;
    align-items: center;
  }

  .icon {
    margin-right: 0.25rem;
  }

  & .header {
    font-weight: 700;
  }

  & .timezone {
    color: $oxd-interface-gray-color;
    white-space: nowrap;
  }
}
</style>

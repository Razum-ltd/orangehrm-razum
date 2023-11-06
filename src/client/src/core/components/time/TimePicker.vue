<template>
  <div role="alert" class="oxd-time-picker">
    <div class="oxd-time-hour-input">
      <oxd-icon-button
        name="chevron-up"
        class="oxd-time-hour-input-up"
        role="none"
        :with-container="false"
        @click="increment(1, 'hour')"
      />
      <oxd-input
        :value="hour"
        class="oxd-time-hour-input-text"
        @change="onChange($event, 'hour')"
      />
      <oxd-icon-button
        name="chevron-down"
        class="oxd-time-hour-input-down"
        role="none"
        :with-container="false"
        @click="decrement(1, 'hour')"
      />
    </div>
    <div class="oxd-time-seperator">
      <span class="oxd-time-seperator-icon">:</span>
    </div>
    <div class="oxd-time-minute-input">
      <oxd-icon-button
        name="chevron-up"
        class="oxd-time-minute-input-up"
        role="none"
        :with-container="false"
        @click="increment(step, 'minute')"
      />
      <oxd-input
        :value="minute"
        class="oxd-time-minute-input-text"
        @change="onChange($event, 'minute')"
      />
      <oxd-icon-button
        name="chevron-down"
        class="oxd-time-minute-input-down"
        role="none"
        :with-container="false"
        @click="decrement(step, 'minute')"
      />
    </div>
  </div>
</template>

<script lang="ts">
import {formatDate, parseDate} from '../../util/helper/datefns';
import {OxdInput, OxdIconButton} from '@ohrm/oxd';
import {defineComponent, reactive, toRefs, watchEffect} from 'vue';

interface State {
  hour: string;
  minute: string;
}

export default defineComponent({
  name: 'TimePicker',

  components: {
    'oxd-input': OxdInput,
    'oxd-icon-button': OxdIconButton,
  },

  props: {
    modelValue: {
      type: String,
      required: false,
      default: null,
    },
    step: {
      type: Number,
      required: false,
      default: 1,
    },
  },

  emits: ['update:modelValue'],

  setup(props, context) {
    const state: State = reactive({
      hour: '01',
      minute: '00',
    });

    const setValue = (input: number, type: string) => {
      if (isNaN(input)) return;
      if (type === 'hour') {
        if (input > 0 && input < 24) {
          state.hour = input < 1 ? '0' + input : input.toString();
        }
        return;
      }
      if (input >= 0 && input < 60) {
        // If input val is not a multiply of step, get nearest value
        const minutes = (Math.round(input / props.step) * props.step) % 60;
        state.minute = minutes < 10 ? '0' + minutes : minutes.toString();
      }
    };

    const onChange = ($event: Event, type: string) => {
      const input = parseInt(($event.target as HTMLInputElement).value);
      setValue(input, type);
    };

    const increment = (step: number, type: keyof State) => {
      const input = parseInt(state[type]);
      setValue(input + step, type);
    };

    const decrement = (step: number, type: keyof State) => {
      const input = parseInt(state[type]);
      setValue(input - step, type);
    };

    watchEffect(() => {
      const time = parseDate(props.modelValue, 'HH:mm');
      if (time) {
        // getHours() return 0-23, return 12 if 0
        // setValue(time.getHours() % 12 || 12, 'hour');
        setValue(time.getHours(), 'hour');
        setValue(time.getMinutes(), 'minute');
      }
    });

    watchEffect(() => {
      const parsedDate = parseDate(`${state.hour}:${state.minute}`, 'HH:mm');

      context.emit(
        'update:modelValue',
        parsedDate ? formatDate(parsedDate, 'HH:mm') : null,
      );
    });

    return {
      onChange,
      increment,
      decrement,
      ...toRefs(state),
    };
  },
});
</script>

<style src="./time-input.scss" lang="scss" scoped></style>

<template>
  <div v-click-outside="onFocusOut" :class="classes">
    <div class="oxd-time-input">
      <oxd-input
        ref="oxdInput"
        :has-error="hasError"
        :disabled="disabled"
        :readonly="readonly"
        :value="timeDisplay"
        :placeholder="placeholder"
        @change="onTimeInput"
        @click="toggleDropdown"
      />
      <oxd-icon :class="timeIconClasses" name="clock" @click="toggleDropdown" />
    </div>
    <time-picker
      v-if="open"
      :step="step"
      :model-value="modelValue"
      @update:model-value="$emit('update:modelValue', $event)"
    ></time-picker>
  </div>
</template>

<script lang="ts">
import {defineComponent} from 'vue';
import {formatDate, parseDate} from '../../util/helper/datefns';
import type {ComponentPublicInstance} from 'vue';
import TimePicker from './TimePicker.vue';
import {clickOutsideDirective, OxdIcon, OxdInput} from '@ohrm/oxd';

export default defineComponent({
  name: 'OxdTimeInput',

  components: {
    'oxd-icon': OxdIcon,
    'oxd-input': OxdInput,
    'time-picker': TimePicker,
  },

  directives: {
    'click-outside': clickOutsideDirective,
  },

  props: {
    modelValue: {
      type: String,
      required: false,
      default: '',
    },
    hasError: {
      type: Boolean,
      required: false,
      default: false,
    },
    disabled: {
      type: Boolean,
      required: false,
      default: false,
    },
    readonly: {
      type: Boolean,
      required: false,
      default: false,
    },
    placeholder: {
      type: String,
      required: false,
      default: null,
    },
    step: {
      type: Number,
      required: false,
      default: 1,
    },
    classes: {
      type: Object,
      required: false,
      default: () => ({
        'oxd-time-wrapper': true,
        'oxd-input-field-bottom-space': true,
      }),
    },
  },

  emits: [
    'blur',
    'update:modelValue',
    'timeselect:opened',
    'timeselect:closed',
  ],

  data() {
    return {
      open: false,
    };
  },

  computed: {
    timeIconClasses(): object {
      return {
        'oxd-time-input--clock': true,
        '--disabled': this.disabled,
        '--readonly': this.readonly,
      };
    },
    timeDisplay(): string | null {
      const parsedDate = parseDate(this.modelValue, 'HH:mm');
      return parsedDate ? formatDate(parsedDate, 'HH:mm') : null;
    },
  },

  methods: {
    onFocusOut() {
      this.open && this.closeDropdown();
    },
    onTimeInput($event: Event) {
      const input = ($event.target as HTMLInputElement).value;
      const parsedDate = parseDate(input, 'hh:mm a');
      this.$emit(
        'update:modelValue',
        parsedDate ? formatDate(parsedDate, 'HH:mm') : null,
      );
    },
    toggleDropdown() {
      if (!this.disabled) {
        if (!this.open) {
          (this.$refs.oxdInput as ComponentPublicInstance).$el.focus();
          this.openDropdown();
        } else {
          this.closeDropdown();
        }
      }
    },
    openDropdown() {
      this.open = true;
      this.$emit('timeselect:opened');
    },
    closeDropdown() {
      this.open = false;
      this.$emit('timeselect:closed');
    },
  },
});
</script>

<style src="./time-input.scss" lang="scss" scoped></style>

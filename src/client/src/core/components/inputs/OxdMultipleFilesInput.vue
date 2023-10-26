<template>
  <input
    ref="input"
    type="file"
    multiple
    v-bind="$attrs"
    :class="fileInputClasses"
    @focus="onFocus"
    @blur="onBlur"
    @input="onInput"
  />
  <div :class="classes" :style="style" @click="onClick">
    <slot></slot>
    <template v-if="!$slots.default">
      <div
        v-if="buttonLabel"
        :class="{'oxd-file-button': true, '--disabled': disabled}"
      >
        {{ buttonLabel }}
      </div>
      <div class="oxd-file-input-div">
        {{ placeholderText }}
      </div>
      <oxd-icon
        v-if="buttonIcon"
        :name="buttonIcon"
        :class="{'oxd-file-input-icon': true, '--disabled': disabled}"
      />
    </template>
  </div>
  <div v-if="errors && errors.length > 0" class="oxd-file-errors">
    <small v-for="error in errors" :key="error" class="oxd-file-error">
      {{ error }}
    </small>
  </div>
  <div
    v-if="selectedFiles && selectedFiles.length > 0"
    class="orangehrm-file-previews"
  >
    <div
      v-for="file in selectedFiles"
      :key="file.name"
      :class="{'orangehrm-file-preview': true, '--disabled': disabled}"
      @click="!disabled && removeFile(file.name)"
    >
      <oxd-icon class="orangehrm-file-icon" name="file-earmark-text" />
      <oxd-text class="orangehrm-file-name" tag="p" :title="file.name">
        {{ file.name }}
        <oxd-icon class="orangehrm-file-download" name="remove" />
      </oxd-text>
    </div>
  </div>
</template>

<script lang="ts">
import {defineComponent} from 'vue';
import {OxdIcon} from '@ohrm/oxd';

export interface OutputFile extends Pick<File, 'name' | 'type' | 'size'> {
  base64: string;
}

export interface State {
  focused: boolean;
  inputValue: string;
  selectedFiles: OutputFile[];
  errors: Array<string>;
}

interface FileInputElement extends Omit<HTMLInputElement, 'files'> {
  files: File[];
}

export default defineComponent({
  name: 'OxdMultipleFilesInput',
  components: {
    'oxd-icon': OxdIcon,
  },
  inheritAttrs: false,
  props: {
    modelValue: {
      type: Object,
      required: false,
      default: () => null,
    },
    style: {
      type: Object,
      required: false,
      default: () => ({}),
    },
    buttonLabel: {
      type: String,
      required: false,
      default: null,
    },
    buttonIcon: {
      type: String,
      required: false,
      default: 'upload',
    },
    placeholder: {
      type: String,
      required: false,
      default: null,
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
  },

  emits: ['click', 'focus', 'blur', 'input', 'update:modelValue'],

  data(): State {
    return {
      focused: false,
      inputValue: '',
      selectedFiles: [],
      errors: [],
    };
  },

  computed: {
    classes(): object {
      return {
        'oxd-file-div': true,
        'oxd-file-div--active': !this.focused,
        'oxd-file-div--focus': this.focused,
        'oxd-file-div--error': this.errors.length > 0,
        'oxd-file-div--disabled': this.disabled,
        'oxd-file-div--readonly': this.readonly,
        'oxd-file-div--empty': this.selectedFiles.length === 0,
      };
    },
    fileInputClasses(): object {
      return {
        'oxd-file-input': true,
      };
    },
    placeholderText(): string {
      if (this.inputValue) {
        return this.inputValue;
      }
      return (
        this.placeholder ??
        this.$t('general.no_file_chosen', {
          defaultValue: 'No file chosen',
        })
      );
    },
  },

  watch: {
    modelValue(newValue, oldValue) {
      if (
        newValue !== oldValue &&
        newValue === undefined &&
        newValue === null
      ) {
        this.inputValue = '';
      }
      if (this.$attrs.rules && Array.isArray(this.$attrs.rules)) {
        // loop over each rule and call the function
        const errors: string[] = [];
        this.$attrs.rules.forEach((rule) => {
          const result = rule(this.modelValue) as string | boolean;
          if (result !== true) errors.push(result.toString());
        });
        if (errors.length > 0) {
          // if there are errors set the errors array
          this.errors = errors;
          // reset to default values
          this.$emit('update:modelValue', null);
          this.inputValue = '';
          this.selectedFiles = [];
        } else {
          // if there are no errors set the errors array to empty
          this.errors = [];
        }
      }
    },
  },

  // on load check if v-model has an array of files if so set the input value
  mounted() {
    if (this.modelValue) {
      this.selectedFiles = this.modelValue as OutputFile[];
      this.inputValue = `${this.selectedFiles.length} file${
        this.selectedFiles.length > 1 ? 's' : ''
      } selected`;
    }
  },

  methods: {
    onClick(e: Event) {
      if (this.disabled || this.readonly) return;
      const inputRef = this.$refs.input as HTMLInputElement;
      inputRef.focus();
      inputRef.click();
      this.$emit('click', e);
    },
    onFocus(e: Event) {
      this.focused = true;
      this.$emit('focus', e);
    },
    onBlur(e: Event) {
      this.focused = false;
      this.$emit('blur', e);
    },
    removeFile(fileName: string) {
      const currentFileCount = this.selectedFiles.length;
      this.selectedFiles = this.selectedFiles.filter(
        (file) => file.name !== fileName,
      );
      this.inputValue =
        currentFileCount - 1 === 0
          ? '' // No files selected - shows default placeholder
          : `${currentFileCount - 1} file${
              this.selectedFiles.length > 1 ? 's' : ''
            } selected`;
      this.$emit(
        'update:modelValue',
        this.selectedFiles.length > 0 ? this.selectedFiles : null,
      );
    },
    onInput(e: Event) {
      e.preventDefault();
      const files = [...(e.target as FileInputElement).files];
      const inputValue = `${files.length} file${
        files.length > 1 ? 's' : ''
      } selected`;

      if (files.length > 0) {
        // handle all files
        const outputFiles: OutputFile[] = [];
        files.forEach((file: File) => {
          const reader = new FileReader();
          reader.readAsDataURL(file);
          reader.onload = () => {
            outputFiles.push({
              name: file.name,
              type: file.type,
              size: file.size,
              base64: reader.result as string,
            });
            if (outputFiles.length === files.length) {
              this.inputValue = inputValue;
              this.$emit('update:modelValue', outputFiles);
              this.selectedFiles = outputFiles;
            }
          };
        });
      } else {
        this.inputValue = '';
        this.selectedFiles = [];
        this.$emit('update:modelValue', null);
      }

      this.$emit('input', e);
    },
  },
});
</script>

<style src="./multiple-file-input.scss" lang="scss" scoped></style>

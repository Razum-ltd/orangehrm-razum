<template>
  <div class="orangehrm-editor-input">
    <label for="editor">{{ label }}</label>
    <ckeditor
      id="editor"
      v-model="internalValue"
      :editor="editor"
      :config="editorConfig"
    ></ckeditor>
  </div>
</template>
<script lang="tsx">
import ClassicEditor from '@ckeditor/ckeditor5-build-classic';

export default {
  name: 'EditorInput',
  props: {
    label: {
      type: String,
      default: '',
    },
    value: {
      type: String,
      default: '',
    },
  },
  emits: ['update'],
  data() {
    return {
      editor: ClassicEditor,
      editorConfig: {
        // The configuration of the editor.
      },
      internalValue: this.value || '',
    };
  },
  watch: {
    internalValue(newValue: string) {
      this.$emit('update', newValue);
    },
  },
};
</script>
<style src="./editor-dialog.scss" lang="scss" scoped></style>
<style lang="scss">
.ck-editor {
  border-radius: 0.65rem !important;
  border: 1px solid #e8eaef !important;
  overflow: auto !important;
}
.ck.ck-editor__main > .ck-editor__editable:not(.ck-focused),
.ck.ck-toolbar {
  border: none !important;
}
</style>

<template>
  <BaseFileUploader
    multiple
    preserve-local-files
    accept=".pdf,.jpg,.jpeg,.png,.webp"
    @change="add"
    @remove="remove"
  />
</template>
<script setup lang="ts">
const model = defineModel<File[]>({ required: true })
function add(_field: string, files: FileList | File | string) {
  if (typeof files === 'string') return
  model.value = [
    ...model.value,
    ...(files instanceof File ? [files] : Array.from(files)),
  ]
}
function remove(value: number | { fileObject?: File }) {
  const index =
    typeof value === 'number'
      ? value
      : model.value.indexOf(value.fileObject as File)
  if (index >= 0) model.value = model.value.filter((_, i) => i !== index)
}
</script>

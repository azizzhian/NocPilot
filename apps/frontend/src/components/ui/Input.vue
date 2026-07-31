<script setup lang="ts">
import { cn } from '@/lib/utils'

interface Props {
  class?: string
  modelValue?: string
  placeholder?: string
  type?: string
}

const props = defineProps<Props>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

function onValueChange(e: Event) {
  emit('update:modelValue', (e.target as HTMLInputElement).value)
}
</script>

<template>
  <input
    :type="type ?? 'text'"
    :value="modelValue ?? ''"
    :placeholder="placeholder"
    :class="cn(
      'form-control h-10 px-4 py-2',
      (type === 'date' || type === 'time' || type === 'datetime-local') && 'form-control-date',
      props.class,
    )"
    @input="onValueChange"
    @change="onValueChange"
  />
</template>

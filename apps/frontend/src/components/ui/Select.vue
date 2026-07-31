<script setup lang="ts">
import { cn } from '@/lib/utils'

interface Option {
  value: string | number
  label: string
}

interface Props {
  modelValue?: string | number | null
  class?: string
  options?: Option[]
}

const props = defineProps<Props>()
const emit = defineEmits<{ 'update:modelValue': [value: string | number] }>()
</script>

<template>
  <select
    :value="modelValue ?? ''"
    :class="cn('form-control form-select h-10 px-4 py-2', props.class)"
    @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
  >
    <slot />
    <option
      v-for="opt in options"
      :key="String(opt.value)"
      :value="opt.value"
    >
      {{ opt.label }}
    </option>
  </select>
</template>

<script setup lang="ts">
import { cn } from '@/lib/utils'
import Input from '@/components/ui/Input.vue'
import { Search } from 'lucide-vue-next'

interface Props {
  modelValue?: string
  placeholder?: string
  class?: string
}

defineProps<Props>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <div :class="cn('search-input relative', $props.class)">
    <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#94A3B8]" />
    <Input
      :model-value="modelValue"
      :placeholder="placeholder"
      class="pl-10"
      @update:model-value="emit('update:modelValue', $event)"
    />
  </div>
</template>

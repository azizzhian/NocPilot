<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<{
    message: string
    variant?: 'success' | 'error' | 'info'
    /** Auto-hide after ms; 0 = stay until dismissed manually */
    duration?: number
  }>(),
  { variant: 'info' },
)

const emit = defineEmits<{ dismiss: [] }>()

const hideAfterMs = computed(() => {
  if (props.duration !== undefined) return props.duration
  return props.variant === 'error' ? 8000 : 4000
})

let timer: ReturnType<typeof setTimeout> | undefined

onMounted(() => {
  const ms = hideAfterMs.value
  if (ms > 0) {
    timer = setTimeout(() => emit('dismiss'), ms)
  }
})

onBeforeUnmount(() => {
  if (timer) clearTimeout(timer)
})

const variantClass = {
  success: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/90 dark:text-emerald-200',
  error: 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950/90 dark:text-red-200',
  info: 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950/90 dark:text-blue-200',
}
</script>

<template>
  <Teleport to="body">
    <div
      role="alert"
      :class="cn(
        'pointer-events-none fixed left-1/2 top-4 z-[100] w-[min(92vw,32rem)] -translate-x-1/2 rounded-xl border px-4 py-3 text-sm shadow-lg',
        variantClass[props.variant],
      )"
    >
      {{ message }}
    </div>
  </Teleport>
</template>

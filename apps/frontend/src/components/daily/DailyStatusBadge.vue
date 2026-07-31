<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  status: string
  type: string
  id: number
  action?: string
}>()

const emit = defineEmits<{ 'need-action': [id: number]; 'mark-clear': [payload: { type: string; id: number }] }>()

const isClear = computed(() => props.status?.toLowerCase() === 'clear')

async function markClear() {
  if (props.type === 'complaint' && !props.action?.trim()) {
    emit('need-action', props.id)
    return
  }
  emit('mark-clear', { type: props.type, id: props.id })
}
</script>

<template>
  <div class="mt-2 flex flex-wrap items-center gap-2">
    <span
      :class="[
        isClear
          ? 'bg-success/10 text-success'
          : 'bg-warning/10 text-warning',
        'inline-block rounded-full px-2.5 py-0.5 text-xs font-medium',
      ]"
    >
      {{ status }}
    </span>
    <button
      v-if="!isClear"
      type="button"
      class="rounded-lg bg-success px-2.5 py-1 text-xs font-medium text-white transition hover:bg-success/90"
      @click="markClear"
    >
      ✓ Tandai Clear
    </button>
  </div>
</template>

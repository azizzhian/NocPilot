<script setup lang="ts">
defineProps<{
  creatorName?: string | null
  clearerName?: string | null
  status?: string
}>()
</script>

<template>
  <p class="mt-1.5 text-xs text-muted">
    <span v-if="creatorName">Input: <span class="font-medium text-foreground">{{ creatorName }}</span></span>
    <span v-if="creatorName && status === 'Clear' && clearerName"> · </span>
    <span v-if="status === 'Clear' && clearerName">
      Clear: <span class="font-medium text-success">{{ clearerName }}</span>
    </span>
    <span v-else-if="status !== 'Clear' && creatorName" class="opacity-80"> · On-Progress</span>
  </p>
</template>

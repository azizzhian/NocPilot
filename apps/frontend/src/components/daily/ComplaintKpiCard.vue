<script setup lang="ts">
import { MessageSquare } from 'lucide-vue-next'

defineProps<{
  value: number
}>()
</script>

<template>
  <div
    class="relative overflow-hidden rounded-[18px] border border-border bg-card p-5 card-shadow sm:max-w-sm"
  >
    <div class="relative z-10 flex items-center gap-4">
      <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-danger/10 text-danger">
        <MessageSquare class="h-6 w-6" />
      </div>
      <div>
        <div class="flex items-baseline gap-2">
          <span class="text-4xl font-bold tracking-tight text-danger">{{ value }}</span>
          <span class="text-lg font-semibold text-foreground">Komplain</span>
        </div>
        <p class="mt-0.5 text-sm text-muted">Total komplain hari ini</p>
      </div>
    </div>

    <svg
      class="pointer-events-none absolute -bottom-1 -right-2 h-24 w-40 text-danger/15"
      viewBox="0 0 160 96"
      fill="none"
      aria-hidden="true"
    >
      <path
        d="M0 70 C30 40, 50 90, 80 55 C110 20, 130 60, 160 35 L160 96 L0 96 Z"
        fill="currentColor"
      />
      <path
        d="M0 78 C35 55, 55 88, 85 62 C115 36, 135 70, 160 48"
        stroke="currentColor"
        stroke-width="3"
        fill="none"
        opacity="0.5"
      />
    </svg>
  </div>
</template>

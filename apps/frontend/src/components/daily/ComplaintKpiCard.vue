<script setup lang="ts">
import { CheckCircle2, Clock, MessageSquare } from 'lucide-vue-next'

defineProps<{
  today: number
  onProgress: number
  clear: number
}>()
</script>

<template>
  <div class="grid gap-4 sm:grid-cols-3">
    <div
      class="relative overflow-hidden rounded-[18px] border border-border bg-card p-5 card-shadow"
    >
      <div class="relative z-10 flex items-center gap-4">
        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-danger/10 text-danger">
          <MessageSquare class="h-6 w-6" />
        </div>
        <div>
          <div class="flex items-baseline gap-2">
            <span class="text-4xl font-bold tracking-tight text-danger">{{ today }}</span>
            <span class="text-lg font-semibold text-foreground">Komplain</span>
          </div>
          <p class="mt-0.5 text-sm text-muted">Total komplain hari ini</p>
        </div>
      </div>
    </div>

    <div
      class="relative overflow-hidden rounded-[18px] border border-border bg-card p-5 card-shadow"
    >
      <div class="relative z-10 flex items-center gap-4">
        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-warning text-warning">
          <Clock class="h-6 w-6" />
        </div>
        <div>
          <div class="flex items-baseline gap-2">
            <span class="text-4xl font-bold tracking-tight text-warning">{{ onProgress }}</span>
            <span class="text-lg font-semibold text-foreground">On-Progress</span>
          </div>
          <p class="mt-0.5 text-sm text-muted">Belum clear dari hari sebelumnya</p>
        </div>
      </div>
    </div>

    <div
      class="relative overflow-hidden rounded-[18px] border border-border bg-card p-5 card-shadow"
    >
      <div class="relative z-10 flex items-center gap-4">
        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-success/10 text-success">
          <CheckCircle2 class="h-6 w-6" />
        </div>
        <div>
          <div class="flex items-baseline gap-2">
            <span class="text-4xl font-bold tracking-tight text-success">{{ clear }}</span>
            <span class="text-lg font-semibold text-foreground">Clear</span>
          </div>
          <p class="mt-0.5 text-sm text-muted">Diselesaikan hari ini</p>
        </div>
      </div>
    </div>
  </div>
</template>

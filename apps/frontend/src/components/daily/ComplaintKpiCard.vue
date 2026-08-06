<script setup lang="ts">
import { CheckCircle2, Clock, MessageSquare } from 'lucide-vue-next'

defineProps<{
  today: number
  onProgress: number
  clear: number
}>()
</script>

<template>
  <div class="grid gap-3 sm:grid-cols-3 sm:gap-4">
    <div
      class="relative overflow-hidden rounded-[18px] border border-border bg-card p-4 card-shadow sm:p-5"
    >
      <div class="relative z-10 flex items-center gap-3 sm:gap-4">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-danger/10 text-danger sm:h-14 sm:w-14">
          <MessageSquare class="h-5 w-5 sm:h-6 sm:w-6" />
        </div>
        <div class="min-w-0">
          <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0">
            <span class="text-3xl font-bold tracking-tight text-danger sm:text-4xl">{{ today }}</span>
            <span class="text-base font-semibold text-foreground sm:text-lg">Komplain</span>
          </div>
          <p class="mt-0.5 text-xs text-muted sm:text-sm">Total komplain hari ini</p>
        </div>
      </div>
    </div>

    <div
      class="relative overflow-hidden rounded-[18px] border border-border bg-card p-4 card-shadow sm:p-5"
    >
      <div class="relative z-10 flex items-center gap-3 sm:gap-4">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-warning/20 text-warning sm:h-14 sm:w-14">
          <Clock class="h-5 w-5 sm:h-6 sm:w-6" />
        </div>
        <div class="min-w-0">
          <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0">
            <span class="text-3xl font-bold tracking-tight text-warning sm:text-4xl">{{ onProgress }}</span>
            <span class="text-base font-semibold text-foreground sm:text-lg">On-Progress</span>
          </div>
          <p class="mt-0.5 text-xs text-muted sm:text-sm">Belum clear dari hari sebelumnya</p>
        </div>
      </div>
    </div>

    <div
      class="relative overflow-hidden rounded-[18px] border border-border bg-card p-4 card-shadow sm:p-5"
    >
      <div class="relative z-10 flex items-center gap-3 sm:gap-4">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-success/10 text-success sm:h-14 sm:w-14">
          <CheckCircle2 class="h-5 w-5 sm:h-6 sm:w-6" />
        </div>
        <div class="min-w-0">
          <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0">
            <span class="text-3xl font-bold tracking-tight text-success sm:text-4xl">{{ clear }}</span>
            <span class="text-base font-semibold text-foreground sm:text-lg">Clear</span>
          </div>
          <p class="mt-0.5 text-xs text-muted sm:text-sm">Diselesaikan hari ini</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { CheckCircle2, Clock, Gauge, MessageSquare, Timer } from 'lucide-vue-next'

defineProps<{
  today: number
  onProgress: number
  waiting?: number
  clear: number
  sla?: { percent: number | null; within_60: number; clear: number }
}>()
</script>

<template>
  <div class="grid gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-5">
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
          <p class="mt-0.5 text-xs text-muted sm:text-sm">Sedang ditangani NOC</p>
        </div>
      </div>
    </div>

    <div
      class="relative overflow-hidden rounded-[18px] border border-border bg-card p-4 card-shadow sm:p-5"
    >
      <div class="relative z-10 flex items-center gap-3 sm:gap-4">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-info/10 text-info sm:h-14 sm:w-14">
          <Timer class="h-5 w-5 sm:h-6 sm:w-6" />
        </div>
        <div class="min-w-0">
          <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0">
            <span class="text-3xl font-bold tracking-tight text-info sm:text-4xl">{{ waiting ?? 0 }}</span>
            <span class="text-base font-semibold text-foreground sm:text-lg">Menunggu</span>
          </div>
          <p class="mt-0.5 text-xs text-muted sm:text-sm">Menunggu konfirmasi · auto-clear 30 menit</p>
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

    <div
      class="relative overflow-hidden rounded-[18px] border border-border bg-card p-4 card-shadow sm:p-5"
    >
      <div class="relative z-10 flex items-center gap-3 sm:gap-4">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary sm:h-14 sm:w-14">
          <Gauge class="h-5 w-5 sm:h-6 sm:w-6" />
        </div>
        <div class="min-w-0">
          <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0">
            <span class="text-3xl font-bold tracking-tight text-primary sm:text-4xl">
              {{ sla?.percent == null ? '—' : `${sla.percent}%` }}
            </span>
            <span class="text-base font-semibold text-foreground sm:text-lg">Persen Komplain (≤ 60 menit)</span>
          </div>
          <p class="mt-0.5 text-xs text-muted sm:text-sm">
            {{ sla ? `${sla.within_60} / ${sla.clear} komplain Clear` : 'Menunggu data komplain Clear' }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

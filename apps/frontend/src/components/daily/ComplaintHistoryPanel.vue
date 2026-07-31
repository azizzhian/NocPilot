<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { ComplaintHistoryItem, ComplaintHistorySummary } from '@/services/api'
import { AlertTriangle, Check, ChevronRight, Clock, X } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    total: number
    days: number
    items: ComplaintHistoryItem[]
    summary?: ComplaintHistorySummary | null
    loading?: boolean
    open?: boolean
    drawerOnly?: boolean
    title?: string
  }>(),
  {
    open: undefined,
    drawerOnly: false,
    title: 'Riwayat Komplain',
  },
)

const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const internalOpen = ref(false)

const drawerOpen = computed({
  get: () => (props.open !== undefined ? props.open : internalOpen.value),
  set: (v: boolean) => {
    if (props.open !== undefined) emit('update:open', v)
    else internalOpen.value = v
  },
})

watch(
  () => props.open,
  (v) => {
    if (v === undefined) return
    internalOpen.value = v
  },
)

const summary = computed<ComplaintHistorySummary>(() => {
  if (props.summary) return props.summary
  return {
    total: props.total,
    days: props.days,
    count_30d: 0,
    is_repeat: props.total >= 3,
    open_count: props.items.filter((i) => !isClear(i.status)).length,
    clear_count: props.items.filter((i) => isClear(i.status)).length,
    clear_rate: 0,
    avg_clear_hours: null,
    last_date: props.items[0]?.report_date ?? null,
    last_problem: props.items[0]?.problem ?? null,
    score: { value: 0, label: 'Sangat Baik', level: 'good', breakdown: [] },
  }
})

const hasHistory = computed(() => summary.value.total > 0)

function isClear(status: string) {
  return status?.toLowerCase() === 'clear'
}

function formatDate(value: string | null | undefined) {
  if (!value) return '—'
  const d = new Date(value.includes('T') ? value : `${value}T00:00:00`)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatAvgHours(hours: number | null) {
  if (hours == null) return '—'
  if (hours < 1) return `${Math.round(hours * 60)} mnt`
  if (hours < 24) return `${hours} jam`
  return `${(hours / 24).toFixed(1)} hari`
}

function countBadgeClass(n: number) {
  if (n >= 4) return 'bg-danger/10 text-danger'
  if (n >= 2) return 'bg-warning/10 text-warning'
  return 'bg-info/10 text-info'
}

function scoreClass(level: string) {
  if (level === 'risk') return 'bg-danger/10 text-danger border-danger/30'
  if (level === 'watch') return 'bg-warning/10 text-warning border-warning/30'
  return 'bg-success/10 text-success border-success/30'
}

function scoreDot(level: string) {
  if (level === 'risk') return 'bg-danger'
  if (level === 'watch') return 'bg-warning'
  return 'bg-success'
}

function openDrawer() {
  if (!hasHistory.value && !props.loading) return
  drawerOpen.value = true
}

function closeDrawer() {
  drawerOpen.value = false
}
</script>

<template>
  <div>
    <template v-if="!drawerOnly">
      <div
        v-if="loading"
        class="rounded-xl border border-border bg-slate-50 px-3 py-3 text-sm text-muted dark:bg-slate-800/40"
      >
        Memuat riwayat komplain...
      </div>

      <div
        v-else-if="!hasHistory"
        class="rounded-xl border border-dashed border-border px-3 py-2.5 text-xs text-muted"
      >
        Belum ada riwayat komplain ({{ days }} hari).
      </div>

      <button
        v-else
        type="button"
        class="w-full rounded-xl border px-3.5 py-3 text-left transition hover:shadow-sm"
        :class="summary.is_repeat
          ? 'border-warning/40 bg-warning/5 hover:border-warning/60'
          : 'border-border bg-slate-50 hover:border-primary/40 dark:bg-slate-800/40'"
        @click="openDrawer"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0 flex-1">
            <div v-if="summary.is_repeat" class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-warning">
              <AlertTriangle class="h-3.5 w-3.5" />
              Repeat Complaint
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
              <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
                :class="countBadgeClass(summary.total)"
              >
                {{ summary.total }} Komplain
              </span>
              <span
                v-if="summary.count_30d >= 2"
                class="inline-flex items-center rounded-full bg-orange-500/10 px-2 py-0.5 text-[11px] font-semibold text-orange-600 dark:text-orange-400"
              >
                {{ summary.count_30d }}× / 30 Hari
              </span>
              <span
                v-else-if="summary.is_repeat"
                class="inline-flex items-center rounded-full bg-orange-500/10 px-2 py-0.5 text-[11px] font-semibold text-orange-600 dark:text-orange-400"
              >
                Repeat
              </span>
              <span
                v-if="summary.open_count > 0"
                class="inline-flex items-center rounded-full bg-danger/10 px-2 py-0.5 text-[11px] font-semibold text-danger"
              >
                {{ summary.open_count }} Open
              </span>
              <span
                v-else
                class="inline-flex items-center rounded-full bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success"
              >
                Clear
              </span>
              <span
                class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                :class="scoreClass(summary.score.level)"
              >
                <span class="h-1.5 w-1.5 rounded-full" :class="scoreDot(summary.score.level)" />
                {{ summary.score.value }}/100
              </span>
            </div>
            <p class="mt-2 text-xs text-muted">
              Terakhir: {{ formatDate(summary.last_date) }}
            </p>
            <p v-if="summary.last_problem" class="mt-0.5 truncate text-sm font-medium text-foreground">
              {{ summary.last_problem }}
            </p>
          </div>
          <span class="mt-1 inline-flex shrink-0 items-center gap-0.5 text-xs font-medium text-primary">
            Riwayat
            <ChevronRight class="h-3.5 w-3.5" />
          </span>
        </div>
      </button>
    </template>

    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="drawerOpen"
          class="fixed inset-0 z-[60] bg-black/40 backdrop-blur-sm"
          @click="closeDrawer"
        />
      </Transition>
      <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-x-0"
        leave-to-class="translate-x-full"
      >
        <aside
          v-if="drawerOpen"
          class="fixed inset-y-0 right-0 z-[61] flex w-full max-w-md flex-col border-l border-border bg-card shadow-xl"
          role="dialog"
          :aria-label="title"
        >
          <div class="flex items-start justify-between border-b border-border px-5 py-4">
            <div class="min-w-0">
              <h2 class="truncate text-lg font-semibold text-foreground">{{ title }}</h2>
              <p class="mt-0.5 text-xs text-muted">{{ days }} hari terakhir</p>
            </div>
            <button
              type="button"
              class="rounded-lg p-1.5 text-muted hover:bg-muted/40"
              @click="closeDrawer"
            >
              <X class="h-5 w-5" />
            </button>
          </div>

          <div class="flex-1 overflow-y-auto px-5 py-4">
            <div v-if="loading" class="py-12 text-center text-sm text-muted">
              Memuat riwayat...
            </div>

            <template v-else>
              <div
                class="mb-4 rounded-xl border px-4 py-3"
                :class="scoreClass(summary.score.level)"
              >
                <p class="text-xs font-medium uppercase tracking-wide opacity-80">Complaint Score</p>
                <p class="mt-1 text-2xl font-bold">
                  {{ summary.score.value }}<span class="text-base font-semibold opacity-70">/100</span>
                </p>
                <p class="text-sm font-semibold">{{ summary.score.label }}</p>
                <ul v-if="summary.score.breakdown.length" class="mt-2 space-y-0.5 text-[11px] opacity-90">
                  <li v-for="b in summary.score.breakdown" :key="b.key">
                    {{ b.points > 0 ? '+' : '' }}{{ b.points }} · {{ b.label }}
                  </li>
                </ul>
              </div>

              <div class="mb-5 flex flex-wrap gap-1.5">
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="countBadgeClass(summary.total)">
                  {{ summary.total }} Komplain
                </span>
                <span
                  v-if="summary.count_30d >= 2 || summary.is_repeat"
                  class="inline-flex rounded-full bg-orange-500/10 px-2.5 py-1 text-xs font-semibold text-orange-600 dark:text-orange-400"
                >
                  {{ summary.count_30d }}× Repeat (30 Hari)
                </span>
                <span class="inline-flex rounded-full bg-success/10 px-2.5 py-1 text-xs font-semibold text-success">
                  {{ summary.clear_rate }}% Clear
                </span>
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-muted dark:bg-slate-800">
                  <Clock class="h-3 w-3" />
                  Avg Clear: {{ formatAvgHours(summary.avg_clear_hours) }}
                </span>
                <span
                  v-if="summary.open_count > 0"
                  class="inline-flex rounded-full bg-danger/10 px-2.5 py-1 text-xs font-semibold text-danger"
                >
                  {{ summary.open_count }} masih Open
                </span>
              </div>

              <div class="mb-3 h-px bg-border" />

              <ol v-if="items.length" class="relative space-y-0 pl-1">
                <li
                  v-for="(item, idx) in items"
                  :key="item.id"
                  class="relative flex gap-3 pb-6 last:pb-0"
                >
                  <div class="flex w-4 flex-col items-center">
                    <span
                      class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full"
                      :class="isClear(item.status) ? 'bg-success' : 'bg-danger'"
                    />
                    <span
                      v-if="idx < items.length - 1"
                      class="mt-1 w-px flex-1 bg-border"
                    />
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-foreground">{{ formatDate(item.report_date) }}</p>
                    <ul class="mt-1.5 space-y-0.5 text-xs text-muted">
                      <li class="font-medium text-foreground">{{ item.problem || '—' }}</li>
                      <li v-if="item.shift">Shift {{ item.shift }}</li>
                      <li v-if="item.creator_name">Admin: {{ item.creator_name }}</li>
                      <li v-if="item.odc_name">ODC: {{ item.odc_name }}</li>
                      <li class="flex items-center gap-1" :class="isClear(item.status) ? 'text-success' : 'text-warning'">
                        <Check v-if="isClear(item.status)" class="h-3 w-3" />
                        {{ item.status }}
                      </li>
                    </ul>
                  </div>
                </li>
              </ol>
              <p v-else class="py-8 text-center text-sm text-muted">
                Belum ada riwayat komplain ({{ days }} hari).
              </p>
            </template>
          </div>
        </aside>
      </Transition>
    </Teleport>
  </div>
</template>

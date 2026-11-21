<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import {
  dailyEntryApi,
  type ComplaintHistoryItem,
  type ComplaintHistorySummary,
  type DailyEntryItem,
} from '@/services/api'
import Button from '@/components/ui/Button.vue'
import ComplaintHistoryPanel from '@/components/daily/ComplaintHistoryPanel.vue'
import { useAuthStore } from '@/stores/auth'
import { serverNow } from '@/lib/api'
import {
  complaintClearRemainingSeconds,
  formatCountdown,
  formatDuration,
  isComplaintClearTooYoung,
} from '@/lib/utils'
import {
  Calendar,
  Check,
  ChevronLeft,
  ChevronRight,
  ClipboardList,
  Clock,
  Lock,
  MessageSquareWarning,
  MoreVertical,
  Pencil,
  Plus,
  RotateCcw,
  Router,
  Signal,
  Timer,
  Trash2,
  Wifi,
  WifiOff,
} from 'lucide-vue-next'

const auth = useAuthStore()

const props = defineProps<{
  items: DailyEntryItem[]
}>()

const emit = defineEmits<{
  add: []
  edit: [item: DailyEntryItem]
  delete: [item: DailyEntryItem]
  'need-action': [id: number]
  /** Clear ditekan tapi Action masih kosong → popup khusus isi Action (bukan form Edit penuh). */
  'clear-needs-action': [id: number]
  'mark-clear': [payload: { type: string; id: number }]
  'set-status': [payload: { type: string; id: number; status: string }]
}>()

/** Timer auto-clear "Menunggu Konfirmasi" (menit) — sama dengan backend. */
const WAITING_MINUTES = 30

const pageSize = 8
const pageToday = ref(1)
const pageOpen = ref(1)
const openMenuId = ref<number | null>(null)
const menuPos = ref({ top: 0, left: 0 })
const menuItem = computed(() => props.items.find((i) => i.id === openMenuId.value) ?? null)

const historyOpen = ref(false)
const historyLoading = ref(false)
const historyItems = ref<ComplaintHistoryItem[]>([])
const historyTotal = ref(0)
const historySummary = ref<ComplaintHistorySummary | null>(null)
const historyTitle = ref('Riwayat Komplain')
const historyDays = 90

const accents = [
  {
    stripe: 'bg-danger',
    iconBg: 'bg-danger/10',
    iconText: 'text-danger',
    problem: 'text-danger',
  },
  {
    stripe: 'bg-info',
    iconBg: 'bg-info/10',
    iconText: 'text-info',
    problem: 'text-info',
  },
  {
    stripe: 'bg-warning',
    iconBg: 'bg-warning/10',
    iconText: 'text-warning',
    problem: 'text-warning',
  },
  {
    stripe: 'bg-success',
    iconBg: 'bg-success/10',
    iconText: 'text-success',
    problem: 'text-success',
  },
] as const

const todayItems = computed(() => props.items.filter((i) => !i.is_carryover))
const openItems = computed(() => props.items.filter((i) => Boolean(i.is_carryover)))
const clearTodayCount = computed(
  () => todayItems.value.filter((i) => isClear(i.status)).length,
)

const totalPagesToday = computed(() => Math.max(1, Math.ceil(todayItems.value.length / pageSize)))
const totalPagesOpen = computed(() => Math.max(1, Math.ceil(openItems.value.length / pageSize)))

const pagedToday = computed(() => {
  const start = (pageToday.value - 1) * pageSize
  return todayItems.value.slice(start, start + pageSize)
})

const pagedOpen = computed(() => {
  const start = (pageOpen.value - 1) * pageSize
  return openItems.value.slice(start, start + pageSize)
})

function rangeLabel(list: DailyEntryItem[], page: number) {
  if (!list.length) return 'Menampilkan 0 dari 0 komplain'
  const start = (page - 1) * pageSize + 1
  const end = Math.min(page * pageSize, list.length)
  return `Menampilkan ${start}–${end} dari ${list.length} komplain`
}

function accentFor(item: DailyEntryItem, index: number) {
  return accents[(item.id + index) % accents.length]
}

function iconFor(problem: string | null | undefined) {
  const p = (problem ?? '').toLowerCase()
  if (p.includes('modem') || p.includes('router') || p.includes('ont')) return Router
  if (p.includes('putus') || p.includes('mati') || p.includes('down')) return WifiOff
  if (p.includes('redaman') || p.includes('signal') || p.includes('sinyal')) return Signal
  if (p.includes('lambat') || p.includes('internet') || p.includes('wifi')) return Wifi
  return MessageSquareWarning
}

function isClear(status: string) {
  return status?.toLowerCase() === 'clear'
}

function isWaiting(item: DailyEntryItem) {
  return item.status === 'Menunggu Konfirmasi'
}

const nowTick = ref(Date.now())
let tickTimer: ReturnType<typeof setInterval> | null = null
const hasWaitingItem = computed(() => props.items.some((i) => isWaiting(i)))

watch(
  hasWaitingItem,
  (active) => {
    if (active && !tickTimer) {
      tickTimer = setInterval(() => {
        nowTick.value = Date.now()
      }, 20_000)
    } else if (!active && tickTimer) {
      clearInterval(tickTimer)
      tickTimer = null
    }
  },
  { immediate: true },
)
onUnmounted(() => {
  if (tickTimer) clearInterval(tickTimer)
})

/** Sisa waktu auto-clear (menit). */
function waitingLeft(item: DailyEntryItem) {
  if (!isWaiting(item) || !item.handled_at) return 0
  const due = new Date(item.handled_at).getTime() + WAITING_MINUTES * 60_000
  return Math.max(0, Math.ceil((due - nowTick.value) / 60_000))
}

/** Tick 1 detik untuk countdown minimum umur Clear (waktu server via header Date). */
const clearTick = ref(serverNow())
let clearTimer: ReturnType<typeof setInterval> | null = null
const hasYoungComplaint = computed(() =>
  props.items.some((i) => isComplaintClearTooYoung(i, clearTick.value)),
)

watch(
  hasYoungComplaint,
  (active) => {
    if (active && !clearTimer) {
      clearTick.value = serverNow()
      clearTimer = setInterval(() => {
        clearTick.value = serverNow()
      }, 1000)
    } else if (!active && clearTimer) {
      clearInterval(clearTimer)
      clearTimer = null
    }
  },
  { immediate: true },
)
onUnmounted(() => {
  if (clearTimer) clearInterval(clearTimer)
})

function clearHint(item: DailyEntryItem): string {
  const remaining = complaintClearRemainingSeconds(item.created_at, clearTick.value)
  if (remaining === null) return 'Clear belum tersedia (waktu input tidak valid)'
  return `Clear tersedia dalam ${formatCountdown(remaining)}`
}

function performanceClass(category?: string | null) {
  switch (category) {
    case 'Cepat':
      return 'bg-success/10 text-success'
    case 'Lambat':
      return 'bg-info/10 text-info'
    case 'Cukup Lambat':
      return 'bg-warning/10 text-warning'
    case 'Sangat Lambat':
      return 'bg-danger/10 text-danger'
    default:
      return 'bg-muted text-muted'
  }
}

function durationLabel(item: DailyEntryItem) {
  if (item.handling_minutes == null) return ''
  return formatDuration(item.handling_minutes)
}

function itemDate(value: string | null | undefined) {
  return (value ?? '').slice(0, 10)
}

function isClearedFromPreviousDay(item: DailyEntryItem) {
  if (!isClear(item.status)) return false
  const report = itemDate(item.report_date)
  const cleared = itemDate(item.cleared_at) || report
  return Boolean(report && cleared && report < cleared)
}

function complaintCountBadgeClass(n: number) {
  if (n >= 4) return 'bg-danger/10 text-danger'
  if (n >= 2) return 'bg-warning/10 text-warning'
  return 'bg-info/10 text-info'
}

function formatDate(value: string | null | undefined) {
  if (!value) return '—'
  const d = new Date(value.includes('T') ? value : `${value}T00:00:00`)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatDateShort(value: string | null | undefined) {
  if (!value) return '—'
  const d = new Date(value.includes('T') ? value : `${value}T00:00:00`)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
}

function formatTime(value: string | null | undefined) {
  if (!value || !value.includes('T')) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  return `${d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false })} WIB`
}

function metaLine(item: DailyEntryItem) {
  const input = item.creator_name?.trim() || '—'
  const clear = item.clearer_name?.trim() || '—'
  return `Input: ${input} • Clear: ${clear}`
}

function locationLine(item: DailyEntryItem) {
  const parts: string[] = []
  if (item.customer_code) parts.push(`ID ${item.customer_code}`)
  if (item.odc_name) parts.push(`ODC ${item.odc_name}`)
  if (item.shift) parts.push(`Shift ${item.shift}`)
  return parts.length ? parts.join(' • ') : '—'
}

function toggleMenu(id: number, event: MouseEvent) {
  event.stopPropagation()
  if (openMenuId.value === id) {
    closeMenu()
    return
  }
  const btn = event.currentTarget as HTMLElement
  const rect = btn.getBoundingClientRect()
  const menuWidth = 192
  const menuHeight = 96
  const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
  const openUp = rect.bottom + menuHeight > window.innerHeight - 8
  menuPos.value = {
    top: openUp ? rect.top - menuHeight - 4 : rect.bottom + 4,
    left: Math.max(8, left),
  }
  openMenuId.value = id
}

function closeMenu() {
  openMenuId.value = null
}

function onEdit(item: DailyEntryItem) {
  closeMenu()
  if (isComplaintLocked(item)) return
  emit('edit', item)
}

function onDelete(item: DailyEntryItem) {
  closeMenu()
  if (isComplaintLocked(item)) return
  emit('delete', item)
}

/** Tombol pill status di kartu komplain (warna konsisten: oranye / biru / hijau). */
const statusPills = [
  { status: 'On-Progress', label: 'On-Progress', active: 'bg-warning/15 text-warning ring-1 ring-warning/40' },
  { status: 'Menunggu Konfirmasi', label: 'Menunggu Konfirmasi', active: 'bg-info/15 text-info ring-1 ring-info/40' },
  { status: 'Clear', label: 'Clear', active: 'bg-success/15 text-success ring-1 ring-success/40' },
] as const

/** `${id}:${status}` saat request status sedang berjalan — cegah double-click. */
const statusPending = ref<string | null>(null)
let statusPendingTimer: ReturnType<typeof setTimeout> | null = null

function clearStatusPending() {
  statusPending.value = null
  if (statusPendingTimer) {
    clearTimeout(statusPendingTimer)
    statusPendingTimer = null
  }
}

function isStatusBusy(item: DailyEntryItem) {
  return statusPending.value !== null && statusPending.value.startsWith(`${item.id}:`)
}

/**
 * Klik pill status: langsung perbarui status komplain.
 * Tanpa optimistic update — status kartu hanya berubah setelah respons sukses
 * dari server; bila gagal, status tetap seperti semula + notifikasi error
 * ditampilkan induk halaman (InputHarianView).
 */
function isComplaintLocked(item: DailyEntryItem): boolean {
  const ownerId = item.created_by
  const userId = auth.user?.id
  return ownerId == null || userId == null || Number(ownerId) !== Number(userId)
}

function statusPillTitle(item: DailyEntryItem, status: string): string {
  if (status === 'Clear' && !isComplaintLocked(item)) {
    if (complaintClearRemainingSeconds(item.created_at, clearTick.value) === null) {
      return 'Waktu input komplain tidak valid. Komplain belum dapat di-Clear.'
    }
    if (isComplaintClearTooYoung(item, clearTick.value)) {
      return 'Komplain harus berusia minimal 5 menit sebelum dapat di-Clear.'
    }
  }
  if (!isComplaintLocked(item)) return `Ubah status menjadi ${status}`
  return status === 'Clear'
    ? 'Hanya pembuat komplain yang dapat melakukan Clear.'
    : 'Hanya pembuat komplain yang dapat mengubah status komplain.'
}

function onStatusPill(item: DailyEntryItem, status: string) {
  if (isStatusBusy(item) || item.status === status || isComplaintLocked(item)) return
  if (status === 'Clear' && isComplaintClearTooYoung(item, serverNow())) return
  if (!item.action?.trim()) {
    if (status === 'Clear') {
      // Clear tanpa Action → popup khusus isi Action (bukan form Edit penuh).
      emit('clear-needs-action', item.id)
      return
    }
    if (status === 'Menunggu Konfirmasi') {
      emit('need-action', item.id)
      return
    }
  }
  statusPending.value = `${item.id}:${status}`
  if (statusPendingTimer) clearTimeout(statusPendingTimer)
  // Failsafe: kalau API gagal/tidak merespons, kunci dilepas otomatis.
  statusPendingTimer = setTimeout(clearStatusPending, 6000)
  if (status === 'Clear') {
    emit('mark-clear', { type: 'complaint', id: item.id })
  } else {
    emit('set-status', { type: 'complaint', id: item.id, status })
  }
}

// Status kartu hanya berubah setelah respons sukses → lepas kunci pending.
watch(
  () => props.items.map((i) => `${i.id}:${i.status}`).join('|'),
  () => {
    if (!statusPending.value) return
    const sep = statusPending.value.indexOf(':')
    const id = Number(statusPending.value.slice(0, sep))
    const target = statusPending.value.slice(sep + 1)
    const item = props.items.find((i) => i.id === id)
    if (!item || item.status === target) clearStatusPending()
  },
)

onUnmounted(clearStatusPending)

async function openHistory(item: DailyEntryItem) {
  historyTitle.value = item.customer_name?.trim()
    ? `Riwayat · ${item.customer_name}`
    : 'Riwayat Komplain'
  historyItems.value = []
  historyTotal.value = 0
  historySummary.value = null
  historyOpen.value = true
  historyLoading.value = true

  try {
    const res = await dailyEntryApi.complaintHistory({
      customer_id: item.customer_id || undefined,
      customer_code: !item.customer_id && item.customer_code ? item.customer_code : undefined,
      phone: !item.customer_id && !item.customer_code && item.phone_normalized
        ? item.phone_normalized
        : undefined,
      name: !item.customer_id && !item.customer_code && !item.phone_normalized && item.customer_name
        ? item.customer_name
        : undefined,
      days: historyDays,
    })
    historyItems.value = res.data.items
    historyTotal.value = res.data.total
    historySummary.value = res.data.summary ?? null
  } catch {
    historyItems.value = []
    historyTotal.value = 0
    historySummary.value = null
  } finally {
    historyLoading.value = false
  }
}

function onDocClick() {
  closeMenu()
}

onMounted(() => document.addEventListener('click', onDocClick))
onUnmounted(() => document.removeEventListener('click', onDocClick))

watch(
  () => todayItems.value.length,
  () => {
    if (pageToday.value > totalPagesToday.value) pageToday.value = totalPagesToday.value
  },
)

watch(
  () => openItems.value.length,
  () => {
    if (pageOpen.value > totalPagesOpen.value) pageOpen.value = totalPagesOpen.value
  },
)
</script>

<template>
  <div class="grid gap-6 xl:grid-cols-2">
    <!-- Hari ini / rentang filter -->
    <div class="overflow-hidden rounded-[18px] border border-border bg-card card-shadow">
      <div class="flex flex-col gap-3 border-b border-border px-4 py-4 sm:flex-row sm:items-start sm:justify-between sm:gap-4 sm:px-6 sm:py-5">
        <div class="flex min-w-0 items-start gap-3">
          <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
            <ClipboardList class="h-5 w-5" />
          </div>
          <div class="min-w-0">
            <h2 class="text-base font-semibold text-foreground">
              Data Komplain
              <span class="font-medium text-muted">({{ todayItems.length }})</span>
            </h2>
            <p class="mt-0.5 text-sm text-muted">
              Komplain pada rentang tanggal
              <span v-if="clearTodayCount" class="text-success"> · {{ clearTodayCount }} Clear</span>
            </p>
          </div>
        </div>
        <Button type="button" class="w-full shrink-0 sm:w-auto" @click="emit('add')">
          <Plus class="h-4 w-4" />
          Tambah Komplain
        </Button>
      </div>

      <div class="max-h-[calc(100vh-22rem)] space-y-3 overflow-y-auto p-3 sm:p-5">
        <div
          v-for="(item, index) in pagedToday"
          :key="item.id"
          class="relative flex rounded-2xl border border-border bg-white shadow-sm transition hover:shadow-md dark:bg-slate-900/40"
        >
          <div :class="['w-1.5 shrink-0 rounded-l-2xl', accentFor(item, index).stripe]" />
          <div class="flex min-w-0 flex-1 gap-3 px-3 py-3 sm:gap-4 sm:px-5 sm:py-4">
            <div
              :class="[
                'hidden h-12 w-12 shrink-0 items-center justify-center rounded-full sm:flex',
                accentFor(item, index).iconBg,
                accentFor(item, index).iconText,
              ]"
            >
              <component :is="iconFor(item.problem)" class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1 space-y-1.5">
                  <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                    <button
                      type="button"
                      class="max-w-full truncate text-left text-sm font-semibold text-foreground transition hover:text-primary hover:underline sm:text-base"
                      :title="'Lihat riwayat komplain'"
                      @click.stop="openHistory(item)"
                    >
                      {{ item.customer_name || '—' }}
                    </button>
                    <button
                      v-if="(item.complaint_count_90d ?? 0) >= 1"
                      type="button"
                      class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium transition hover:ring-2 hover:ring-primary/20"
                      :class="complaintCountBadgeClass(item.complaint_count_90d ?? 0)"
                      :title="`${item.complaint_count_90d} komplain dalam 90 hari — klik untuk riwayat`"
                      @click.stop="openHistory(item)"
                    >
                      {{ item.complaint_count_90d }}× / 90h
                    </button>
                    <span
                      v-if="isClear(item.status) && item.performance_category"
                      :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium', performanceClass(item.performance_category)]"
                      :title="`Durasi penanganan ${durationLabel(item) || '—'}`"
                    >
                      <Timer class="h-3 w-3" />
                      {{ item.performance_category }}<template v-if="durationLabel(item)"> · {{ durationLabel(item) }}</template>
                    </span>
                    <button
                      v-if="!isClear(item.status) && (item.parent_complaint_id || item.is_repeat)"
                      type="button"
                      class="inline-flex items-center gap-1 rounded-full bg-slate-200/80 px-2 py-0.5 text-[11px] font-medium text-slate-700 transition hover:ring-2 hover:ring-primary/20 dark:bg-slate-700/80 dark:text-slate-200"
                      :title="`Komplain berulang — terkait komplain #${item.parent_complaint_id ?? '-'}`"
                      @click.stop="openHistory(item)"
                    >
                      <RotateCcw class="h-3 w-3" />
                      Berulang
                    </button>
                    <span
                      v-if="isClear(item.status) && isClearedFromPreviousDay(item)"
                      class="inline-flex items-center rounded-full bg-success/10 px-2 py-0.5 text-[11px] font-medium text-success"
                      :title="'Laporan dari ' + formatDate(item.report_date)"
                    >
                      dari {{ formatDateShort(item.report_date) }}
                    </span>
                    <template v-else-if="isWaiting(item)">
                      <span
                        class="inline-flex items-center gap-1 rounded-full bg-info/10 px-2 py-0.5 text-[11px] font-medium text-info"
                        :title="`Selesai ditangani ${formatTime(item.handled_at)} — menunggu konfirmasi pelanggan`"
                      >
                        <Timer class="h-3 w-3" />
                        Menunggu Konfirmasi · auto-clear {{ waitingLeft(item) ? formatDuration(waitingLeft(item)) : 'segera' }}
                      </span>
                    </template>
                  </div>
                  <div
                    class="flex flex-wrap items-center gap-1.5 pt-0.5"
                    role="group"
                    aria-label="Status komplain"
                    @click.stop
                  >
                    <span
                      v-for="s in statusPills"
                      :key="s.status"
                      class="inline-flex"
                      :title="statusPillTitle(item, s.status)"
                    >
                      <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                        :class="item.status === s.status ? s.active : 'border border-border text-muted hover:border-foreground/40 hover:text-foreground'"
                        :disabled="isStatusBusy(item) || isComplaintLocked(item) || (s.status === 'Clear' && isComplaintClearTooYoung(item, clearTick))"
                        :aria-pressed="item.status === s.status"
                        @click.stop="onStatusPill(item, s.status)"
                      >
                        <Lock v-if="isComplaintLocked(item)" class="h-3 w-3" />
                        {{ s.label }}
                      </button>
                    </span>
                    <span
                      v-if="isComplaintClearTooYoung(item, clearTick)"
                      class="inline-flex items-center gap-1 rounded-full bg-warning/10 px-2 py-0.5 text-[11px] font-medium text-warning"
                      :title="statusPillTitle(item, 'Clear')"
                    >
                      <Timer class="h-3 w-3" />
                      {{ clearHint(item) }}
                    </span>
                  </div>
                  <p class="break-words text-sm text-muted">{{ locationLine(item) }}</p>
                  <p :class="['break-words text-sm font-medium', accentFor(item, index).problem]">
                    {{ item.problem || '—' }}
                  </p>
                  <p class="break-words text-xs text-muted">{{ metaLine(item) }}</p>
                  <div class="flex flex-wrap items-center gap-x-3 gap-y-1 pt-0.5 text-xs text-muted sm:text-sm">
                    <span class="inline-flex items-center gap-1.5">
                      <Calendar class="h-3.5 w-3.5 shrink-0" />
                      {{ formatDate(item.start_problem || item.report_date) }}
                    </span>
                    <span class="inline-flex items-center gap-1.5" title="Jam open (input)">
                      <Clock class="h-3.5 w-3.5 shrink-0" />
                      Open {{ formatTime(item.created_at) }}
                    </span>
                    <span class="inline-flex items-center gap-1.5" title="Jam close (clear)">
                      <Check class="h-3.5 w-3.5 shrink-0" />
                      Close {{ formatTime(item.cleared_at) }}
                    </span>
                  </div>
                </div>
                <div class="shrink-0" @click.stop>
                  <button
                    type="button"
                    class="rounded-lg p-2 text-muted transition hover:bg-muted hover:text-foreground"
                    @click="toggleMenu(item.id, $event)"
                  >
                    <MoreVertical class="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <p v-if="!todayItems.length" class="py-10 text-center text-sm text-muted">
          Belum ada komplain pada rentang tanggal ini.
        </p>
      </div>

      <div
        v-if="todayItems.length"
        class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-4"
      >
        <p class="text-sm text-muted">{{ rangeLabel(todayItems, pageToday) }}</p>
        <div class="flex items-center gap-1">
          <button
            type="button"
            class="flex h-9 w-9 items-center justify-center rounded-lg border border-border text-muted transition hover:bg-muted disabled:opacity-40"
            :disabled="pageToday <= 1"
            @click="pageToday -= 1"
          >
            <ChevronLeft class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="flex h-9 min-w-9 items-center justify-center rounded-lg bg-primary px-3 text-sm font-medium text-white"
          >
            {{ pageToday }}
          </button>
          <button
            type="button"
            class="flex h-9 w-9 items-center justify-center rounded-lg border border-border text-muted transition hover:bg-muted disabled:opacity-40"
            :disabled="pageToday >= totalPagesToday"
            @click="pageToday += 1"
          >
            <ChevronRight class="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- On-Progress carryover -->
    <div class="overflow-hidden rounded-[18px] border border-warning/30 bg-card card-shadow">
      <div class="flex flex-col gap-3 border-b border-warning/20 bg-warning/5 px-4 py-4 sm:flex-row sm:items-start sm:justify-between sm:gap-4 sm:px-6 sm:py-5">
        <div class="flex min-w-0 items-start gap-3">
          <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-warning/15 text-warning">
            <Clock class="h-5 w-5" />
          </div>
          <div class="min-w-0">
            <h2 class="text-base font-semibold text-foreground">
              On-Progress
              <span class="font-medium text-muted">({{ openItems.length }})</span>
            </h2>
            <p class="mt-0.5 text-sm text-muted">Belum clear dari hari sebelumnya — tetap ditampilkan sampai Clear.</p>
          </div>
        </div>
      </div>

      <div class="max-h-[calc(100vh-22rem)] space-y-3 overflow-y-auto p-3 sm:p-5">
        <div
          v-for="(item, index) in pagedOpen"
          :key="item.id"
          class="relative flex rounded-2xl border border-border bg-white shadow-sm transition hover:shadow-md dark:bg-slate-900/40"
        >
          <div :class="['w-1.5 shrink-0 rounded-l-2xl', accentFor(item, index).stripe]" />
          <div class="flex min-w-0 flex-1 gap-3 px-3 py-3 sm:gap-4 sm:px-5 sm:py-4">
            <div
              :class="[
                'hidden h-12 w-12 shrink-0 items-center justify-center rounded-full sm:flex',
                accentFor(item, index).iconBg,
                accentFor(item, index).iconText,
              ]"
            >
              <component :is="iconFor(item.problem)" class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1 space-y-1.5">
                  <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                    <button
                      type="button"
                      class="max-w-full truncate text-left text-sm font-semibold text-foreground transition hover:text-primary hover:underline sm:text-base"
                      :title="'Lihat riwayat komplain'"
                      @click.stop="openHistory(item)"
                    >
                      {{ item.customer_name || '—' }}
                    </button>
                    <button
                      v-if="(item.complaint_count_90d ?? 0) >= 1"
                      type="button"
                      class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium transition hover:ring-2 hover:ring-primary/20"
                      :class="complaintCountBadgeClass(item.complaint_count_90d ?? 0)"
                      :title="`${item.complaint_count_90d} komplain dalam 90 hari — klik untuk riwayat`"
                      @click.stop="openHistory(item)"
                    >
                      {{ item.complaint_count_90d }}× / 90h
                    </button>
                    <button
                      v-if="!isClear(item.status) && (item.parent_complaint_id || item.is_repeat)"
                      type="button"
                      class="inline-flex items-center gap-1 rounded-full bg-slate-200/80 px-2 py-0.5 text-[11px] font-medium text-slate-700 transition hover:ring-2 hover:ring-primary/20 dark:bg-slate-700/80 dark:text-slate-200"
                      :title="`Komplain berulang — terkait komplain #${item.parent_complaint_id ?? '-'}`"
                      @click.stop="openHistory(item)"
                    >
                      <RotateCcw class="h-3 w-3" />
                      Berulang
                    </button>
                    <template v-if="isWaiting(item)">
                      <span
                        class="inline-flex items-center gap-1 rounded-full bg-info/10 px-2 py-0.5 text-[11px] font-medium text-info"
                        :title="`Selesai ditangani ${formatTime(item.handled_at)} — menunggu konfirmasi pelanggan`"
                      >
                        <Timer class="h-3 w-3" />
                        Menunggu Konfirmasi · auto-clear {{ waitingLeft(item) ? formatDuration(waitingLeft(item)) : 'segera' }}
                      </span>
                    </template>
                    <span
                      class="inline-flex items-center rounded-full bg-slate-200/80 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-700/80 dark:text-slate-200"
                      :title="`Masih open sejak ${formatDate(item.report_date)}`"
                    >
                      Open dari {{ formatDateShort(item.report_date) }}
                    </span>
                  </div>
                  <div
                    class="flex flex-wrap items-center gap-1.5 pt-0.5"
                    role="group"
                    aria-label="Status komplain"
                    @click.stop
                  >
                    <span
                      v-for="s in statusPills"
                      :key="s.status"
                      class="inline-flex"
                      :title="statusPillTitle(item, s.status)"
                    >
                      <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                        :class="item.status === s.status ? s.active : 'border border-border text-muted hover:border-foreground/40 hover:text-foreground'"
                        :disabled="isStatusBusy(item) || isComplaintLocked(item) || (s.status === 'Clear' && isComplaintClearTooYoung(item, clearTick))"
                        :aria-pressed="item.status === s.status"
                        @click.stop="onStatusPill(item, s.status)"
                      >
                        <Lock v-if="isComplaintLocked(item)" class="h-3 w-3" />
                        {{ s.label }}
                      </button>
                    </span>
                    <span
                      v-if="isComplaintClearTooYoung(item, clearTick)"
                      class="inline-flex items-center gap-1 rounded-full bg-warning/10 px-2 py-0.5 text-[11px] font-medium text-warning"
                      :title="statusPillTitle(item, 'Clear')"
                    >
                      <Timer class="h-3 w-3" />
                      {{ clearHint(item) }}
                    </span>
                  </div>
                  <p class="break-words text-sm text-muted">{{ locationLine(item) }}</p>
                  <p :class="['break-words text-sm font-medium', accentFor(item, index).problem]">
                    {{ item.problem || '—' }}
                  </p>
                  <p class="break-words text-xs text-muted">{{ metaLine(item) }}</p>
                  <div class="flex flex-wrap items-center gap-x-3 gap-y-1 pt-0.5 text-xs text-muted sm:text-sm">
                    <span class="inline-flex items-center gap-1.5">
                      <Calendar class="h-3.5 w-3.5 shrink-0" />
                      {{ formatDate(item.start_problem || item.report_date) }}
                    </span>
                    <span class="inline-flex items-center gap-1.5" title="Jam open (input)">
                      <Clock class="h-3.5 w-3.5 shrink-0" />
                      Open {{ formatTime(item.created_at) }}
                    </span>
                    <span class="inline-flex items-center gap-1.5" title="Jam close (clear)">
                      <Check class="h-3.5 w-3.5 shrink-0" />
                      Close {{ formatTime(item.cleared_at) }}
                    </span>
                  </div>
                </div>
                <div class="shrink-0" @click.stop>
                  <button
                    type="button"
                    class="rounded-lg p-2 text-muted transition hover:bg-muted hover:text-foreground"
                    @click="toggleMenu(item.id, $event)"
                  >
                    <MoreVertical class="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <p v-if="!openItems.length" class="py-10 text-center text-sm text-muted">
          Tidak ada komplain On-Progress dari hari sebelumnya.
        </p>
      </div>

      <div
        v-if="openItems.length"
        class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-4"
      >
        <p class="text-sm text-muted">{{ rangeLabel(openItems, pageOpen) }}</p>
        <div class="flex items-center gap-1">
          <button
            type="button"
            class="flex h-9 w-9 items-center justify-center rounded-lg border border-border text-muted transition hover:bg-muted disabled:opacity-40"
            :disabled="pageOpen <= 1"
            @click="pageOpen -= 1"
          >
            <ChevronLeft class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="flex h-9 min-w-9 items-center justify-center rounded-lg bg-warning px-3 text-sm font-medium text-white"
          >
            {{ pageOpen }}
          </button>
          <button
            type="button"
            class="flex h-9 w-9 items-center justify-center rounded-lg border border-border text-muted transition hover:bg-muted disabled:opacity-40"
            :disabled="pageOpen >= totalPagesOpen"
            @click="pageOpen += 1"
          >
            <ChevronRight class="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="menuItem"
        class="fixed z-[80] w-48 overflow-hidden rounded-xl border border-border bg-card py-1 shadow-lg"
        :style="{ top: `${menuPos.top}px`, left: `${menuPos.left}px` }"
        @click.stop
      >
        <div :title="isComplaintLocked(menuItem) ? 'Hanya pembuat komplain yang dapat mengedit komplain.' : undefined">
          <button
            type="button"
            class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm text-foreground transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="isComplaintLocked(menuItem)"
            @click="onEdit(menuItem)"
          >
            <Lock v-if="isComplaintLocked(menuItem)" class="h-4 w-4 text-muted" />
            <Pencil v-else class="h-4 w-4 text-muted" />
            Edit Komplain
          </button>
        </div>
        <div :title="isComplaintLocked(menuItem) ? 'Hanya pembuat komplain yang dapat menghapus komplain.' : undefined">
          <button
            type="button"
            class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm text-danger transition hover:bg-danger/5 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="isComplaintLocked(menuItem)"
            @click="onDelete(menuItem)"
          >
            <Lock v-if="isComplaintLocked(menuItem)" class="h-4 w-4 text-danger" />
            <Trash2 v-else class="h-4 w-4" />
            Hapus Komplain
          </button>
        </div>
      </div>
    </Teleport>

    <ComplaintHistoryPanel
      v-model:open="historyOpen"
      :title="historyTitle"
      :loading="historyLoading"
      :items="historyItems"
      :total="historyTotal"
      :summary="historySummary"
      :days="historyDays"
    />
  </div>
</template>

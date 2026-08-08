<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Calendar, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import { formatDateId, parseDateInput, todayInput, toDateKey } from '@/lib/date-input'

const props = withDefaults(
  defineProps<{
    from?: string
    to?: string
    label?: string
    class?: string
    placeholder?: string
  }>(),
  {
    from: '',
    to: '',
    label: 'Periode',
    placeholder: 'Pilih periode',
  },
)

const emit = defineEmits<{
  'update:from': [value: string]
  'update:to': [value: string]
}>()

const open = ref(false)
const rootEl = ref<HTMLElement | null>(null)
const viewMonth = ref(new Date())
const pickingStart = ref(true)
const hoverKey = ref<string | null>(null)
const draftFrom = ref('')
const draftTo = ref('')

const MONTHS_ID = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]
const WEEKDAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']

const displayText = computed(() => {
  if (!props.from && !props.to) return ''
  const start = props.from || props.to
  const end = props.to || props.from
  if (!start || !end) return ''
  return `${formatDateId(start)} - ${formatDateId(end)}`
})

const monthLabel = computed(() => {
  return `${MONTHS_ID[viewMonth.value.getMonth()]} ${viewMonth.value.getFullYear()}`
})

type DayCell = {
  key: string
  day: number
  inMonth: boolean
  date: Date
}

const calendarDays = computed((): DayCell[] => {
  const year = viewMonth.value.getFullYear()
  const month = viewMonth.value.getMonth()
  const first = new Date(year, month, 1)
  // Monday-first: Mon=0 ... Sun=6
  const startPad = (first.getDay() + 6) % 7
  const daysInMonth = new Date(year, month + 1, 0).getDate()
  const prevDays = new Date(year, month, 0).getDate()

  const cells: DayCell[] = []

  for (let i = startPad - 1; i >= 0; i--) {
    const d = new Date(year, month - 1, prevDays - i)
    cells.push({ key: toDateKey(d), day: d.getDate(), inMonth: false, date: d })
  }
  for (let day = 1; day <= daysInMonth; day++) {
    const d = new Date(year, month, day)
    cells.push({ key: toDateKey(d), day, inMonth: true, date: d })
  }
  while (cells.length % 7 !== 0 || cells.length < 42) {
    const last = cells[cells.length - 1].date
    const d = new Date(last.getFullYear(), last.getMonth(), last.getDate() + 1)
    cells.push({ key: toDateKey(d), day: d.getDate(), inMonth: false, date: d })
  }

  return cells
})

const rangeStart = computed(() => {
  if (open.value) return draftFrom.value
  return props.from || ''
})

const rangeEnd = computed(() => {
  if (open.value) {
    if (draftFrom.value && !draftTo.value && hoverKey.value) return hoverKey.value
    return draftTo.value || (draftFrom.value && !pickingStart.value ? draftFrom.value : '') || ''
  }
  return props.to || ''
})

function normalizedRange(a: string, b: string): { from: string; to: string } {
  if (!a && !b) return { from: '', to: '' }
  if (a && !b) return { from: a, to: a }
  if (!a && b) return { from: b, to: b }
  return a <= b ? { from: a, to: b } : { from: b, to: a }
}

function isInRange(key: string): boolean {
  const { from, to } = normalizedRange(rangeStart.value, rangeEnd.value)
  if (!from || !to) return false
  return key >= from && key <= to
}

function isStart(key: string): boolean {
  const { from } = normalizedRange(rangeStart.value, rangeEnd.value)
  return !!from && key === from
}

function isEnd(key: string): boolean {
  const { to } = normalizedRange(rangeStart.value, rangeEnd.value)
  return !!to && key === to
}

function isToday(key: string): boolean {
  return key === todayInput()
}

function dayClass(cell: DayCell): string {
  const inRange = isInRange(cell.key)
  const start = isStart(cell.key)
  const end = isEnd(cell.key)
  const single = start && end

  return cn(
    'relative flex h-9 w-full items-center justify-center text-sm transition-colors',
    !cell.inMonth && 'text-muted/40',
    cell.inMonth && !inRange && 'text-foreground hover:bg-muted/80',
    inRange && !single && 'bg-primary/15 text-primary',
    single && 'bg-transparent',
    start && !single && 'rounded-l-full',
    end && !single && 'rounded-r-full',
    single && 'rounded-full',
  )
}

function dayInnerClass(cell: DayCell): string {
  const start = isStart(cell.key)
  const end = isEnd(cell.key)
  if (!start && !end) {
    return cn(
      'flex h-9 w-9 items-center justify-center rounded-full',
      isToday(cell.key) && cell.inMonth && !isInRange(cell.key) && 'ring-1 ring-primary/40',
    )
  }
  return 'flex h-9 w-9 items-center justify-center rounded-full bg-primary text-white font-semibold shadow-sm'
}

function openPicker() {
  draftFrom.value = props.from
  draftTo.value = props.to
  pickingStart.value = true
  hoverKey.value = null
  const anchor = parseDateInput(props.from || props.to || todayInput())
  viewMonth.value = new Date(anchor.getFullYear(), anchor.getMonth(), 1)
  open.value = true
}

function closePicker() {
  open.value = false
  pickingStart.value = true
  hoverKey.value = null
  draftFrom.value = ''
  draftTo.value = ''
}

function selectDay(cell: DayCell) {
  const key = cell.key
  if (pickingStart.value || !draftFrom.value) {
    draftFrom.value = key
    draftTo.value = ''
    pickingStart.value = false
    return
  }

  const range = normalizedRange(draftFrom.value, key)
  emit('update:from', range.from)
  emit('update:to', range.to)
  closePicker()
}

function onDayHover(cell: DayCell) {
  if (!pickingStart.value && draftFrom.value && !draftTo.value) {
    hoverKey.value = cell.key
  }
}

function prevMonth() {
  viewMonth.value = new Date(viewMonth.value.getFullYear(), viewMonth.value.getMonth() - 1, 1)
}

function nextMonth() {
  viewMonth.value = new Date(viewMonth.value.getFullYear(), viewMonth.value.getMonth() + 1, 1)
}

function clearRange(e: Event) {
  e.stopPropagation()
  emit('update:from', '')
  emit('update:to', '')
  closePicker()
}

function onDocPointerDown(e: PointerEvent) {
  if (!open.value || !rootEl.value) return
  if (!rootEl.value.contains(e.target as Node)) {
    // If user selected only start, commit same-day range
    if (draftFrom.value && !draftTo.value) {
      emit('update:from', draftFrom.value)
      emit('update:to', draftFrom.value)
    }
    closePicker()
  }
}

watch(
  () => [props.from, props.to],
  () => {
    if (!open.value) return
    draftFrom.value = props.from
    draftTo.value = props.to
  },
)

onMounted(() => document.addEventListener('pointerdown', onDocPointerDown))
onUnmounted(() => document.removeEventListener('pointerdown', onDocPointerDown))
</script>

<template>
  <div ref="rootEl" :class="cn('relative', props.class)">
    <label v-if="label" class="mb-1.5 block text-xs font-medium text-muted">{{ label }}</label>
    <button
      type="button"
      class="form-control relative flex h-10 w-full items-center gap-2 px-3 pr-10 text-left"
      :aria-expanded="open"
      @click="open ? closePicker() : openPicker()"
    >
      <span :class="displayText ? 'text-foreground' : 'text-muted'">
        {{ displayText || placeholder }}
      </span>
      <Calendar class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
    </button>

    <div
      v-if="open"
      class="absolute left-0 z-50 mt-2 w-[18.5rem] rounded-2xl border border-border bg-card p-3 shadow-lg"
    >
      <div class="mb-3 flex items-center justify-between gap-2">
        <button
          type="button"
          class="rounded-lg p-1.5 text-muted hover:bg-muted hover:text-foreground"
          aria-label="Bulan sebelumnya"
          @click="prevMonth"
        >
          <ChevronLeft class="h-4 w-4" />
        </button>
        <p class="text-sm font-semibold text-foreground">{{ monthLabel }}</p>
        <button
          type="button"
          class="rounded-lg p-1.5 text-muted hover:bg-muted hover:text-foreground"
          aria-label="Bulan berikutnya"
          @click="nextMonth"
        >
          <ChevronRight class="h-4 w-4" />
        </button>
      </div>

      <div class="mb-1 grid grid-cols-7 gap-y-0.5">
        <div
          v-for="wd in WEEKDAYS"
          :key="wd"
          class="flex h-8 items-center justify-center text-[11px] font-medium text-muted"
        >
          {{ wd }}
        </div>
      </div>

      <div class="grid grid-cols-7 gap-y-0.5">
        <button
          v-for="cell in calendarDays"
          :key="cell.key"
          type="button"
          :class="dayClass(cell)"
          @click="selectDay(cell)"
          @mouseenter="onDayHover(cell)"
        >
          <span :class="dayInnerClass(cell)">{{ cell.day }}</span>
        </button>
      </div>

      <div class="mt-3 flex items-center justify-between border-t border-border pt-2">
        <p class="text-[11px] text-muted">
          {{ pickingStart || !draftFrom ? 'Pilih tanggal mulai' : 'Pilih tanggal selesai' }}
        </p>
        <button
          v-if="from || to"
          type="button"
          class="text-[11px] font-medium text-muted hover:text-foreground"
          @click="clearRange"
        >
          Reset
        </button>
      </div>
    </div>
  </div>
</template>

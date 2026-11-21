<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import KpiCard from '@/components/dashboard/KpiCard.vue'
import CustomerAutocomplete from '@/components/daily/CustomerAutocomplete.vue'
import ComplaintDataList from '@/components/daily/ComplaintDataList.vue'
import ComplaintKpiCard from '@/components/daily/ComplaintKpiCard.vue'
import DailyStatusBadge from '@/components/daily/DailyStatusBadge.vue'
import DailyNocAttribution from '@/components/daily/DailyNocAttribution.vue'
import Modal from '@/components/ui/Modal.vue'
import Toast from '@/components/ui/Toast.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import DateRangePicker from '@/components/ui/DateRangePicker.vue'
import SectionReportModal from '@/components/report/SectionReportModal.vue'
import { dailyEntryApi, technicianApi, type DailyEntryData, type DailyEntryItem } from '@/services/api'
import type { ReportSection } from '@/services/api'
import { useDailyEntryPoll, type DailyEntryRealtimeEvent } from '@/composables/useDailyEntryPoll'
import { useAuthStore } from '@/stores/auth'
import { toDateInput, todayInput, formatTimeId } from '@/lib/date-input'
import { parseActivationText } from '@/lib/parse-activation-text'
import { cn, complaintClearRemainingSeconds, isComplaintClearTooYoung } from '@/lib/utils'
import { serverNow } from '@/lib/api'
import { Pencil, Trash2, Plus, Download, FileText, Clock, Check } from 'lucide-vue-next'

const route = useRoute()
const auth = useAuthStore()
const date = ref(todayInput())
const filterFrom = ref(todayInput())
const filterTo = ref(todayInput())
const filterOdc = ref('')
const filterSearch = ref('')
const filterMode = ref('')
const filterUser = ref('')
const filterShift = ref('')
const filterStatus = ref('')
const technicians = ref<{ id: number; name: string }[]>([])
const exporting = ref(false)
const exportingRecap = ref(false)
const reportModalOpen = ref(false)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const toast = ref<{ message: string; variant: 'success' | 'error' } | null>(null)
const deleteTarget = ref<{ type: string; id: number; label: string } | null>(null)
const deleting = ref(false)
const formModalOpen = ref(false)
const activeTab = ref((route.meta.dailyTab as string) || 'activation')
const data = ref<DailyEntryData | null>(null)

const editingActivationId = ref<number | null>(null)
const editingCctvId = ref<number | null>(null)
const editingDismantleId = ref<number | null>(null)
const editingComplaintId = ref<number | null>(null)
const editingNocId = ref<number | null>(null)

const activationForm = ref({
  customer_name: '',
  package_name: '',
  olt_name: '',
  odp_name: '',
  port_onu: '',
  status: 'On-Progress',
  notes: '',
})
const activationPasteText = ref('')
const activationPasteHint = ref('')
const cctvForm = ref({ customer_name: '', router: '', status: 'On-Progress' })
const dismantleForm = ref({
  customer_name: '',
  customer_code: '',
  site_name: '',
  start_ticket: '',
  close_ticket: '',
  status: 'On-Progress',
})

const complaintForm = reactive({
  customer_id: null as number | null,
  customer_code: '',
  odc_name: '',
  customer_name: '',
  phone_normalized: '',
  problem: '',
  action: '',
  start_problem: todayInput(),
  end_problem: '',
  status: 'On-Progress',
  shift: '' as '' | '1' | '2' | '3',
})
const nocForm = ref({ description: '', odc_name: '', status: 'On-Progress', sort_order: 0 })

function filterParams() {
  return {
    from: filterFrom.value || date.value,
    to: filterTo.value || date.value,
    odc_name: filterOdc.value || undefined,
    search: filterSearch.value.trim() || undefined,
    mode: filterMode.value || undefined,
    user_id: filterUser.value ? Number(filterUser.value) : undefined,
    shift: filterShift.value ? Number(filterShift.value) : undefined,
    status: filterStatus.value || undefined,
  }
}

const allTabs = [
  { key: 'activation', label: 'Aktivasi' },
  { key: 'cctv', label: 'CCTV' },
  { key: 'dismantle', label: 'Dismantle' },
  { key: 'complaint', label: 'Komplain' },
  { key: 'noc', label: 'Update NOC' },
]

const allowedTabKeys = computed(() => {
  const fromMeta = route.meta.dailyTabs as string[] | undefined
  if (fromMeta?.length) return fromMeta
  const single = route.meta.dailyTab as string | undefined
  if (single) return [single]
  return allTabs.map((t) => t.key)
})

const tabs = computed(() => allTabs.filter((t) => allowedTabKeys.value.includes(t.key)))

const pageTitle = computed(() => {
  if (route.meta.title && typeof route.meta.title === 'string') return route.meta.title
  if (allowedTabKeys.value.length === 1 && allowedTabKeys.value[0] === 'complaint') return 'Komplain'
  if (allowedTabKeys.value.length === 1 && allowedTabKeys.value[0] === 'noc') return 'Update NOC'
  if (allowedTabKeys.value.includes('activation') && allowedTabKeys.value.includes('cctv')) return 'Aktivasi'
  return 'Input Harian'
})

const pageSubtitle = computed(() => {
  if (route.meta.subtitle && typeof route.meta.subtitle === 'string') return route.meta.subtitle
  if (pageTitle.value === 'Komplain') return 'Input dan pantau komplain harian NOC'
  if (pageTitle.value === 'Update NOC') return 'Input dan pantau update NOC harian'
  if (pageTitle.value === 'Aktivasi') return 'Aktivasi dan setup CCTV harian'
  return 'Laporan operasional harian NOC'
})

const formModalTitle = computed(() => {
  const editing = {
    activation: editingActivationId.value,
    cctv: editingCctvId.value,
    dismantle: editingDismantleId.value,
    complaint: editingComplaintId.value,
    noc: editingNocId.value,
  }[activeTab.value]
  const labels: Record<string, [string, string]> = {
    activation: ['Tambah Aktivasi', 'Edit Aktivasi'],
    cctv: ['Setup CCTV', 'Edit CCTV'],
    dismantle: ['Tambah Dismantle', 'Edit Dismantle'],
    complaint: ['Tambah Komplain', 'Edit Komplain'],
    noc: ['Update NOC', 'Edit Update NOC'],
  }
  const pair = labels[activeTab.value] ?? ['Tambah', 'Edit']
  return editing ? pair[1] : pair[0]
})

const addButtonLabel = computed(() => {
  const labels: Record<string, string> = {
    activation: 'Tambah Aktivasi',
    cctv: 'Tambah CCTV',
    dismantle: 'Tambah Dismantle',
    complaint: 'Tambah Komplain',
    noc: 'Tambah Update NOC',
  }
  return labels[activeTab.value] ?? 'Tambah'
})

const listTitle = computed(() => {
  const labels: Record<string, string> = {
    activation: 'Data Aktivasi',
    cctv: 'Data CCTV',
    dismantle: 'Data Dismantle',
    complaint: 'Data Komplain',
    noc: 'Update NOC',
  }
  return labels[activeTab.value] ?? 'Data'
})

function openCreateForm() {
  if (activeTab.value === 'activation') resetActivationForm(false)
  else if (activeTab.value === 'cctv') resetCctvForm(false)
  else if (activeTab.value === 'dismantle') resetDismantleForm(false)
  else if (activeTab.value === 'complaint') resetComplaintForm(false)
  else if (activeTab.value === 'noc') resetNocForm(false)
  error.value = ''
  formModalOpen.value = true
}

function closeFormModal() {
  formModalOpen.value = false
  if (activeTab.value === 'activation') resetActivationForm(false)
  else if (activeTab.value === 'cctv') resetCctvForm(false)
  else if (activeTab.value === 'dismantle') resetDismantleForm(false)
  else if (activeTab.value === 'complaint') resetComplaintForm(false)
  else if (activeTab.value === 'noc') resetNocForm(false)
}

const showTabBar = computed(() => tabs.value.length > 1)

const isComplaintOnlyPage = computed(
  () => allowedTabKeys.value.length === 1 && allowedTabKeys.value[0] === 'complaint',
)

const isNocOnlyPage = computed(
  () => allowedTabKeys.value.length === 1 && allowedTabKeys.value[0] === 'noc',
)

const showRangeFilter = computed(() =>
  ['complaint', 'noc', 'activation', 'cctv'].includes(activeTab.value),
)

const showOdcFilter = computed(() =>
  ['complaint', 'noc', 'activation', 'cctv'].includes(activeTab.value),
)

const showOdcExportFilter = computed(
  () => activeTab.value === 'complaint' || activeTab.value === 'noc' || activeTab.value === 'activation',
)

const showNameSearch = computed(
  () => activeTab.value === 'activation' || activeTab.value === 'cctv' || activeTab.value === 'complaint',
)

const reportSection = computed((): ReportSection | null => {
  const map: Record<string, ReportSection> = {
    complaint: 'complaint',
    activation: 'activation',
    cctv: 'cctv',
    noc: 'noc',
  }
  return map[activeTab.value] ?? null
})

const canGenerateSection = computed(() => reportSection.value !== null)

watch(
  () => [route.meta.dailyTab, route.meta.dailyTabs, route.path] as const,
  () => {
    const keys = allowedTabKeys.value
    if (!keys.includes(activeTab.value)) {
      activeTab.value = keys[0] ?? 'activation'
    }
  },
  { immediate: true },
)

watch(activeTab, () => {
  if (formModalOpen.value) closeFormModal()
  if (showRangeFilter.value) void refreshFilteredLists()
})

let filterTimeout: ReturnType<typeof setTimeout>
watch([filterFrom, filterTo, filterOdc, filterSearch, filterMode, filterUser, filterShift, filterStatus], () => {
  if (!showRangeFilter.value) return
  clearTimeout(filterTimeout)
  filterTimeout = setTimeout(() => void refreshFilteredLists(), 350)
})

const summary = computed(() => data.value?.summary ?? { activations: 0, complaints: 0, dismantles: 0 })

const complaintTodayCount = computed(() => {
  const from = filterFrom.value || date.value
  const to = filterTo.value || date.value
  return data.value?.complaints.filter((c) => {
    const d = itemDate(c.report_date)
    return Boolean(d && d >= from && d <= to)
  }).length ?? summary.value.complaints
})

const complaintOnProgressCount = computed(
  () => data.value?.complaints.filter((c) => Boolean(c.is_carryover)).length ?? 0,
)

const complaintClearCount = computed(() => {
  const from = filterFrom.value || date.value
  const to = filterTo.value || date.value
  return data.value?.complaints.filter((c) => {
    if (!isStatusClear(c.status)) return false
    const cleared = itemDate(c.cleared_at)
    if (cleared) return cleared >= from && cleared <= to
    const d = itemDate(c.report_date)
    return Boolean(d && d >= from && d <= to)
  }).length ?? 0
})
const statusOptions = computed(() => data.value?.status_options ?? ['On-Progress', 'Clear'])
/** Opsi status khusus form komplain (ada "Menunggu Konfirmasi"). */
const complaintStatusOptions = computed(
  () => data.value?.complaint_status_options ?? ['On-Progress', 'Menunggu Konfirmasi', 'Clear'],
)

const CLEAR_OWNER_MESSAGE = 'Hanya pembuat komplain yang dapat melakukan Clear.'
const STATUS_OWNER_MESSAGE = 'Hanya pembuat komplain yang dapat mengubah status komplain.'
const CLEAR_AGE_MESSAGE = 'Mohon untuk memeriksa komplain minimal 5 menit sebelum di-Clear.'
const CLEAR_TIME_INVALID_MESSAGE = 'Waktu input komplain tidak valid. Komplain belum dapat di-Clear.'
const CLEAR_NEW_MESSAGE = 'Komplain baru tidak dapat langsung di-Clear. Minimal waktu penanganan adalah 5 menit.'

function clearAgeBlockMessage(item: DailyEntryItem, nowMs: number = serverNow()): string | null {
  if (item.status === 'Clear') return null
  if (complaintClearRemainingSeconds(item.created_at, nowMs) === null) return CLEAR_TIME_INVALID_MESSAGE
  return isComplaintClearTooYoung(item, nowMs) ? CLEAR_AGE_MESSAGE : null
}

const clearFormTick = ref(serverNow())
let clearFormTimer: ReturnType<typeof setInterval> | null = null
watch(
  () => formModalOpen.value && activeTab.value === 'complaint',
  (active) => {
    if (active && !clearFormTimer) {
      clearFormTick.value = serverNow()
      clearFormTimer = setInterval(() => {
        clearFormTick.value = serverNow()
      }, 1000)
    } else if (!active && clearFormTimer) {
      clearInterval(clearFormTimer)
      clearFormTimer = null
    }
  },
  { immediate: true },
)
onUnmounted(() => {
  if (clearFormTimer) clearInterval(clearFormTimer)
})

function isComplaintOwner(item: DailyEntryItem): boolean {
  const ownerId = item.created_by
  const userId = auth.user?.id
  return ownerId != null && userId != null && Number(ownerId) === Number(userId)
}

const editingComplaint = computed(
  () => data.value?.complaints.find((c) => c.id === editingComplaintId.value) ?? null,
)
const canChangeStatus = computed(() =>
  !editingComplaintId.value || (editingComplaint.value ? isComplaintOwner(editingComplaint.value) : false),
)
const canSubmitStatusChange = computed(() =>
  canChangeStatus.value || editingComplaint.value?.status === complaintForm.status,
)
const clearFormBlockMessage = computed(() => {
  if (activeTab.value !== 'complaint') return null
  if (!editingComplaintId.value) return CLEAR_NEW_MESSAGE
  return editingComplaint.value ? clearAgeBlockMessage(editingComplaint.value, clearFormTick.value) : null
})

/** Komplain dalam rentang yang sedang menunggu konfirmasi pelanggan (auto-clear 30 mnt). */
const complaintWaitingCount = computed(() => {
  const from = filterFrom.value || date.value
  const to = filterTo.value || date.value
  return data.value?.complaints.filter((c) => {
    if (c.status !== 'Menunggu Konfirmasi') return false
    const d = itemDate(c.report_date)
    return Boolean(d && d >= from && d <= to)
  }).length ?? 0
})
const lookups = computed(() => data.value?.lookups ?? {
  olts: [],
  sites: [],
  odcs: [],
  odps: [],
  routers: [],
  packages: [],
})
const shiftOptions = [
  { value: '1', label: 'Shift 1' },
  { value: '2', label: 'Shift 2' },
  { value: '3', label: 'Shift 3' },
]

function itemDate(value: string | null | undefined) {
  return (value ?? '').slice(0, 10)
}

function isStatusClear(status: string | null | undefined) {
  return (status ?? '').toLowerCase() === 'clear'
}

function withCarryoverFlag(item: DailyEntryItem): DailyEntryItem {
  const actual = itemDate(item.report_date)
  const from = filterFrom.value || date.value
  const isCarryover = Boolean(actual && actual < from && !isStatusClear(item.status))
  return { ...item, is_carryover: isCarryover }
}

function shouldShowOnViewDate(item: DailyEntryItem) {
  const actual = itemDate(item.report_date)
  const from = filterFrom.value || date.value
  const to = filterTo.value || date.value
  if (!actual) return true
  if (actual >= from && actual <= to) return true
  if (actual < from && !isStatusClear(item.status)) return true
  if (isStatusClear(item.status)) {
    const cleared = itemDate(item.cleared_at)
    if (cleared) return cleared >= from && cleared <= to
  }
  return false
}

function reportDateForSave(editingId: number | null, list: DailyEntryItem[] | undefined) {
  if (editingId && list) {
    const found = list.find(i => i.id === editingId)
    const existing = itemDate(found?.report_date)
    if (existing) return existing
  }
  return date.value
}

function recountSummary() {
  if (!data.value) return
  const d = date.value
  data.value.summary = {
    activations: data.value.activations.filter(i => itemDate(i.report_date) === d).length,
    complaints: data.value.complaints.filter(i => itemDate(i.report_date) === d).length,
    dismantles: data.value.dismantles.filter(i => itemDate(i.report_date) === d).length,
  }
}

function formatCarryoverDate(value: string | null | undefined) {
  const raw = itemDate(value)
  if (!raw) return '—'
  const d = new Date(`${raw}T00:00:00`)
  if (Number.isNaN(d.getTime())) return raw
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await dailyEntryApi.index(date.value)
    data.value = res.data
    await refreshFilteredLists()
  } catch {
    error.value = 'Gagal memuat data input harian.'
  } finally {
    loading.value = false
  }
}

async function refreshFilteredLists() {
  if (!data.value) return
  const params = filterParams()

  try {
    if (allowedTabKeys.value.includes('complaint') && (activeTab.value === 'complaint' || isComplaintOnlyPage.value)) {
      const res = await dailyEntryApi.listComplaints(params)
      data.value.complaints = res.data.data
      data.value.sla = res.data.sla
      if (data.value.summary) {
        const from = params.from
        const to = params.to
        const todayCount = res.data.data.filter((c) => {
          const d = (c.report_date ?? '').slice(0, 10)
          return Boolean(d && d >= from && d <= to)
        }).length
        data.value.summary = { ...data.value.summary, complaints: todayCount }
      }
    }
    if (allowedTabKeys.value.includes('noc') && (activeTab.value === 'noc' || isNocOnlyPage.value)) {
      const res = await dailyEntryApi.listNocUpdates(params)
      data.value.noc_updates = res.data.data
    }
    if (allowedTabKeys.value.includes('activation') && activeTab.value === 'activation') {
      const res = await dailyEntryApi.listActivations(params)
      data.value.activations = res.data.data
      if (data.value.summary) {
        data.value.summary = { ...data.value.summary, activations: res.data.data.length }
      }
    }
    if (allowedTabKeys.value.includes('cctv') && activeTab.value === 'cctv') {
      const res = await dailyEntryApi.listCctvSetups(params)
      data.value.cctv_setups = res.data.data
    }
  } catch {
    // keep index data if filter list fails
  }
}

async function exportCurrentTab() {
  exporting.value = true
  error.value = ''
  try {
    const params = filterParams()
    if (activeTab.value === 'complaint') {
      await dailyEntryApi.exportComplaints(params)
    } else if (activeTab.value === 'noc') {
      await dailyEntryApi.exportNocUpdates(params)
    } else if (activeTab.value === 'activation') {
      await dailyEntryApi.exportActivations(params)
    }
  } catch {
    error.value = 'Gagal export Excel.'
  } finally {
    exporting.value = false
  }
}

async function exportComplaintRecap() {
  exportingRecap.value = true
  error.value = ''
  try {
    await dailyEntryApi.exportComplaintsRecap(filterParams())
    showToast('Rekap komplain berhasil diekspor.')
  } catch {
    error.value = 'Gagal export rekap komplain.'
    showToast(error.value, 'error')
  } finally {
    exportingRecap.value = false
  }
}

function changeDate(e: Event) {
  date.value = (e.target as HTMLInputElement).value
  filterFrom.value = date.value
  filterTo.value = date.value
  load()
}

function onComplaintNameTyped() {
  complaintForm.customer_id = null
}

function onComplaintCodeTyped() {
  complaintForm.customer_id = null
}

function onComplaintCustomerSelect(customer: {
  id: number
  name: string
  customer_code?: string
  odc?: { name: string } | null
  phone?: string | null
}) {
  complaintForm.customer_id = customer.id
  complaintForm.customer_code = customer.customer_code ?? ''
  complaintForm.customer_name = customer.name
  if (customer.odc?.name) {
    complaintForm.odc_name = customer.odc.name
  }
  if (customer.phone) {
    complaintForm.phone_normalized = customer.phone
  }
}

// Activation
function resetActivationForm(closeModal = true) {
  editingActivationId.value = null
  activationForm.value = {
    customer_name: '',
    package_name: '',
    olt_name: '',
    odp_name: '',
    port_onu: '',
    status: 'On-Progress',
    notes: '',
  }
  activationPasteText.value = ''
  activationPasteHint.value = ''
  if (closeModal) formModalOpen.value = false
}
function applyActivationPaste() {
  const parsed = parseActivationText(activationPasteText.value)
  const filled: string[] = []
  const warnings: string[] = []
  if (parsed.customer_name) {
    activationForm.value.customer_name = parsed.customer_name
    filled.push('Nama')
  }
  if (parsed.package_name) {
    activationForm.value.package_name = parsed.package_name
    filled.push('Paket')
  }
  if (parsed.olt_name) {
    const resolved = resolveOltFromMaster(parsed.olt_name)
    if (resolved) {
      activationForm.value.olt_name = resolved
      filled.push('OLT')
    } else {
      activationForm.value.olt_name = ''
      warnings.push(`OLT "${parsed.olt_name}" tidak ada di master`)
    }
  }
  if (parsed.odp_name) {
    activationForm.value.odp_name = parsed.odp_name
    filled.push('ODP')
  }
  if (parsed.port_onu) {
    activationForm.value.port_onu = parsed.port_onu
    filled.push('Port')
  }
  if (parsed.status) {
    activationForm.value.status = parsed.status
    filled.push('Status')
  }
  if (!filled.length && !warnings.length) {
    activationPasteHint.value = 'Tidak ada field yang dikenali. Pastikan format Label: nilai.'
    return
  }
  activationPasteHint.value = [
    filled.length ? `Terisi: ${filled.join(', ')}` : '',
    warnings.length ? warnings.join('; ') : '',
  ].filter(Boolean).join(' · ')
  if (filled.length) {
    showToast(`Form diisi dari teks (${filled.length} field).`)
  }
}

/** Cocokkan nama OLT ke master (case-insensitive). */
function resolveOltFromMaster(raw: string): string | null {
  const key = raw.trim().toLowerCase().replace(/\s*\([^)]*\)\s*$/u, '').trim()
  if (!key) return null
  const match = lookups.value.olts.find((o) => {
    const n = String(o.name ?? '').trim().toLowerCase()
    return n === key || n === raw.trim().toLowerCase()
  })
  return match ? String(match.name) : null
}

function openEditActivation(item: DailyEntryItem) {
  editingActivationId.value = item.id
  const resolvedOlt = item.olt_name ? resolveOltFromMaster(item.olt_name) : null
  activationForm.value = {
    customer_name: item.customer_name ?? '',
    package_name: item.package_name ?? '',
    olt_name: resolvedOlt ?? '',
    odp_name: item.odp_name ?? '',
    port_onu: item.port_onu ?? '',
    status: item.status,
    notes: item.notes ?? '',
  }
  activeTab.value = 'activation'
  formModalOpen.value = true
  if (item.olt_name && !resolvedOlt) {
    error.value = `OLT "${item.olt_name}" tidak ada di master — pilih OLT yang valid sebelum simpan.`
  }
}
async function submitActivation() {
  saving.value = true
  error.value = ''
  try {
    if (!activationForm.value.olt_name.trim()) {
      error.value = 'OLT wajib dipilih dari data master.'
      return
    }
    const resolved = resolveOltFromMaster(activationForm.value.olt_name)
    if (!resolved) {
      error.value = 'OLT harus sesuai data master OLT.'
      return
    }
    activationForm.value.olt_name = resolved
    const payload = {
      report_date: reportDateForSave(editingActivationId.value, data.value?.activations),
      ...activationForm.value,
    }
    if (editingActivationId.value) {
      await dailyEntryApi.updateActivation(editingActivationId.value, payload)
    } else {
      await dailyEntryApi.storeActivation(payload)
    }
    resetActivationForm()
    await load()
    showToast('Aktivasi berhasil disimpan.')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    error.value = err.response?.data?.errors?.olt_name?.[0]
      ?? err.response?.data?.errors?.customer_name?.[0]
      ?? err.response?.data?.message ?? 'Gagal menyimpan aktivasi.'
  } finally {
    saving.value = false
  }
}

// CCTV
function resetCctvForm(closeModal = true) {
  editingCctvId.value = null
  cctvForm.value = { customer_name: '', router: '', status: 'On-Progress' }
  if (closeModal) formModalOpen.value = false
}
function openEditCctv(item: DailyEntryItem) {
  editingCctvId.value = item.id
  cctvForm.value = { customer_name: item.customer_name ?? '', router: item.router ?? '', status: item.status }
  activeTab.value = 'cctv'
  formModalOpen.value = true
}
async function submitCctv() {
  saving.value = true
  try {
    const payload = {
      report_date: reportDateForSave(editingCctvId.value, data.value?.cctv_setups),
      ...cctvForm.value,
    }
    if (editingCctvId.value) await dailyEntryApi.updateCctv(editingCctvId.value, payload)
    else await dailyEntryApi.storeCctv(payload)
    resetCctvForm()
    await load()
    showToast('CCTV berhasil disimpan.')
  } finally { saving.value = false }
}

// Dismantle
const dismantleStatusOptions = ['Pending', 'On-Progress', 'Clear']

function resetDismantleForm(closeModal = true) {
  editingDismantleId.value = null
  dismantleForm.value = {
    customer_name: '',
    customer_code: '',
    site_name: '',
    start_ticket: '',
    close_ticket: '',
    status: 'On-Progress',
  }
  if (closeModal) formModalOpen.value = false
}
function openEditDismantle(item: DailyEntryItem) {
  editingDismantleId.value = item.id
  dismantleForm.value = {
    customer_name: item.customer_name ?? '',
    customer_code: item.customer_code ?? '',
    site_name: item.site_name ?? '',
    start_ticket: toDateInput(item.start_ticket),
    close_ticket: toDateInput(item.close_ticket),
    status: item.status,
  }
  activeTab.value = 'dismantle'
  formModalOpen.value = true
}
async function submitDismantle() {
  saving.value = true
  try {
    const payload = {
      report_date: reportDateForSave(editingDismantleId.value, data.value?.dismantles),
      ...dismantleForm.value,
    }
    if (editingDismantleId.value) await dailyEntryApi.updateDismantle(editingDismantleId.value, payload)
    else await dailyEntryApi.storeDismantle(payload)
    resetDismantleForm()
    await load()
    showToast('Dismantle berhasil disimpan.')
  } finally { saving.value = false }
}

// Complaint
let pollPausedForEdit = false

function resetComplaintForm(closeModal = true) {
  editingComplaintId.value = null
  Object.assign(complaintForm, {
    customer_id: null,
    customer_code: '',
    odc_name: '',
    customer_name: '',
    phone_normalized: '',
    problem: '',
    action: '',
    start_problem: todayInput(),
    end_problem: '',
    status: 'On-Progress',
    shift: '',
  })
  if (closeModal) formModalOpen.value = false
  if (pollPausedForEdit) {
    startPoll()
    pollPausedForEdit = false
  }
}
function openEditComplaint(item: DailyEntryItem) {
  if (!isComplaintOwner(item)) {
    error.value = 'Hanya pembuat komplain yang dapat mengedit komplain.'
    return
  }
  stopPoll()
  pollPausedForEdit = true
  editingComplaintId.value = item.id
  Object.assign(complaintForm, {
    customer_id: item.customer_id ?? null,
    customer_code: item.customer_code ?? '',
    odc_name: item.odc_name ?? '',
    customer_name: item.customer_name ?? '',
    phone_normalized: item.phone_normalized ?? '',
    problem: item.problem ?? '',
    action: item.action ?? '',
    start_problem: toDateInput(item.start_problem),
    end_problem: toDateInput(item.end_problem),
    status: item.status,
    shift: item.shift ? String(item.shift) as '1' | '2' | '3' : '',
  })
  activeTab.value = 'complaint'
  formModalOpen.value = true
}
function upsertComplaintLocally(item: DailyEntryItem) {
  if (!data.value) return
  const normalized = withCarryoverFlag({
    ...item,
    start_problem: item.start_problem ? toDateInput(item.start_problem) : (item.start_problem ?? null),
    end_problem: item.end_problem ? toDateInput(item.end_problem) : (item.end_problem ?? null),
  })
  const idx = data.value.complaints.findIndex(c => c.id === normalized.id)

  if (!shouldShowOnViewDate(normalized)) {
    if (idx !== -1) data.value.complaints.splice(idx, 1)
    recountSummary()
    return
  }

  if (idx === -1) {
    data.value.complaints.unshift(normalized)
  } else {
    const prev = data.value.complaints[idx]
    data.value.complaints[idx] = {
      ...prev,
      ...normalized,
    }
  }
  recountSummary()
}

function onComplaintUpdated(item: DailyEntryItem) {
  upsertComplaintLocally(item)
  showToast('Komplain berhasil diperbarui.')
}

function onNeedActionComplaint(id: number) {
  const item = data.value?.complaints.find(c => c.id === id)
  if (item) openEditComplaint(item)
  error.value = 'Action/perbaikan wajib diisi sebelum status Menunggu Konfirmasi / Clear.'
}

// Popup khusus: tombol Clear ditekan sementara Action masih kosong
// (tanpa membuka form Edit Komplain secara keseluruhan).
const actionClearOpen = ref(false)
const actionClearItem = ref<DailyEntryItem | null>(null)
const actionClearText = ref('')
const actionClearSaving = ref(false)
const actionClearError = ref('')

function openActionClear(id: number) {
  const item = data.value?.complaints.find(c => c.id === id)
  if (!item || !isComplaintOwner(item)) {
    error.value = CLEAR_OWNER_MESSAGE
    return
  }
  const ageBlock = clearAgeBlockMessage(item)
  if (ageBlock) {
    error.value = ageBlock
    return
  }
  actionClearItem.value = item
  actionClearText.value = item.action ?? ''
  actionClearError.value = ''
  actionClearOpen.value = true
}

function closeActionClear() {
  if (actionClearSaving.value) return
  actionClearOpen.value = false
  actionClearItem.value = null
  actionClearText.value = ''
  actionClearError.value = ''
}

/** Satu request: simpan Action + status Clear. Backend menjalankan assertComplaintActionForClear,
 *  syncClearAttribution, syncHandling, end_problem default, realtime & activity log. */
async function submitActionClear() {
  const item = actionClearItem.value
  if (!item) return
  if (!isComplaintOwner(item)) {
    actionClearError.value = CLEAR_OWNER_MESSAGE
    return
  }
  const ageBlock = clearAgeBlockMessage(item)
  if (ageBlock) {
    actionClearError.value = ageBlock
    return
  }
  const action = actionClearText.value.trim()
  if (!action) {
    actionClearError.value = 'Action wajib diisi.'
    return
  }
  actionClearSaving.value = true
  actionClearError.value = ''
  try {
    const res = await dailyEntryApi.updateComplaint(item.id, {
      // Payload membawa nilai field saat ini dari kartu — tidak ada penimpaan null/yang tidak disengaja.
      report_date: item.report_date,
      customer_id: item.customer_id ?? null,
      customer_code: item.customer_code ?? '',
      customer_name: item.customer_name ?? '',
      phone_normalized: item.phone_normalized ?? null,
      odc_name: item.odc_name ?? '',
      problem: item.problem ?? '',
      shift: item.shift ?? null,
      start_problem: item.start_problem ? toDateInput(item.start_problem) : null,
      end_problem: item.end_problem ? toDateInput(item.end_problem) : null,
      action,
      status: 'Clear',
    })
    upsertComplaintLocally(res.data.data as DailyEntryItem)
    actionClearOpen.value = false
    actionClearItem.value = null
    actionClearText.value = ''
    showToast('Komplain berhasil di-Clear.')
  } catch (e: unknown) {
    // Gagal → status kartu tidak diubah; popup tetap terbuka + notifikasi error.
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    actionClearError.value = err.response?.data?.errors?.action?.[0]
      ?? Object.values(err.response?.data?.errors ?? {}).flat()[0]
      ?? err.response?.data?.message
      ?? 'Gagal menyimpan Action & status Clear.'
    showToast(actionClearError.value, 'error')
  } finally {
    actionClearSaving.value = false
  }
}

async function submitComplaint() {
  if (!canSubmitStatusChange.value) {
    error.value = STATUS_OWNER_MESSAGE
    return
  }
  if (
    (complaintForm.status === 'Clear' || complaintForm.status === 'Menunggu Konfirmasi')
    && !complaintForm.action?.trim()
  ) {
    error.value = `Action/perbaikan wajib diisi sebelum status ${complaintForm.status}.`
    return
  }
  if (!editingComplaintId.value && complaintForm.status === 'Clear') {
    error.value = CLEAR_NEW_MESSAGE
    return
  }
  if (editingComplaintId.value && complaintForm.status === 'Clear' && editingComplaint.value) {
    const ageBlock = clearAgeBlockMessage(editingComplaint.value)
    if (ageBlock) {
      error.value = ageBlock
      return
    }
  }
  if (!complaintForm.customer_code.trim() || !complaintForm.customer_name.trim()) {
    error.value = 'Komplain wajib isi Nama dan ID Pelanggan.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    const payload = {
      report_date: reportDateForSave(editingComplaintId.value, data.value?.complaints),
      ...complaintForm,
      shift: complaintForm.shift ? Number(complaintForm.shift) : null,
      start_problem: complaintForm.start_problem || null,
      end_problem: complaintForm.end_problem || null,
    }
    if (editingComplaintId.value) {
      const res = await dailyEntryApi.updateComplaint(editingComplaintId.value, payload)
      onComplaintUpdated(res.data.data)
    } else {
      const res = await dailyEntryApi.storeComplaint(payload)
      upsertComplaintLocally(res.data.data)
      showToast('Komplain berhasil ditambahkan.')
    }
    resetComplaintForm()
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const first = err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat()[0]
      : undefined
    error.value = first ?? err.response?.data?.message ?? 'Gagal menyimpan komplain.'
  } finally {
    saving.value = false
  }
}

// NOC
function resetNocForm(closeModal = true) {
  editingNocId.value = null
  nocForm.value = { description: '', odc_name: '', status: 'On-Progress', sort_order: 0 }
  if (closeModal) formModalOpen.value = false
}
function openEditNoc(item: DailyEntryItem) {
  editingNocId.value = item.id
  nocForm.value = {
    description: item.description ?? '',
    odc_name: item.odc_name ?? '',
    status: item.status,
    sort_order: item.sort_order ?? 0,
  }
  activeTab.value = 'noc'
  formModalOpen.value = true
}
async function submitNoc() {
  saving.value = true
  try {
    const payload = {
      report_date: reportDateForSave(editingNocId.value, data.value?.noc_updates),
      ...nocForm.value,
    }
    if (editingNocId.value) await dailyEntryApi.updateNocUpdate(editingNocId.value, payload)
    else await dailyEntryApi.storeNocUpdate(payload)
    resetNocForm()
    await load()
  } finally { saving.value = false }
}

const deleteTypeLabels: Record<string, string> = {
  activation: 'aktivasi',
  cctv: 'CCTV',
  dismantle: 'dismantle',
  complaint: 'komplain',
  'noc-update': 'update NOC',
}

const dataKeyByType: Record<string, keyof Pick<DailyEntryData, 'activations' | 'cctv_setups' | 'dismantles' | 'complaints' | 'noc_updates'>> = {
  activation: 'activations',
  cctv: 'cctv_setups',
  dismantle: 'dismantles',
  complaint: 'complaints',
  'noc-update': 'noc_updates',
}

function showToast(message: string, variant: 'success' | 'error' = 'success') {
  toast.value = { message, variant }
}

function requestDelete(type: string, id: number, label?: string) {
  if (type === 'complaint') {
    const item = data.value?.complaints.find((c) => c.id === id)
    if (!item || !isComplaintOwner(item)) {
      error.value = 'Hanya pembuat komplain yang dapat menghapus komplain.'
      return
    }
  }
  deleteTarget.value = { type, id, label: label?.trim() || 'item ini' }
}

function clearEditingIfDeleted(type: string, id: number) {
  if (type === 'activation' && editingActivationId.value === id) resetActivationForm()
  if (type === 'cctv' && editingCctvId.value === id) resetCctvForm()
  if (type === 'dismantle' && editingDismantleId.value === id) resetDismantleForm()
  if (type === 'complaint' && editingComplaintId.value === id) resetComplaintForm()
  if (type === 'noc-update' && editingNocId.value === id) resetNocForm()
}

function removeItemLocally(type: string, id: number) {
  if (!data.value) return
  const key = dataKeyByType[type]
  if (!key) return
  const list = data.value[key]
  const idx = list.findIndex(item => item.id === id)
  if (idx === -1) return
  list.splice(idx, 1)
  recountSummary()
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  const { type, id } = deleteTarget.value
  deleting.value = true
  try {
    await dailyEntryApi.destroy(type, id)
    removeItemLocally(type, id)
    clearEditingIfDeleted(type, id)
    const label = deleteTypeLabels[type] ?? 'data'
    showToast(`${label.charAt(0).toUpperCase() + label.slice(1)} berhasil dihapus.`)
    deleteTarget.value = null
  } catch {
    showToast('Gagal menghapus data. Coba lagi.', 'error')
  } finally {
    deleting.value = false
  }
}

async function markItemClear(type: string, id: number) {
  await setItemStatus(type, id, 'Clear')
}

async function setItemStatus(type: string, id: number, status: string) {
  if (type === 'complaint' && status === 'Clear') {
    const item = data.value?.complaints.find(c => c.id === id)
    if (item) {
      const ageBlock = clearAgeBlockMessage(item)
      if (ageBlock) {
        error.value = ageBlock
        showToast(ageBlock, 'error')
        return
      }
    }
  }
  try {
    const res = await dailyEntryApi.updateStatus(type, id, status)

    if (type === 'complaint') {
      onComplaintUpdated(res.data.data)
      return
    }

    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    error.value = err.response?.data?.errors?.action?.[0]
      ?? err.response?.data?.errors?.created_at?.[0]
      ?? err.response?.data?.message
      ?? 'Gagal memperbarui status.'
    showToast(error.value, 'error')
  }
}

function handleRealtimeEvent(event: DailyEntryRealtimeEvent) {
  const payload = event.payload
  if (!payload) return

  const alreadyInList = Boolean(data.value?.complaints.some(c => c.id === payload.complaint_id))
  const payloadDate = itemDate(payload.report_date)
  const complaint = payload.complaint
  const from = filterFrom.value || date.value
  const to = filterTo.value || date.value
  const clearedDate = itemDate(complaint?.cleared_at)
  const isInRange = Boolean(payloadDate && payloadDate >= from && payloadDate <= to)
  const isOpenCarryover = Boolean(
    complaint
    && payloadDate
    && payloadDate < from
    && !isStatusClear(complaint.status),
  )
  const isClearedInRange = Boolean(
    complaint
    && isStatusClear(complaint.status)
    && clearedDate
    && clearedDate >= from
    && clearedDate <= to,
  )

  if (payload.action === 'deleted') {
    if (!isInRange && !alreadyInList) return
    if (payload.complaint_id) {
      removeItemLocally('complaint', payload.complaint_id)
      clearEditingIfDeleted('complaint', payload.complaint_id)
    }
    return
  }

  if (!complaint) return
  if (!isInRange && !alreadyInList && !isOpenCarryover && !isClearedInRange) return

  // Jangan timpa data di list saat user sedang mengedit komplain yang sama.
  if (editingComplaintId.value === complaint.id) return

  const isNew = !alreadyInList
  upsertComplaintLocally(complaint)

  if (payload.action === 'created' && isNew && isInRange) {
    showToast(`Komplain baru: ${complaint.customer_name}`)
  }
}

const { connected: pollConnected, start: startPoll, stop: stopPoll } = useDailyEntryPoll(
  date,
  handleRealtimeEvent,
)

function applyRouteQuery() {
  const q = route.query
  if (typeof q.from === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(q.from)) {
    filterFrom.value = q.from
  }
  if (typeof q.to === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(q.to)) {
    filterTo.value = q.to
  }
  if (typeof q.odc_name === 'string') {
    filterOdc.value = q.odc_name
  }
  if (typeof q.mode === 'string' && q.mode.toLowerCase() === 'clear') {
    filterMode.value = 'clear'
  }
  if (typeof q.tab === 'string' && allowedTabKeys.value.includes(q.tab)) {
    activeTab.value = q.tab
  }
}

async function loadTechnicians() {
  try {
    const res = await technicianApi.list()
    technicians.value = res.data.data.map((t) => ({ id: t.id, name: t.name }))
  } catch {
    technicians.value = []
  }
}

onMounted(() => {
  applyRouteQuery()
  void loadTechnicians()
  void load().finally(() => startPoll())
})

onUnmounted(stopPoll)
</script>

<template>
  <AppLayout :title="pageTitle" :subtitle="pageSubtitle">
    <div
      v-if="filterOdc === '__none__'"
      class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-2.5 text-sm text-foreground"
    >
      Menampilkan data <span class="font-semibold">Tanpa ODC</span> (belum ter-map) — revisi OLT/ODP/ODC agar terhitung benar di dashboard.
      <button type="button" class="ml-2 text-xs text-primary underline" @click="filterOdc = ''">Hapus filter</button>
    </div>
    <div
      v-if="filterMode === 'clear'"
      class="mb-4 rounded-xl border border-primary/20 bg-primary/5 px-4 py-2.5 text-sm text-foreground"
    >
      Filter dari dashboard: data <span class="font-semibold">Clear</span> menurut tanggal clear (bukan tanggal buat).
      <button type="button" class="ml-2 text-xs text-primary underline" @click="filterMode = ''">Hapus</button>
    </div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
        <p class="text-sm text-muted">
          Operasional NOC · {{ pageTitle }}
          <span
            v-if="pollConnected"
            class="ml-2 inline-flex items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-xs font-medium text-success"
            title="Terhubung ke update realtime"
          >
            <span class="h-1.5 w-1.5 rounded-full bg-success animate-pulse" />
            Live
          </span>
        </p>
        <div class="flex w-full flex-wrap items-end gap-2 sm:w-auto">
          <template v-if="showRangeFilter">
            <div class="min-w-0 flex-1 sm:flex-none sm:w-64">
              <DateRangePicker v-model:from="filterFrom" v-model:to="filterTo" class="w-full" />
            </div>
            <div v-if="showNameSearch" class="min-w-0 basis-full sm:basis-auto sm:min-w-[12rem]">
              <label class="mb-1 block text-[11px] text-muted">
                {{ activeTab === 'complaint' ? 'Cari nama / ID / problem' : 'Cari nama pelanggan' }}
              </label>
              <SearchInput
                v-model="filterSearch"
                :placeholder="activeTab === 'complaint' ? 'Nama, ID, atau problem...' : 'Nama pelanggan...'"
                class="w-full sm:w-48"
              />
            </div>
            <template v-if="showOdcFilter">
              <div class="min-w-0 flex-1 sm:flex-none">
                <label class="mb-1 block text-[11px] text-muted">ODC / Site</label>
                <Select v-model="filterOdc" class="w-full sm:w-44">
                  <option value="">Semua ODC</option>
                  <option value="__none__">Tanpa ODC</option>
                  <option v-for="o in lookups.odcs" :key="o.id" :value="o.name">{{ o.name }}</option>
                </Select>
              </div>
              <template v-if="activeTab === 'complaint'">
                <div class="min-w-0 flex-1 sm:flex-none">
                  <label class="mb-1 block text-[11px] text-muted">NOC</label>
                  <Select v-model="filterUser" class="w-full sm:w-40">
                    <option value="">Semua NOC</option>
                    <option v-for="t in technicians" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                  </Select>
                </div>
                <div class="min-w-0 flex-1 sm:flex-none">
                  <label class="mb-1 block text-[11px] text-muted">Shift</label>
                  <Select v-model="filterShift" class="w-full sm:w-28">
                    <option value="">Semua shift</option>
                    <option v-for="s in shiftOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                  </Select>
                </div>
                <div class="min-w-0 flex-1 sm:flex-none">
                  <label class="mb-1 block text-[11px] text-muted">Status</label>
                  <Select v-model="filterStatus" class="w-full sm:w-40">
                    <option value="">Semua status</option>
                    <option v-for="s in complaintStatusOptions" :key="s" :value="s">{{ s }}</option>
                  </Select>
                </div>
              </template>
              <Button
                v-if="showOdcExportFilter"
                variant="outline"
                class="w-full sm:w-auto"
                :disabled="exporting"
                @click="exportCurrentTab"
              >
                <Download class="h-4 w-4" /> {{ exporting ? 'Export...' : 'Excel' }}
              </Button>
              <Button
                v-if="activeTab === 'complaint'"
                variant="outline"
                class="w-full sm:w-auto"
                :disabled="exportingRecap"
                @click="exportComplaintRecap"
              >
                <Download class="h-4 w-4" /> {{ exportingRecap ? 'Export...' : 'Rekap NOC/Shift' }}
              </Button>
            </template>
          </template>
          <Button
            v-if="canGenerateSection"
            variant="outline"
            class="w-full sm:w-auto"
            @click="reportModalOpen = true"
          >
            <FileText class="h-4 w-4" /> Generate
          </Button>
          <div>
            <label class="mb-1 block text-[11px] text-muted">{{ showRangeFilter ? 'Hari input' : 'Tanggal' }}</label>
            <input type="date" :value="date" class="form-control h-10 w-auto px-4" @change="changeDate" />
          </div>
        </div>
      </div>

      <div v-if="error && !formModalOpen" class="mb-4 rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">{{ error }}</div>

      <div v-if="isComplaintOnlyPage" class="mb-6">
        <ComplaintKpiCard
          :today="complaintTodayCount"
          :on-progress="complaintOnProgressCount"
          :waiting="complaintWaitingCount"
          :clear="complaintClearCount"
          :sla="data?.sla"
        />
      </div>
      <div v-else-if="!isNocOnlyPage" class="mb-6 grid gap-4 md:grid-cols-3">
        <KpiCard v-if="allowedTabKeys.includes('activation')" label="Aktivasi" :value="summary.activations" icon="activation" color="success" />
        <KpiCard v-if="allowedTabKeys.includes('complaint')" label="Komplain" :value="summary.complaints" icon="ticket" color="danger" />
        <KpiCard v-if="allowedTabKeys.includes('cctv')" label="CCTV" :value="data?.cctv_setups.length ?? 0" icon="onu" color="primary" />
        <KpiCard v-if="allowedTabKeys.includes('dismantle')" label="Dismantle" :value="summary.dismantles" icon="dismantle" color="warning" />
      </div>

      <div v-if="showTabBar" class="mb-6 flex gap-2 overflow-x-auto pb-1">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          :class="cn(
            'shrink-0 rounded-xl px-4 py-2 text-sm font-medium transition',
            activeTab === tab.key
              ? 'bg-primary text-white shadow-sm'
              : 'border border-border bg-card text-foreground hover:bg-muted',
          )"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>

      <div v-if="loading" class="py-12 text-center text-sm text-muted">Memuat data...</div>

      <template v-else-if="data">
        <ComplaintDataList
          v-if="activeTab === 'complaint'"
          :items="data.complaints"
          @add="openCreateForm"
          @edit="openEditComplaint"
          @delete="(item) => requestDelete('complaint', item.id, item.customer_name)"
          @need-action="onNeedActionComplaint"
          @clear-needs-action="openActionClear"
          @mark-clear="({ type, id }) => markItemClear(type, id)"
          @set-status="({ type, id, status }) => setItemStatus(type, id, status)"
        />

        <Card v-else>
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-foreground">
              {{ listTitle }}
              <span class="font-normal text-muted">
                ({{
                  activeTab === 'activation' ? data.activations.length
                  : activeTab === 'cctv' ? data.cctv_setups.length
                  : activeTab === 'dismantle' ? data.dismantles.length
                  : data.noc_updates.length
                }})
              </span>
            </h2>
            <Button type="button" @click="openCreateForm">
              <Plus class="h-4 w-4" /> {{ addButtonLabel }}
            </Button>
          </div>

          <!-- Aktivasi -->
          <div v-show="activeTab === 'activation'" class="max-h-[calc(100vh-16rem)] space-y-3 overflow-y-auto pr-1">
            <div v-for="item in data.activations" :key="item.id" class="rounded-xl border border-border bg-slate-50 p-4 dark:bg-slate-800/50">
              <div class="flex justify-between gap-2">
                <div class="min-w-0 flex flex-wrap items-center gap-2">
                  <p class="font-medium text-foreground">{{ item.customer_name }}</p>
                  <span
                    v-if="item.is_carryover"
                    class="inline-flex items-center rounded-full bg-slate-200/80 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-700/80 dark:text-slate-200"
                  >
                    Open dari {{ formatCarryoverDate(item.report_date) }}
                  </span>
                </div>
                <div class="flex gap-1">
                  <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-muted" @click="openEditActivation(item)"><Pencil class="h-4 w-4" /></button>
                  <button type="button" class="rounded-lg p-1.5 text-danger hover:bg-danger/10" @click="requestDelete('activation', item.id, item.customer_name)"><Trash2 class="h-4 w-4" /></button>
                </div>
              </div>
              <p class="text-sm text-muted">
                Paket: {{ item.package_name || '—' }}
                · OLT: {{ item.olt_name || '—' }}
                · ODP: {{ item.odp_name || '—' }}
                · Port: {{ item.port_onu || '—' }}
              </p>
              <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                <span class="inline-flex items-center gap-1" title="Jam open (input)">
                  <Clock class="h-3.5 w-3.5 shrink-0" /> Open {{ formatTimeId(item.created_at) || '—' }}
                </span>
                <span class="inline-flex items-center gap-1" title="Jam close (clear)">
                  <Check class="h-3.5 w-3.5 shrink-0" /> Close {{ formatTimeId(item.cleared_at) || '—' }}
                </span>
              </p>
              <DailyNocAttribution :creator-name="item.creator_name" :clearer-name="item.clearer_name" :status="item.status" />
              <DailyStatusBadge :status="item.status" type="activation" :id="item.id" @mark-clear="({ type, id }) => markItemClear(type, id)" />
            </div>
            <p v-if="!data.activations.length" class="py-8 text-center text-sm text-muted">Belum ada data aktivasi.</p>
          </div>

          <!-- CCTV -->
          <div v-show="activeTab === 'cctv'" class="max-h-[calc(100vh-16rem)] space-y-3 overflow-y-auto pr-1">
            <div v-for="item in data.cctv_setups" :key="item.id" class="rounded-xl border border-border bg-slate-50 p-4 dark:bg-slate-800/50">
              <div class="flex justify-between">
                <div class="min-w-0 flex flex-wrap items-center gap-2">
                  <p class="font-medium text-foreground">{{ item.customer_name || '—' }} · {{ item.router || '—' }}</p>
                  <span
                    v-if="item.is_carryover"
                    class="inline-flex items-center rounded-full bg-slate-200/80 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-700/80 dark:text-slate-200"
                  >
                    Open dari {{ formatCarryoverDate(item.report_date) }}
                  </span>
                </div>
                <div class="flex gap-1">
                  <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-muted" @click="openEditCctv(item)"><Pencil class="h-4 w-4" /></button>
                  <button type="button" class="rounded-lg p-1.5 text-danger hover:bg-danger/10" @click="requestDelete('cctv', item.id, item.customer_name ?? undefined)"><Trash2 class="h-4 w-4" /></button>
                </div>
              </div>
              <DailyNocAttribution :creator-name="item.creator_name" :clearer-name="item.clearer_name" :status="item.status" />
              <DailyStatusBadge :status="item.status" type="cctv" :id="item.id" @mark-clear="({ type, id }) => markItemClear(type, id)" />
            </div>
            <p v-if="!data.cctv_setups.length" class="py-8 text-center text-sm text-muted">Belum ada data CCTV.</p>
          </div>

          <!-- Dismantle (tab harian, jika ada) -->
          <div v-show="activeTab === 'dismantle'" class="max-h-[calc(100vh-16rem)] space-y-3 overflow-y-auto pr-1">
            <div v-for="item in data.dismantles" :key="item.id" class="rounded-xl border border-border bg-slate-50 p-4 dark:bg-slate-800/50">
              <div class="flex justify-between">
                <div class="min-w-0 flex flex-wrap items-center gap-2">
                  <p class="font-medium text-foreground">{{ item.customer_name }}</p>
                  <span
                    v-if="item.is_carryover"
                    class="inline-flex items-center rounded-full bg-slate-200/80 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-700/80 dark:text-slate-200"
                  >
                    Open dari {{ formatCarryoverDate(item.report_date) }}
                  </span>
                </div>
                <div class="flex gap-1">
                  <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-muted" @click="openEditDismantle(item)"><Pencil class="h-4 w-4" /></button>
                  <button type="button" class="rounded-lg p-1.5 text-danger hover:bg-danger/10" @click="requestDelete('dismantle', item.id, item.customer_name)"><Trash2 class="h-4 w-4" /></button>
                </div>
              </div>
              <p class="text-sm text-muted">{{ item.site_name || '—' }} · {{ item.customer_code || '—' }}</p>
              <DailyNocAttribution :creator-name="item.creator_name" :clearer-name="item.clearer_name" :status="item.status" />
              <DailyStatusBadge :status="item.status" type="dismantle" :id="item.id" @mark-clear="({ type, id }) => markItemClear(type, id)" />
            </div>
            <p v-if="!data.dismantles.length" class="py-8 text-center text-sm text-muted">Belum ada data dismantle.</p>
          </div>

          <!-- NOC Update -->
          <div v-show="activeTab === 'noc'" class="max-h-[calc(100vh-16rem)] space-y-3 overflow-y-auto pr-1">
            <div v-for="item in data.noc_updates" :key="item.id" class="rounded-xl border border-border bg-slate-50 p-4 dark:bg-slate-800/50">
              <div class="flex justify-between gap-2">
                <div class="min-w-0 flex flex-wrap items-center gap-2">
                  <p class="font-medium text-foreground">* {{ item.description }}</p>
                  <span
                    v-if="item.odc_name"
                    class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary"
                  >
                    {{ item.odc_name }}
                  </span>
                  <span
                    v-if="item.is_carryover"
                    class="inline-flex items-center rounded-full bg-slate-200/80 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-700/80 dark:text-slate-200"
                  >
                    Open dari {{ formatCarryoverDate(item.report_date) }}
                  </span>
                </div>
                <div class="flex gap-1 shrink-0">
                  <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-muted" @click="openEditNoc(item)"><Pencil class="h-4 w-4" /></button>
                  <button type="button" class="rounded-lg p-1.5 text-danger hover:bg-danger/10" @click="requestDelete('noc-update', item.id, item.description)"><Trash2 class="h-4 w-4" /></button>
                </div>
              </div>
              <DailyNocAttribution :creator-name="item.creator_name" :clearer-name="item.clearer_name" :status="item.status" />
              <DailyStatusBadge :status="item.status" type="noc-update" :id="item.id" @mark-clear="({ type, id }) => markItemClear(type, id)" />
            </div>
            <p v-if="!data.noc_updates.length" class="py-8 text-center text-sm text-muted">Belum ada update NOC.</p>
          </div>
        </Card>
      </template>

    <Modal
      :open="formModalOpen"
      :title="formModalTitle"
      size="lg"
      @close="closeFormModal"
    >
      <div v-if="error" class="mb-4 rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">{{ error }}</div>

      <form v-if="activeTab === 'activation'" class="max-h-[70vh] space-y-4 overflow-y-auto pr-1" @submit.prevent="submitActivation">
        <div class="rounded-xl border border-border bg-muted/30 p-3">
          <label class="mb-1.5 block text-sm font-medium text-foreground">Paste teks aktivasi</label>
          <Textarea
            v-model="activationPasteText"
            :rows="5"
            placeholder="Nama Pelanggan: ...&#10;ODP : ...&#10;Kapasitas: ...&#10;OLT: ...&#10;Port | ONU: ...&#10;Status: Clear"
          />
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <Button type="button" variant="secondary" @click="applyActivationPaste">Isi form dari teks</Button>
            <button
              v-if="activationPasteText"
              type="button"
              class="text-xs text-muted hover:text-foreground"
              @click="activationPasteText = ''; activationPasteHint = ''"
            >
              Hapus teks
            </button>
          </div>
          <p v-if="activationPasteHint" class="mt-1.5 text-xs text-muted">{{ activationPasteHint }}</p>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Nama Pelanggan</label>
          <Input v-model="activationForm.customer_name" required />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Paket Pelanggan</label>
          <Input
            v-model="activationForm.package_name"
            list="activation-package-suggestions"
            placeholder="Contoh: 15Mb / 50 Mbps"
          />
          <datalist id="activation-package-suggestions">
            <option v-for="p in lookups.packages" :key="p.id" :value="p.name" />
          </datalist>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">OLT <span class="text-danger">*</span></label>
          <Select v-model="activationForm.olt_name" required>
            <option value="">Pilih OLT dari master</option>
            <option v-for="o in lookups.olts" :key="o.id" :value="o.name">
              {{ o.name }}{{ o.pop_name ? ` · ${o.pop_name}` : '' }}
            </option>
          </Select>
          <p v-if="lookups.olts.length === 0" class="mt-1 text-xs text-danger">
            Belum ada data OLT — tambahkan dulu di menu Jaringan → OLT.
          </p>
          <p v-else class="mt-1 text-xs text-muted">
            Wajib sesuai master OLT agar terhitung di Performa ODC.
          </p>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">ODP</label>
          <Input
            v-model="activationForm.odp_name"
            list="activation-odp-suggestions"
            placeholder="Contoh: ODP-TUBAN-01"
          />
          <datalist id="activation-odp-suggestions">
            <option
              v-for="o in lookups.odps"
              :key="o.id"
              :value="o.name"
            />
          </datalist>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Port | ONU</label>
          <Input v-model="activationForm.port_onu" placeholder="1/2/16:5" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Status</label>
          <Select v-model="activationForm.status">
            <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
          </Select>
        </div>
        <div class="flex gap-2">
          <Button type="submit" :disabled="saving">{{ editingActivationId ? 'Simpan Perubahan' : 'Simpan Aktivasi' }}</Button>
          <Button type="button" variant="outline" @click="closeFormModal">Batal</Button>
        </div>
      </form>

      <form v-else-if="activeTab === 'cctv'" class="max-h-[70vh] space-y-4 overflow-y-auto pr-1" @submit.prevent="submitCctv">
        <CustomerAutocomplete v-model="cctvForm.customer_name" />
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Router</label>
          <Select v-model="cctvForm.router">
            <option value="">— Pilih Router —</option>
            <option v-for="r in lookups.routers" :key="r" :value="r">{{ r }}</option>
          </Select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Status</label>
          <Select v-model="cctvForm.status">
            <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
          </Select>
        </div>
        <div class="flex gap-2">
          <Button type="submit" :disabled="saving">{{ editingCctvId ? 'Simpan Perubahan' : 'Simpan CCTV' }}</Button>
          <Button type="button" variant="outline" @click="closeFormModal">Batal</Button>
        </div>
      </form>

      <form v-else-if="activeTab === 'dismantle'" class="max-h-[70vh] space-y-4 overflow-y-auto pr-1" @submit.prevent="submitDismantle">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Lokasi</label>
          <Select v-model="dismantleForm.site_name">
            <option value="">— Pilih Lokasi / Site —</option>
            <option v-for="s in lookups.sites" :key="s" :value="s">{{ s }}</option>
          </Select>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-foreground">ID Pel</label>
            <Input v-model="dismantleForm.customer_code" placeholder="Kode pelanggan" />
          </div>
          <CustomerAutocomplete v-model="dismantleForm.customer_name" label="Nama" required />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-foreground">Open Ticket</label>
            <Input v-model="dismantleForm.start_ticket" type="date" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-foreground">Close Ticket</label>
            <Input v-model="dismantleForm.close_ticket" type="date" />
          </div>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Status Tiket</label>
          <Select v-model="dismantleForm.status">
            <option v-for="s in dismantleStatusOptions" :key="s" :value="s">{{ s }}</option>
          </Select>
        </div>
        <div class="flex gap-2">
          <Button type="submit" :disabled="saving">{{ editingDismantleId ? 'Simpan Perubahan' : 'Simpan Dismantle' }}</Button>
          <Button type="button" variant="outline" @click="closeFormModal">Batal</Button>
        </div>
      </form>

      <form v-else-if="activeTab === 'complaint'" class="max-h-[70vh] space-y-4 overflow-y-auto pr-1" @submit.prevent="submitComplaint">
        <CustomerAutocomplete
          v-model="complaintForm.customer_name"
          label="Pelanggan *"
          required
          @update:model-value="onComplaintNameTyped"
          @select="onComplaintCustomerSelect"
        />
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-foreground">ID Pelanggan *</label>
            <Input
              v-model="complaintForm.customer_code"
              required
              placeholder="Ketik ID pelanggan"
              @update:model-value="onComplaintCodeTyped"
            />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-foreground">ODC</label>
            <Select v-model="complaintForm.odc_name">
              <option value="">— Pilih ODC —</option>
              <option v-for="o in lookups.odcs" :key="o.id" :value="o.name">
                {{ o.name }} ({{ o.code }})
              </option>
            </Select>
          </div>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Shift</label>
          <Select v-model="complaintForm.shift">
            <option value="">— Pilih Shift —</option>
            <option v-for="s in shiftOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
          </Select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Problem</label>
          <Input v-model="complaintForm.problem" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Action (wajib jika Clear)</label>
          <Textarea v-model="complaintForm.action" :rows="2" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-foreground">Start Problem</label>
            <input
              v-model="complaintForm.start_problem"
              type="date"
              class="form-control form-control-date h-10 w-full px-4 py-2"
            />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-foreground">End Problem</label>
            <input
              v-model="complaintForm.end_problem"
              type="date"
              class="form-control form-control-date h-10 w-full px-4 py-2"
            />
          </div>
        </div>
        <div :title="canChangeStatus ? undefined : STATUS_OWNER_MESSAGE">
          <label class="mb-1.5 block text-sm font-medium text-foreground">Status</label>
          <Select v-model="complaintForm.status" :disabled="!canChangeStatus">
            <option
              v-for="s in complaintStatusOptions"
              :key="s"
              :value="s"
              :disabled="s === 'Clear' && Boolean(clearFormBlockMessage)"
            >{{ s }}</option>
          </Select>
          <p v-if="!canChangeStatus" class="mt-1 text-[11px] text-muted">{{ STATUS_OWNER_MESSAGE }}</p>
          <p v-else-if="clearFormBlockMessage" class="mt-1 text-[11px] text-muted">{{ clearFormBlockMessage }}</p>
        </div>
        <div class="flex gap-2">
          <Button type="submit" :disabled="saving">{{ editingComplaintId ? 'Simpan Perubahan' : 'Simpan Komplain' }}</Button>
          <Button type="button" variant="outline" @click="closeFormModal">Batal</Button>
        </div>
      </form>

      <form v-else-if="activeTab === 'noc'" class="max-h-[70vh] space-y-4 overflow-y-auto pr-1" @submit.prevent="submitNoc">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Deskripsi</label>
          <Textarea v-model="nocForm.description" required />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">ODC / Site</label>
          <Select v-model="nocForm.odc_name">
            <option value="">— Pilih ODC —</option>
            <option v-for="o in lookups.odcs" :key="o.id" :value="o.name">{{ o.name }}</option>
          </Select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Status</label>
          <Select v-model="nocForm.status">
            <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
          </Select>
        </div>
        <div class="flex gap-2">
          <Button type="submit" :disabled="saving">{{ editingNocId ? 'Simpan Perubahan' : 'Simpan Update' }}</Button>
          <Button type="button" variant="outline" @click="closeFormModal">Batal</Button>
        </div>
      </form>
    </Modal>

    <Modal
      :open="!!deleteTarget"
      title="Hapus data?"
      :subtitle="deleteTarget ? `Yakin ingin menghapus ${deleteTarget.label}? Tindakan ini tidak bisa dibatalkan.` : undefined"
      size="sm"
      @close="deleteTarget = null"
    >
      <p class="text-sm text-muted">
        Data akan dihapus dari laporan tanggal {{ date }}.
      </p>
      <template #footer>
        <Button type="button" variant="outline" :disabled="deleting" @click="deleteTarget = null">Batal</Button>
        <Button type="button" variant="danger" :disabled="deleting" @click="confirmDelete">
          {{ deleting ? 'Menghapus...' : 'Hapus' }}
        </Button>
      </template>
    </Modal>

    <Modal
      :open="actionClearOpen"
      title="Action Wajib Diisi"
      size="sm"
      @close="closeActionClear"
    >
      <p class="text-sm text-muted">
        Silakan isi tindakan penanganan sebelum mengubah status komplain menjadi Clear.
      </p>
      <div class="mt-4">
        <label for="action-clear-textarea" class="mb-1.5 block text-sm font-medium">
          Action
        </label>
        <Textarea
          id="action-clear-textarea"
          v-model="actionClearText"
          :rows="3"
          placeholder="Contoh: Reconfig DNS & Soft Reboot"
          :disabled="actionClearSaving"
        />
      </div>
      <p v-if="actionClearError" class="mt-3 text-sm text-danger">{{ actionClearError }}</p>
      <template #footer>
        <Button type="button" variant="outline" :disabled="actionClearSaving" @click="closeActionClear">
          Batal
        </Button>
        <Button type="button" :disabled="actionClearSaving" @click="submitActionClear">
          {{ actionClearSaving ? 'Menyimpan...' : 'Simpan & Clear' }}
        </Button>
      </template>
    </Modal>

    <SectionReportModal
      v-if="reportSection"
      v-model:open="reportModalOpen"
      :section="reportSection"
      :from="filterFrom || date"
      :to="filterTo || filterFrom || date"
    />

    <Toast
      v-if="toast"
      :message="toast.message"
      :variant="toast.variant"
      @dismiss="toast = null"
    />
  </AppLayout>
</template>

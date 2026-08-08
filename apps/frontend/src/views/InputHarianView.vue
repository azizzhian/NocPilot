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
import { dailyEntryApi, type DailyEntryData, type DailyEntryItem } from '@/services/api'
import type { ReportSection } from '@/services/api'
import { useDailyEntryPoll, type DailyEntryRealtimeEvent } from '@/composables/useDailyEntryPoll'
import { toDateInput, todayInput } from '@/lib/date-input'
import { parseActivationText } from '@/lib/parse-activation-text'
import { cn } from '@/lib/utils'
import { Pencil, Trash2, Plus, Download, FileText } from 'lucide-vue-next'

const route = useRoute()
const date = ref(todayInput())
const filterFrom = ref(todayInput())
const filterTo = ref(todayInput())
const filterOdc = ref('')
const filterSearch = ref('')
const exporting = ref(false)
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
  complaint_type: 'individual' as 'individual' | 'gamas',
  customer_id: null as number | null,
  customer_code: '',
  gamas_kind: 'odp' as 'odp' | 'upstream' | 'olt' | 'other',
  location_label: '',
  impact: '',
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
const gamasKindOptions = [
  { value: 'odp', label: 'ODP / Jalur' },
  { value: 'upstream', label: 'Upstream' },
  { value: 'olt', label: 'OLT / Site' },
  { value: 'other', label: 'Lainnya' },
] as const
const nocForm = ref({ description: '', odc_name: '', status: 'On-Progress', sort_order: 0 })

function filterParams() {
  return {
    from: filterFrom.value || date.value,
    to: filterTo.value || date.value,
    odc_name: filterOdc.value || undefined,
    search: filterSearch.value.trim() || undefined,
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

const showOdcExportFilter = computed(
  () => activeTab.value === 'complaint' || activeTab.value === 'noc',
)

const showNameSearch = computed(
  () => activeTab.value === 'activation' || activeTab.value === 'cctv',
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
watch([filterFrom, filterTo, filterOdc, filterSearch], () => {
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
    }
  } catch {
    error.value = 'Gagal export Excel.'
  } finally {
    exporting.value = false
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

function setComplaintType(type: 'individual' | 'gamas') {
  complaintForm.complaint_type = type
  if (type === 'gamas') {
    complaintForm.customer_id = null
    complaintForm.customer_code = ''
    complaintForm.customer_name = ''
    complaintForm.phone_normalized = ''
  } else {
    complaintForm.gamas_kind = 'odp'
    complaintForm.location_label = ''
    complaintForm.impact = ''
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
  if (parsed.customer_name) {
    activationForm.value.customer_name = parsed.customer_name
    filled.push('Nama')
  }
  if (parsed.package_name) {
    activationForm.value.package_name = parsed.package_name
    filled.push('Paket')
  }
  if (parsed.olt_name) {
    activationForm.value.olt_name = parsed.olt_name
    filled.push('OLT')
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
  if (!filled.length) {
    activationPasteHint.value = 'Tidak ada field yang dikenali. Pastikan format Label: nilai.'
    return
  }
  activationPasteHint.value = `Terisi: ${filled.join(', ')}`
  showToast(`Form diisi dari teks (${filled.length} field).`)
}
function openEditActivation(item: DailyEntryItem) {
  editingActivationId.value = item.id
  activationForm.value = {
    customer_name: item.customer_name ?? '',
    package_name: item.package_name ?? '',
    olt_name: item.olt_name ?? '',
    odp_name: item.odp_name ?? '',
    port_onu: item.port_onu ?? '',
    status: item.status,
    notes: item.notes ?? '',
  }
  activeTab.value = 'activation'
  formModalOpen.value = true
}
async function submitActivation() {
  saving.value = true
  error.value = ''
  try {
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
    error.value = err.response?.data?.errors?.customer_name?.[0]
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
    complaint_type: 'individual',
    customer_id: null,
    customer_code: '',
    gamas_kind: 'odp',
    location_label: '',
    impact: '',
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
  stopPoll()
  pollPausedForEdit = true
  editingComplaintId.value = item.id
  Object.assign(complaintForm, {
    complaint_type: (item.complaint_type === 'gamas' ? 'gamas' : 'individual') as 'individual' | 'gamas',
    customer_id: item.customer_id ?? null,
    customer_code: item.customer_code ?? '',
    gamas_kind: (item.gamas_kind as 'odp' | 'upstream' | 'olt' | 'other') || 'odp',
    location_label: item.location_label ?? '',
    impact: item.impact ?? '',
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
  error.value = 'Action/perbaikan wajib diisi sebelum status Clear.'
}

async function submitComplaint() {
  if (complaintForm.status === 'Clear' && !complaintForm.action?.trim()) {
    error.value = 'Action/perbaikan wajib diisi sebelum status Clear.'
    return
  }
  if (complaintForm.complaint_type === 'individual') {
    if (!complaintForm.customer_code.trim() || !complaintForm.customer_name.trim()) {
      error.value = 'Komplain individu wajib isi Nama dan ID Pelanggan.'
      return
    }
  } else if (!complaintForm.location_label.trim()) {
    error.value = 'Lokasi / jalur gamas wajib diisi.'
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
      customer_id: complaintForm.complaint_type === 'individual' ? complaintForm.customer_id : null,
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
  try {
    const res = await dailyEntryApi.updateStatus(type, id, 'Clear')

    if (type === 'complaint') {
      onComplaintUpdated(res.data.data)
      return
    }

    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    error.value = err.response?.data?.errors?.action?.[0]
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
    removeItemLocally('complaint', payload.complaint_id)
    clearEditingIfDeleted('complaint', payload.complaint_id)
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

onMounted(() => {
  void load().finally(() => startPoll())
})

onUnmounted(stopPoll)
</script>

<template>
  <AppLayout :title="pageTitle" :subtitle="pageSubtitle">
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
              <label class="mb-1 block text-[11px] text-muted">Cari nama pelanggan</label>
              <SearchInput v-model="filterSearch" placeholder="Nama pelanggan..." class="w-full sm:w-48" />
            </div>
            <template v-if="showOdcExportFilter">
              <div class="min-w-0 flex-1 sm:flex-none">
                <label class="mb-1 block text-[11px] text-muted">ODC / Site</label>
                <Select v-model="filterOdc" class="w-full sm:w-44">
                  <option value="">Semua ODC</option>
                  <option v-for="o in lookups.odcs" :key="o.id" :value="o.name">{{ o.name }}</option>
                </Select>
              </div>
              <Button variant="outline" class="w-full sm:w-auto" :disabled="exporting" @click="exportCurrentTab">
                <Download class="h-4 w-4" /> {{ exporting ? 'Export...' : 'Excel' }}
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
          :clear="complaintClearCount"
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
          @mark-clear="({ type, id }) => markItemClear(type, id)"
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
          <label class="mb-1.5 block text-sm font-medium text-foreground">OLT</label>
          <Input
            v-model="activationForm.olt_name"
            list="activation-olt-suggestions"
            placeholder="Contoh: Pacitan"
          />
          <datalist id="activation-olt-suggestions">
            <option v-for="o in lookups.olts" :key="o.id" :value="o.name" />
          </datalist>
          <p v-if="lookups.olts.length === 0" class="mt-1 text-xs text-muted">
            Belum ada data OLT — bisa ketik manual atau tambahkan di menu Jaringan → OLT.
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
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Tipe</label>
          <div class="flex gap-2 rounded-xl border border-border p-1">
            <button
              type="button"
              :class="[
                'flex-1 rounded-lg px-3 py-2 text-sm font-medium transition',
                complaintForm.complaint_type === 'individual' ? 'bg-primary text-white' : 'text-muted hover:bg-muted',
              ]"
              @click="setComplaintType('individual')"
            >
              Individu
            </button>
            <button
              type="button"
              :class="[
                'flex-1 rounded-lg px-3 py-2 text-sm font-medium transition',
                complaintForm.complaint_type === 'gamas' ? 'bg-primary text-white' : 'text-muted hover:bg-muted',
              ]"
              @click="setComplaintType('gamas')"
            >
              Gamas
            </button>
          </div>
        </div>

        <template v-if="complaintForm.complaint_type === 'individual'">
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
        </template>

        <template v-else>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-foreground">Jenis Gamas *</label>
            <Select v-model="complaintForm.gamas_kind" class="w-full">
              <option v-for="k in gamasKindOptions" :key="k.value" :value="k.value">{{ k.label }}</option>
            </Select>
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-foreground">Lokasi / Jalur *</label>
            <Input
              v-model="complaintForm.location_label"
              required
              :placeholder="complaintForm.gamas_kind === 'upstream'
                ? 'Contoh: Upstream Jiwan · ether11'
                : complaintForm.gamas_kind === 'odp'
                  ? 'Contoh: ODP Banyumas 1'
                  : 'Lokasi / perangkat'"
            />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-foreground">Impact</label>
              <Input v-model="complaintForm.impact" placeholder="all user / ±10 user" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-foreground">ODC (opsional)</label>
              <Select v-model="complaintForm.odc_name">
                <option value="">— Pilih ODC —</option>
                <option v-for="o in lookups.odcs" :key="o.id" :value="o.name">
                  {{ o.name }} ({{ o.code }})
                </option>
              </Select>
            </div>
          </div>
        </template>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Shift</label>
          <Select v-model="complaintForm.shift">
            <option value="">— Pilih Shift —</option>
            <option v-for="s in shiftOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
          </Select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Problem</label>
          <Input v-model="complaintForm.problem" :placeholder="complaintForm.complaint_type === 'gamas' ? 'LOS / link down / dll' : ''" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Action (wajib jika Clear)</label>
          <Textarea v-model="complaintForm.action" :rows="2" :placeholder="complaintForm.complaint_type === 'gamas' ? 'Koordinasi dengan tim / vendor' : ''" />
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
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Status</label>
          <Select v-model="complaintForm.status">
            <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
          </Select>
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

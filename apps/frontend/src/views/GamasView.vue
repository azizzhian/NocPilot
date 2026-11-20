<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import Select from '@/components/ui/Select.vue'
import DateRangePicker from '@/components/ui/DateRangePicker.vue'
import Modal from '@/components/ui/Modal.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Toast from '@/components/ui/Toast.vue'
import SectionReportModal from '@/components/report/SectionReportModal.vue'
import {
  gamasApi,
  odcApi,
  type DailyGamasItem,
  type GamasStatsResponse,
  type GamasUpdate,
} from '@/services/api'
import { todayInput, formatTimeId } from '@/lib/date-input'
import { useDailyEntryPoll, type DailyEntryRealtimeEvent } from '@/composables/useDailyEntryPoll'
import { Plus, Pencil, Trash2, Download, FileText, CheckCircle2, MessageSquarePlus } from 'lucide-vue-next'

const route = useRoute()
const search = ref('')
const statusFilter = ref('all')
const fromDate = ref(todayInput())
const toDate = ref(todayInput())
const odcName = ref('')
const clearMode = ref(false)
const odcs = ref<{ id: number; name: string }[]>([])
const items = ref<DailyGamasItem[]>([])
const stats = ref<GamasStatsResponse | null>(null)
const loading = ref(true)
const exporting = ref(false)
const showModal = ref(false)
const saving = ref(false)
const editingId = ref<number | null>(null)
const error = ref('')
const deleteTarget = ref<DailyGamasItem | null>(null)
const deleting = ref(false)
const reportModalOpen = ref(false)
const actionClearOpen = ref(false)
const actionClearItem = ref<DailyGamasItem | null>(null)
const actionClearText = ref('')
const actionClearSaving = ref(false)
const actionClearError = ref('')
const updateOpen = ref(false)
const updateItem = ref<DailyGamasItem | null>(null)
const updateText = ref('')
const updates = ref<GamasUpdate[]>([])
const updatesLoading = ref(false)
const updateSaving = ref(false)
const updateError = ref('')
const toast = ref<{ message: string; variant: 'success' | 'error' }>({ message: '', variant: 'success' })

const kindOptions = [
  { value: 'odp', label: 'ODP / Jalur' },
  { value: 'upstream', label: 'Upstream' },
  { value: 'olt', label: 'OLT / Site' },
  { value: 'other', label: 'Lainnya' },
] as const

const form = ref({
  gamas_kind: 'odp',
  location_label: '',
  impact: '',
  odc_name: '',
  problem: '',
  action: '',
  status: 'On-Progress',
  start_problem: todayInput(),
  end_problem: '',
  shift: '' as '' | '1' | '2' | '3',
})

const statusTabs = [
  { key: 'all', label: 'Semua' },
  { key: 'On-Progress', label: 'On-Progress' },
  { key: 'Clear', label: 'Clear' },
]

function kindLabel(kind: string): string {
  return kindOptions.find((k) => k.value === kind)?.label ?? 'Gamas'
}

function statusVariant(status: string) {
  return status === 'Clear' ? 'success' : 'warning'
}

function listParams() {
  return {
    search: search.value || undefined,
    status: clearMode.value ? 'all' : statusFilter.value,
    from: fromDate.value || todayInput(),
    to: toDate.value || fromDate.value || todayInput(),
    odc_name: odcName.value || undefined,
    mode: clearMode.value ? 'clear' : undefined,
  }
}

async function loadOdcs() {
  try {
    const res = await odcApi.list({ per_page: 200 })
    odcs.value = res.data.data.map((o) => ({ id: o.id as number, name: String(o.name ?? '') }))
  } catch {
    odcs.value = []
  }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params = listParams()
    const [listRes, statsRes] = await Promise.all([
      gamasApi.list(params),
      gamasApi.stats({ from: params.from, to: params.to }),
    ])
    items.value = listRes.data.data
    stats.value = statsRes.data
  } catch {
    error.value = 'Gagal memuat data gamas.'
  } finally {
    loading.value = false
  }
}

async function exportExcel() {
  exporting.value = true
  error.value = ''
  try {
    await gamasApi.exportExcel(listParams())
  } catch {
    error.value = 'Gagal export Excel.'
  } finally {
    exporting.value = false
  }
}

function openCreate() {
  editingId.value = null
  form.value = {
    gamas_kind: 'odp',
    location_label: '',
    impact: '',
    odc_name: '',
    problem: '',
    action: '',
    status: 'On-Progress',
    start_problem: todayInput(),
    end_problem: '',
    shift: '',
  }
  showModal.value = true
}

function openEdit(item: DailyGamasItem) {
  editingId.value = item.id
  form.value = {
    gamas_kind: item.gamas_kind || 'odp',
    location_label: item.location_label ?? '',
    impact: item.impact ?? '',
    odc_name: item.odc_name ?? '',
    problem: item.problem ?? '',
    action: item.action ?? '',
    status: item.status,
    start_problem: item.start_problem ?? '',
    end_problem: item.end_problem ?? '',
    shift: item.shift ? (String(item.shift) as '1' | '2' | '3') : '',
  }
  showModal.value = true
}

function extractError(e: unknown, fallback: string): string {
  const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
  const first = err.response?.data?.errors
    ? Object.values(err.response.data.errors).flat()[0]
    : undefined
  return first ?? err.response?.data?.message ?? fallback
}

async function submit() {
  if (!form.value.location_label.trim()) {
    error.value = 'Lokasi / jalur gamas wajib diisi.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    const payload: Record<string, unknown> = {
      ...form.value,
      shift: form.value.shift ? Number(form.value.shift) : null,
      start_problem: form.value.start_problem || null,
      end_problem: form.value.end_problem || null,
      report_date: editingId.value
        ? items.value.find((i) => i.id === editingId.value)?.report_date ?? todayInput()
        : todayInput(),
    }
    if (editingId.value) {
      await gamasApi.update(editingId.value, payload)
    } else {
      await gamasApi.create(payload)
    }
    showModal.value = false
    await load()
  } catch (e: unknown) {
    error.value = extractError(e, 'Gagal menyimpan gamas.')
  } finally {
    saving.value = false
  }
}

async function markClear(item: DailyGamasItem) {
  error.value = ''
  if (!item.action?.trim()) {
    actionClearItem.value = item
    actionClearText.value = ''
    actionClearError.value = ''
    actionClearOpen.value = true
    return
  }
  try {
    await gamasApi.updateStatus(item.id, 'Clear')
    await load()
  } catch (e: unknown) {
    error.value = extractError(e, 'Gagal menandai Clear. Pastikan Action sudah diisi.')
  }
}

function closeActionClear() {
  if (actionClearSaving.value) return
  actionClearOpen.value = false
  actionClearItem.value = null
  actionClearText.value = ''
  actionClearError.value = ''
}

/** Satu request: simpan Action + status Clear lewat endpoint update GAMAS. */
async function submitActionClear() {
  const item = actionClearItem.value
  if (!item) return
  const action = actionClearText.value.trim()
  if (!action) {
    actionClearError.value = 'Action wajib diisi.'
    return
  }
  actionClearSaving.value = true
  actionClearError.value = ''
  try {
    await gamasApi.update(item.id, {
      report_date: item.report_date,
      gamas_kind: item.gamas_kind,
      location_label: item.location_label,
      impact: item.impact ?? null,
      odc_name: item.odc_name ?? null,
      problem: item.problem ?? null,
      shift: item.shift ?? null,
      start_problem: item.start_problem || null,
      end_problem: item.end_problem || null,
      action,
      status: 'Clear',
    })
    actionClearOpen.value = false
    actionClearItem.value = null
    actionClearText.value = ''
    await load()
  } catch (e: unknown) {
    actionClearError.value = extractError(e, 'Gagal menyimpan Action & status Clear.')
  } finally {
    actionClearSaving.value = false
  }
}

function showToast(message: string, variant: 'success' | 'error' = 'success') {
  toast.value = { message, variant }
}

async function loadUpdates(id: number) {
  updatesLoading.value = true
  try {
    const res = await gamasApi.updates(id)
    if (updateOpen.value && updateItem.value?.id === id) updates.value = res.data.data
  } catch (e: unknown) {
    updateError.value = extractError(e, 'Gagal memuat riwayat update.')
  } finally {
    updatesLoading.value = false
  }
}

async function openUpdate(item: DailyGamasItem) {
  updateItem.value = item
  updateText.value = ''
  updateError.value = ''
  updates.value = []
  updateOpen.value = true
  await loadUpdates(item.id)
}

function closeUpdate() {
  if (updateSaving.value) return
  updateOpen.value = false
  updateItem.value = null
  updateText.value = ''
  updateError.value = ''
  updates.value = []
}

function formatUpdateTime(value: string | null | undefined): string {
  if (!value) return '—'
  return new Date(value).toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function submitUpdate() {
  const item = updateItem.value
  if (!item) return
  const text = updateText.value.trim()
  if (!text) {
    updateError.value = 'Update / Progress wajib diisi.'
    return
  }
  updateSaving.value = true
  updateError.value = ''
  try {
    const res = await gamasApi.storeUpdate(item.id, text)
    updates.value.unshift(res.data.data)
    updateText.value = ''
    showToast('Update berhasil disimpan.')
  } catch (e: unknown) {
    updateError.value = extractError(e, 'Gagal menyimpan update.')
    showToast(updateError.value, 'error')
  } finally {
    updateSaving.value = false
  }
}

function requestDelete(item: DailyGamasItem) {
  deleteTarget.value = item
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  error.value = ''
  try {
    await gamasApi.destroy(deleteTarget.value.id)
    deleteTarget.value = null
    await load()
  } catch (e: unknown) {
    error.value = extractError(e, 'Gagal menghapus gamas.')
  } finally {
    deleting.value = false
  }
}

let searchTimeout: ReturnType<typeof setTimeout>
watch([search, statusFilter, fromDate, toDate, odcName, clearMode], () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => load(), 400)
})

function applyRouteQuery() {
  const q = route.query
  if (typeof q.from === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(q.from)) {
    fromDate.value = q.from
  }
  if (typeof q.to === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(q.to)) {
    toDate.value = q.to
  }
  if (typeof q.odc_name === 'string') {
    odcName.value = q.odc_name
  }
  if (typeof q.mode === 'string' && q.mode.toLowerCase() === 'clear') {
    clearMode.value = true
    statusFilter.value = 'all'
  }
}

// Realtime: polling channel 'gamas' (perubahan user lain langsung tampil).
const pollDate = ref(todayInput())
let reloadTimeout: ReturnType<typeof setTimeout> | null = null
function handleRealtimeEvent(_event: DailyEntryRealtimeEvent) {
  if (reloadTimeout) clearTimeout(reloadTimeout)
  reloadTimeout = setTimeout(() => void load(), 300)
}
const { start: startPoll } = useDailyEntryPoll(
  pollDate,
  handleRealtimeEvent,
  gamasApi.events,
)

onMounted(async () => {
  applyRouteQuery()
  await loadOdcs()
  await load()
  startPoll()
})
</script>

<template>
  <AppLayout title="Gamas" subtitle="Gangguan massal: ODP, upstream, OLT/site">
    <div class="mb-6 grid grid-cols-3 gap-3 md:grid-cols-3">
      <Card padding="sm">
        <p class="text-xl font-bold text-primary">{{ stats?.summary.total ?? 0 }}</p>
        <p class="text-xs text-muted">Total periode</p>
      </Card>
      <Card padding="sm">
        <p class="text-xl font-bold text-warning">{{ stats?.summary.open_all ?? 0 }}</p>
        <p class="text-xs text-muted">On-Progress (termasuk backlog)</p>
      </Card>
      <Card padding="sm">
        <p class="text-xl font-bold text-success">{{ stats?.summary.clear ?? 0 }}</p>
        <p class="text-xs text-muted">Clear periode</p>
      </Card>
    </div>

    <div
      v-if="stats && stats.by_kind.some((k) => k.total > 0)"
      class="mb-6 rounded-xl border border-border bg-card px-4 py-3"
    >
      <p class="mb-2 text-xs font-medium text-muted">Rekap per jenis (periode terpilih)</p>
      <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
        <span v-for="k in stats.by_kind" :key="k.kind" class="text-foreground">
          {{ k.label }}
          <span class="text-muted">({{ k.total }})</span>
          <span class="ml-1 text-xs text-muted">open {{ k.open }} · clear {{ k.clear }}</span>
        </span>
      </div>
    </div>

    <div v-if="error && !showModal" class="mb-4 rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">
      {{ error }}
    </div>

    <div
      v-if="odcName === '__none__'"
      class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-2.5 text-sm text-foreground"
    >
      Menampilkan gamas <span class="font-semibold">Tanpa ODC</span> — isi ODC untuk revisi.
      <button type="button" class="ml-2 text-xs text-primary underline" @click="odcName = ''">Hapus filter</button>
    </div>
    <div
      v-if="clearMode"
      class="mb-4 rounded-xl border border-primary/20 bg-primary/5 px-4 py-2.5 text-sm text-foreground"
    >
      Filter dari dashboard: gamas <span class="font-semibold">Clear</span> menurut tanggal clear.
      <button type="button" class="ml-2 text-xs text-primary underline" @click="clearMode = false">Hapus</button>
    </div>

    <div class="mb-4 flex flex-wrap items-end gap-3">
      <SearchInput v-model="search" placeholder="Cari lokasi / problem..." class="max-w-sm" />
      <DateRangePicker v-model:from="fromDate" v-model:to="toDate" class="w-64" />
      <div>
        <label class="mb-1 block text-[11px] text-muted">ODC / Site</label>
        <Select v-model="odcName" class="w-44">
          <option value="">Semua ODC</option>
          <option value="__none__">Tanpa ODC</option>
          <option v-for="o in odcs" :key="o.id" :value="o.name">{{ o.name }}</option>
        </Select>
      </div>
      <div class="flex gap-1 overflow-x-auto">
        <button
          v-for="tab in statusTabs"
          :key="tab.key"
          type="button"
          class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-medium transition"
          :class="statusFilter === tab.key ? 'bg-primary text-white' : 'border border-border text-muted hover:bg-muted'"
          @click="statusFilter = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>
      <div class="ml-auto flex gap-2">
        <Button variant="outline" @click="reportModalOpen = true">
          <FileText class="h-4 w-4" /> Generate
        </Button>
        <Button variant="outline" :disabled="exporting" @click="exportExcel">
          <Download class="h-4 w-4" /> {{ exporting ? 'Export...' : 'Excel' }}
        </Button>
        <Button @click="openCreate"><Plus class="h-4 w-4" /> Tambah Gamas</Button>
      </div>
    </div>

    <Card class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-border text-left text-xs text-muted">
            <th class="pb-3 pr-3 font-medium">Tanggal</th>
            <th class="pb-3 pr-3 font-medium">Jenis</th>
            <th class="pb-3 pr-3 font-medium">Lokasi / Impact</th>
            <th class="pb-3 pr-3 font-medium">ODC/Site</th>
            <th class="pb-3 pr-3 font-medium">Problem</th>
            <th class="pb-3 pr-3 font-medium">Status</th>
            <th class="pb-3 pr-3 font-medium">Input oleh</th>
            <th class="pb-3 pr-3 font-medium">Close oleh</th>
            <th class="pb-3 font-medium">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="9" class="py-10 text-center text-muted">Memuat...</td>
          </tr>
          <tr v-else-if="!items.length">
            <td colspan="9" class="py-10 text-center text-muted">Belum ada data gamas.</td>
          </tr>
          <tr
            v-for="item in items"
            :key="item.id"
            class="border-b border-border/50 hover:bg-muted/30"
          >
            <td class="py-3 pr-3 text-xs text-muted">
              {{ item.report_date || '—' }}
              <span class="block text-[11px] text-muted/80">{{ formatTimeId(item.created_at) || '—' }}</span>
              <span
                v-if="item.is_carryover"
                class="mt-1 inline-block rounded bg-amber-500/15 px-1.5 py-0.5 text-[10px] text-amber-600"
              >
                Open dari {{ item.report_date }}
              </span>
            </td>
            <td class="py-3 pr-3 text-xs">{{ kindLabel(item.gamas_kind) }}</td>
            <td class="py-3 pr-3">
              <span class="font-medium">{{ item.location_label || '—' }}</span>
              <span v-if="item.impact" class="block text-xs text-muted">Impact: {{ item.impact }}</span>
            </td>
            <td class="py-3 pr-3 text-xs">{{ item.odc_name || '—' }}</td>
            <td class="py-3 pr-3 max-w-[12rem] truncate">{{ item.problem || '—' }}</td>
            <td class="py-3 pr-3"><Badge :variant="statusVariant(item.status)">{{ item.status }}</Badge></td>
            <td class="py-3 pr-3 text-xs text-foreground">
              {{ item.creator_name || '—' }}
              <span class="block text-[11px] text-muted/80">{{ formatTimeId(item.created_at) || '—' }}</span>
            </td>
            <td class="py-3 pr-3 text-xs text-foreground">
              {{ item.clearer_name || '—' }}
              <span class="block text-[11px] text-muted/80">{{ formatTimeId(item.cleared_at) || '—' }}</span>
            </td>
            <td class="py-3">
              <div class="flex gap-1">
                <button
                  type="button"
                  class="rounded-lg p-1.5 text-primary hover:bg-primary/10"
                  title="Update Progress"
                  @click="openUpdate(item)"
                >
                  <MessageSquarePlus class="h-4 w-4" />
                </button>
                <button
                  v-if="item.status !== 'Clear'"
                  type="button"
                  class="rounded-lg p-1.5 text-success hover:bg-success/10"
                  title="Tandai Clear"
                  @click="markClear(item)"
                >
                  <CheckCircle2 class="h-4 w-4" />
                </button>
                <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-muted" title="Edit" @click="openEdit(item)">
                  <Pencil class="h-4 w-4" />
                </button>
                <button type="button" class="rounded-lg p-1.5 text-danger hover:bg-danger/10" title="Hapus" @click="requestDelete(item)">
                  <Trash2 class="h-4 w-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </Card>

    <p class="mt-3 text-xs text-muted">
      Menampilkan maksimal 500 baris. Perubahan user lain diperbarui otomatis (realtime).
    </p>

    <Modal
      :open="showModal"
      :title="editingId ? 'Edit Gamas' : 'Tambah Gamas'"
      size="lg"
      @close="showModal = false"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1.5 block text-sm font-medium">Jenis Gamas <span class="text-danger">*</span></label>
          <Select v-model="form.gamas_kind">
            <option v-for="k in kindOptions" :key="k.value" :value="k.value">{{ k.label }}</option>
          </Select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium">ODC / Site</label>
          <Select v-model="form.odc_name">
            <option value="">— Pilih ODC —</option>
            <option v-for="o in odcs" :key="o.id" :value="o.name">{{ o.name }}</option>
          </Select>
        </div>
        <div class="sm:col-span-2">
          <label class="mb-1.5 block text-sm font-medium">Lokasi / Jalur <span class="text-danger">*</span></label>
          <Input
            v-model="form.location_label"
            :placeholder="form.gamas_kind === 'upstream'
              ? 'Contoh: Upstream Jiwan · ether11'
              : form.gamas_kind === 'olt'
                ? 'Contoh: OLT Pondok Gede'
                : 'Contoh: ODP Banyumas 1'"
            required
          />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium">Impact</label>
          <Input v-model="form.impact" placeholder="all user / ±10 user" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium">Shift</label>
          <Select v-model="form.shift">
            <option value="">—</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
          </Select>
        </div>
        <div class="sm:col-span-2">
          <label class="mb-1.5 block text-sm font-medium">Problem</label>
          <Input v-model="form.problem" placeholder="LOS / link down / dll" />
        </div>
        <div class="sm:col-span-2">
          <label class="mb-1.5 block text-sm font-medium">Action</label>
          <Textarea v-model="form.action" :rows="2" placeholder="Koordinasi dengan tim / vendor" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium">Status</label>
          <Select v-model="form.status">
            <option value="On-Progress">On-Progress</option>
            <option value="Clear">Clear</option>
          </Select>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1.5 block text-sm font-medium">Start Problem</label>
            <Input v-model="form.start_problem" type="date" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium">End Problem</label>
            <Input v-model="form.end_problem" type="date" />
          </div>
        </div>
      </div>
      <p v-if="error && showModal" class="mt-3 text-sm text-danger">{{ error }}</p>
      <template #footer>
        <Button variant="outline" @click="showModal = false">Batal</Button>
        <Button :disabled="saving" @click="submit">{{ saving ? 'Menyimpan...' : 'Simpan' }}</Button>
      </template>
    </Modal>

    <Modal
      :open="!!deleteTarget"
      title="Hapus Gamas"
      :subtitle="deleteTarget ? `Yakin ingin menghapus gamas ${deleteTarget.location_label}? Tindakan ini tidak bisa dibatalkan.` : undefined"
      size="sm"
      @close="deleteTarget = null"
    >
      <p class="text-sm text-muted">
        Data gamas akan dihapus permanen.
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
        Silakan isi tindakan penanganan sebelum mengubah status GAMAS menjadi Clear.
      </p>
      <div class="mt-4">
        <label for="gamas-action-clear-textarea" class="mb-1.5 block text-sm font-medium">
          Action
        </label>
        <Textarea
          id="gamas-action-clear-textarea"
          v-model="actionClearText"
          :rows="3"
          placeholder="Contoh: Koordinasi tim lapangan dan normalisasi upstream."
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

    <Modal
      :open="updateOpen"
      title="Tambah Update"
      size="lg"
      @close="closeUpdate"
    >
      <div class="rounded-xl border border-border bg-muted/30 p-3 text-sm">
        <p class="font-medium text-foreground">
          {{ kindLabel(updateItem?.gamas_kind ?? '') }} · {{ updateItem?.location_label || '—' }}
        </p>
        <p class="mt-1 text-xs text-muted">ODC/Site: {{ updateItem?.odc_name || '—' }}</p>
        <p class="mt-1 break-words text-xs text-muted">Problem: {{ updateItem?.problem || '—' }}</p>
      </div>
      <div class="mt-4">
        <label for="gamas-update-textarea" class="mb-1.5 block text-sm font-medium">
          Update / Progress <span class="text-danger">*</span>
        </label>
        <Textarea
          id="gamas-update-textarea"
          v-model="updateText"
          :rows="3"
          placeholder="Tuliskan perkembangan penanganan gamas..."
          :disabled="updateSaving"
        />
      </div>
      <p v-if="updateError" class="mt-2 text-sm text-danger">{{ updateError }}</p>

      <div class="mt-5 border-t border-border pt-4">
        <h3 class="mb-3 text-sm font-semibold text-foreground">Riwayat Update</h3>
        <p v-if="updatesLoading" class="py-4 text-center text-sm text-muted">Memuat riwayat...</p>
        <div v-else-if="updates.length" class="max-h-64 space-y-2 overflow-y-auto pr-1">
          <div
            v-for="u in updates"
            :key="u.id"
            class="rounded-xl border border-border bg-muted/30 p-3"
          >
            <p class="break-words text-sm text-foreground">{{ u.update_text }}</p>
            <p class="mt-1.5 text-[11px] text-muted">
              {{ u.author_name || 'Petugas' }} · {{ formatUpdateTime(u.created_at) }}
            </p>
          </div>
        </div>
        <p v-else class="py-4 text-center text-sm text-muted">Belum ada riwayat update</p>
      </div>

      <template #footer>
        <Button type="button" variant="outline" :disabled="updateSaving" @click="closeUpdate">
          Batal
        </Button>
        <Button type="button" :disabled="updateSaving" @click="submitUpdate">
          {{ updateSaving ? 'Menyimpan...' : 'Simpan Update' }}
        </Button>
      </template>
    </Modal>

    <SectionReportModal
      v-model:open="reportModalOpen"
      section="gamas"
      :from="fromDate || todayInput()"
      :to="toDate || fromDate || todayInput()"
    />

    <Toast :message="toast.message" :variant="toast.variant" @dismiss="toast.message = ''" />
  </AppLayout>
</template>

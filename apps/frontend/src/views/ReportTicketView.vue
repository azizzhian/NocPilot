<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import Select from '@/components/ui/Select.vue'
import DateRangePicker from '@/components/ui/DateRangePicker.vue'
import Modal from '@/components/ui/Modal.vue'
import SectionReportModal from '@/components/report/SectionReportModal.vue'
import TicketFormModal from '@/components/tickets/TicketFormModal.vue'
import TicketDetailModal from '@/components/tickets/TicketDetailModal.vue'
import { reportTicketApi, odcApi, type ReportTicketItem, type ReportTicketStats } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { todayInput, formatTimeId } from '@/lib/date-input'
import { ticketStatusLabel, ticketStatusVariant } from '@/lib/ticket-status'
import { Plus, Pencil, Trash2, Download, FileText, Eye } from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const search = ref('')
const statusFilter = ref('all')
const sourceFilter = ref<'all' | 'direct' | 'complaint'>('all')
const fromDate = ref('')
const toDate = ref('')
const odcName = ref('')
const clearMode = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)
const odcs = ref<{ id: number; name: string }[]>([])
const items = ref<ReportTicketItem[]>([])
const stats = ref<Partial<ReportTicketStats>>({})
const statsFailed = ref(false)
const statsError = ref('')
const loading = ref(true)
const exporting = ref(false)
const formOpen = ref(false)
const formEditingId = ref<number | null>(null)
const detailOpen = ref(false)
const detailId = ref<number | null>(null)
const error = ref('')
const deleteTarget = ref<ReportTicketItem | null>(null)
const deleting = ref(false)
const reportModalOpen = ref(false)

const statusChips = ['On-Progress', 'Close'] as const

function statusCount(status: string): number | string {
  if (statsFailed.value) return '—'
  if (status === 'Close') return stats.value.close_count ?? 0
  return stats.value.status_counts?.[status] ?? 0
}

const scopedTotal = computed(() => statsFailed.value
  ? '—'
  : Object.values(stats.value.status_counts ?? {}).reduce((sum, value) => sum + value, 0))

function apiError(reason: unknown, fallback: string): string {
  const err = reason as { response?: { data?: { message?: string } } }
  return err.response?.data?.message || fallback
}

function toggleStatus(status: string) {
  clearMode.value = false
  statusFilter.value = statusFilter.value === status ? 'all' : status
}

function kpiCards() {
  return [
    { label: 'Total Tiket', value: statsFailed.value ? '—' : (stats.value.total ?? 0), tone: 'text-foreground' },
    { label: 'Tiket Aktif', value: statsFailed.value ? '—' : (stats.value.active ?? 0), tone: 'text-warning' },
    { label: 'Dari Komplain', value: statsFailed.value ? '—' : (stats.value.from_complaint ?? 0), tone: 'text-info' },
    { label: 'Tiket Selesai', value: statsFailed.value ? '—' : (stats.value.finished ?? 0), tone: 'text-success' },
  ]
}

function sourceLabel(item: ReportTicketItem) {
  return item.source === 'complaint' ? 'Dari Komplain' : 'Tiket Langsung'
}

function listParams() {
  return {
    search: search.value || undefined,
    status: clearMode.value ? 'all' : statusFilter.value,
    from: fromDate.value || undefined,
    to: toDate.value || undefined,
    odc_name: odcName.value || undefined,
    source: sourceFilter.value === 'all' ? undefined : sourceFilter.value,
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

async function load(page = currentPage.value) {
  loading.value = true
  error.value = ''
  const params = listParams()

  const [listResult, statsResult] = await Promise.allSettled([
    reportTicketApi.list({ ...params, page }),
    reportTicketApi.stats(params),
  ])

  if (listResult.status === 'fulfilled') {
    items.value = listResult.value.data.data
    const meta = listResult.value.data.meta
    currentPage.value = meta?.current_page ?? page
    lastPage.value = meta?.last_page ?? 1
  } else {
    items.value = []
    error.value = apiError(listResult.reason, 'Gagal memuat daftar ticket.')
  }

  if (statsResult.status === 'fulfilled') {
    stats.value = statsResult.value.data
    statsFailed.value = false
    statsError.value = ''
  } else {
    statsFailed.value = true
    statsError.value = apiError(statsResult.reason, 'Gagal memuat statistik ticket.')
  }

  loading.value = false
}

async function exportExcel() {
  exporting.value = true
  error.value = ''
  try {
    await reportTicketApi.exportExcel(listParams())
  } catch {
    error.value = 'Gagal export Excel.'
  } finally {
    exporting.value = false
  }
}

function openCreate() {
  formEditingId.value = null
  formOpen.value = true
}

function openEdit(item: ReportTicketItem) {
  formEditingId.value = item.id
  formOpen.value = true
}

function openDetail(item: ReportTicketItem) {
  detailId.value = item.id
  detailOpen.value = true
}

function viewComplaint(id: number) {
  detailOpen.value = false
  router.push(`/komplain?open=${id}`)
}

async function onFormSaved() {
  await load()
}

function requestDelete(item: ReportTicketItem) {
  deleteTarget.value = item
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  error.value = ''
  try {
    await reportTicketApi.destroy(deleteTarget.value.id)
    deleteTarget.value = null
    await load()
  } catch {
    error.value = 'Gagal menghapus ticket.'
  } finally {
    deleting.value = false
  }
}

let searchTimeout: ReturnType<typeof setTimeout>
watch([search, statusFilter, sourceFilter, fromDate, toDate, odcName, clearMode], () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => load(1), 400)
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
  if (q.source === 'direct' || q.source === 'complaint') {
    sourceFilter.value = q.source
  }
}

onMounted(async () => {
  applyRouteQuery()
  await loadOdcs()
  await load(1)
  if (typeof route.query.open === 'string') {
    const id = Number(route.query.open)
    if (Number.isFinite(id) && id > 0) {
      detailId.value = id
      detailOpen.value = true
    }
  }
})
</script>

<template>
  <AppLayout title="Report Ticket" subtitle="Ticket operasional: lokasi, pelanggan, problem, status">
    <div class="mb-3 grid grid-cols-2 gap-2 md:grid-cols-4">
      <Card v-for="card in kpiCards()" :key="card.label" padding="sm" class="py-3">
        <p class="text-[11px] text-muted">{{ card.label }}</p>
        <p class="mt-0.5 text-xl font-semibold" :class="card.tone">{{ card.value }}</p>
      </Card>
    </div>

    <div v-if="error && !formOpen" class="mb-2 rounded-xl border border-danger/30 bg-danger/5 px-4 py-2.5 text-sm text-danger">
      {{ error }}
    </div>
    <div v-if="statsError && !formOpen" class="mb-2 rounded-xl border border-warning/30 bg-warning/5 px-4 py-2.5 text-sm text-foreground">
      Statistik ticket gagal dimuat — KPI dan jumlah status ditampilkan sebagai “—”. {{ statsError }}
    </div>

    <div
      v-if="odcName === '__none__'"
      class="mb-2 rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-2 text-sm text-foreground"
    >
      Menampilkan ticket <span class="font-semibold">Tanpa ODC</span> — isi ODC/Site untuk revisi.
      <button type="button" class="ml-2 text-xs text-primary underline" @click="odcName = ''">Hapus filter</button>
    </div>
    <div
      v-if="clearMode"
      class="mb-2 rounded-xl border border-primary/20 bg-primary/5 px-4 py-2 text-sm text-foreground"
    >
      Filter dari dashboard: ticket <span class="font-semibold">selesai</span> menurut tanggal clear.
      <button type="button" class="ml-2 text-xs text-primary underline" @click="clearMode = false">Hapus</button>
    </div>

    <Card class="mb-4 p-3">
      <div class="flex flex-wrap items-end gap-2">
        <SearchInput
          v-model="search"
          placeholder="Cari pelanggan / lokasi / no. tiket"
          class="min-w-[12rem] flex-1 sm:max-w-xs"
        />
        <DateRangePicker v-model:from="fromDate" v-model:to="toDate" class="w-full sm:w-56" />
        <div>
          <label class="mb-1 block text-[11px] text-muted">Sumber</label>
          <Select v-model="sourceFilter" class="w-36">
            <option value="all">Semua</option>
            <option value="direct">Tiket Langsung</option>
            <option value="complaint">Dari Komplain</option>
          </Select>
        </div>
        <div>
          <label class="mb-1 block text-[11px] text-muted">ODC / Site</label>
          <Select v-model="odcName" class="w-40">
            <option value="">Semua ODC</option>
            <option value="__none__">Tanpa ODC</option>
            <option v-for="o in odcs" :key="o.id" :value="o.name">{{ o.name }}</option>
          </Select>
        </div>
        <div class="ml-auto flex gap-2">
          <Button variant="outline" @click="reportModalOpen = true">
            <FileText class="h-4 w-4" /> Generate
          </Button>
          <Button variant="outline" :disabled="exporting" @click="exportExcel">
            <Download class="h-4 w-4" /> {{ exporting ? 'Export...' : 'Excel' }}
          </Button>
          <Button v-if="auth.can('ticket.manage')" @click="openCreate"><Plus class="h-4 w-4" /> Tambah Ticket</Button>
        </div>
      </div>

      <div class="mt-3 flex flex-wrap items-center gap-1.5 border-t border-border pt-2.5">
        <span class="mr-1 text-[11px] text-muted">Status</span>
        <button type="button" @click="statusFilter = 'all'; clearMode = false">
          <Badge
            variant="outline"
            class="cursor-pointer"
            :class="statusFilter === 'all' && !clearMode ? 'ring-2 ring-primary/50' : ''"
          >
            Semua · {{ scopedTotal }}
          </Badge>
        </button>
        <button v-for="s in statusChips" :key="s" type="button" @click="toggleStatus(s)">
          <Badge
            :variant="ticketStatusVariant(s)"
            class="cursor-pointer transition-opacity"
            :class="statusFilter === s ? 'ring-2 ring-primary/50' : 'opacity-80 hover:opacity-100'"
          >
            {{ ticketStatusLabel(s) }} · {{ statusCount(s) }}
          </Badge>
        </button>
      </div>
    </Card>

    <Card class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-border text-left text-xs text-muted">
            <th class="pb-3 pr-3 font-medium">ODC/Site</th>
            <th class="pb-3 pr-3 font-medium">Lokasi</th>
            <th class="pb-3 pr-3 font-medium">ID Pel</th>
            <th class="pb-3 pr-3 font-medium">Nama</th>
            <th class="pb-3 pr-3 font-medium">Problem</th>
            <th class="pb-3 pr-3 font-medium">Status</th>
            <th class="pb-3 pr-3 font-medium">Sumber</th>
            <th class="pb-3 pr-3 font-medium">Tgl Open</th>
            <th class="pb-3 pr-3 font-medium">Input oleh</th>
            <th class="pb-3 pr-3 font-medium">Tgl Close</th>
            <th class="pb-3 pr-3 font-medium">Close oleh</th>
            <th class="pb-3 font-medium">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="12" class="py-10 text-center text-muted">Memuat...</td>
          </tr>
          <tr v-else-if="!items.length">
            <td colspan="12" class="py-10 text-center text-muted">Belum ada ticket.</td>
          </tr>
          <tr
            v-for="item in items"
            :key="item.id"
            class="border-b border-border/50 hover:bg-muted/30"
          >
            <td class="py-3 pr-3 text-xs">{{ item.odc_name || '—' }}</td>
            <td class="py-3 pr-3">{{ item.location || '—' }}</td>
            <td class="py-3 pr-3 font-mono text-xs">{{ item.customer_code || '—' }}</td>
            <td class="py-3 pr-3 font-medium">{{ item.customer_name }}</td>
            <td class="py-3 pr-3 max-w-[12rem] truncate">{{ item.problem || '—' }}</td>
            <td class="py-3 pr-3">
              <Badge :variant="ticketStatusVariant(item.status)">
                {{ item.status_label || ticketStatusLabel(item.status) }}
              </Badge>
            </td>
            <td class="py-3 pr-3 text-xs">
              <Badge :variant="item.source === 'complaint' ? 'info' : 'secondary'">
                {{ sourceLabel(item) }}
              </Badge>
              <span v-if="item.complaint_number" class="mt-1 block font-mono text-[10px] text-muted">
                {{ item.complaint_number }}
              </span>
            </td>
            <td class="py-3 pr-3 text-xs text-muted">
              {{ item.opened_at || '—' }}
              <span class="block text-[11px] text-muted/80">{{ formatTimeId(item.created_at) || '—' }}</span>
            </td>
            <td class="py-3 pr-3 text-xs text-foreground">{{ item.creator_name || '—' }}</td>
            <td class="py-3 pr-3 text-xs text-muted">
              {{ item.closed_at || '—' }}
              <span class="block text-[11px] text-muted/80">{{ formatTimeId(item.cleared_at) || '—' }}</span>
            </td>
            <td class="py-3 pr-3 text-xs text-foreground">{{ item.clearer_name || '—' }}</td>
            <td class="py-3">
              <div class="flex gap-1">
                <button type="button" class="rounded-lg p-1.5 text-primary hover:bg-primary/10" title="Detail ticket" @click="openDetail(item)">
                  <Eye class="h-4 w-4" />
                </button>
                <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-muted" @click="openEdit(item)">
                  <Pencil class="h-4 w-4" />
                </button>
                <button type="button" class="rounded-lg p-1.5 text-danger hover:bg-danger/10" @click="requestDelete(item)">
                  <Trash2 class="h-4 w-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </Card>

    <div v-if="lastPage > 1" class="mt-4 flex justify-center gap-2">
      <Button variant="outline" size="sm" :disabled="currentPage <= 1 || loading" @click="load(currentPage - 1)">
        Sebelumnya
      </Button>
      <span class="flex items-center px-3 text-sm text-muted">{{ currentPage }} / {{ lastPage }}</span>
      <Button variant="outline" size="sm" :disabled="currentPage >= lastPage || loading" @click="load(currentPage + 1)">
        Selanjutnya
      </Button>
    </div>

    <Modal
      :open="!!deleteTarget"
      title="Hapus Ticket"
      :subtitle="deleteTarget ? `Yakin ingin menghapus ${deleteTarget.customer_name}? Tindakan ini tidak bisa dibatalkan.` : undefined"
      size="sm"
      @close="deleteTarget = null"
    >
      <p class="text-sm text-muted">
        Ticket akan dihapus permanen dari daftar report.
      </p>
      <template #footer>
        <Button type="button" variant="outline" :disabled="deleting" @click="deleteTarget = null">Batal</Button>
        <Button type="button" variant="danger" :disabled="deleting" @click="confirmDelete">
          {{ deleting ? 'Menghapus...' : 'Hapus' }}
        </Button>
      </template>
    </Modal>

    <TicketFormModal
      :open="formOpen"
      :editing-id="formEditingId"
      @close="formOpen = false"
      @saved="onFormSaved"
    />

    <TicketDetailModal
      :open="detailOpen"
      :ticket-id="detailId"
      @close="detailOpen = false"
      @changed="load"
      @view-complaint="viewComplaint"
    />

    <SectionReportModal
      v-model:open="reportModalOpen"
      section="ticket"
      :source="sourceFilter"
      :status="statusFilter === 'all' ? undefined : statusFilter"
      :from="fromDate || todayInput()"
      :to="toDate || fromDate || todayInput()"
    />
  </AppLayout>
</template>

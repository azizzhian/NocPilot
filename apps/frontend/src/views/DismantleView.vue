<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import Select from '@/components/ui/Select.vue'
import DateRangePicker from '@/components/ui/DateRangePicker.vue'
import Modal from '@/components/ui/Modal.vue'
import SectionReportModal from '@/components/report/SectionReportModal.vue'
import { dismantleApi, type DismantleItem } from '@/services/api'
import { todayInput } from '@/lib/date-input'
import { Plus, Pencil, Trash2, FileText } from 'lucide-vue-next'

const search = ref('')
const statusFilter = ref('all')
const fromDate = ref('')
const toDate = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const items = ref<DismantleItem[]>([])
const stats = ref<Record<string, number>>({})
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const editingId = ref<number | null>(null)
const error = ref('')
const deleteTarget = ref<DismantleItem | null>(null)
const deleting = ref(false)
const reportModalOpen = ref(false)

const form = ref({
  location: '',
  customer_code: '',
  customer_name: '',
  opened_at: todayInput(),
  closed_at: '',
  status: 'On-Progress',
})

const statusTabs = [
  { key: 'all', label: 'Semua' },
  { key: 'Pending', label: 'Pending' },
  { key: 'On-Progress', label: 'On-Progress' },
  { key: 'Clear', label: 'Clear' },
]

function statusVariant(status: string) {
  if (status === 'Clear') return 'success'
  if (status === 'Pending') return 'secondary'
  return 'warning'
}

async function load(page = currentPage.value) {
  loading.value = true
  error.value = ''
  try {
    const [listRes, statsRes] = await Promise.all([
      dismantleApi.list({
        search: search.value || undefined,
        status: statusFilter.value,
        from: fromDate.value || undefined,
        to: toDate.value || undefined,
        page,
      }),
      dismantleApi.stats(),
    ])
    items.value = listRes.data.data
    const meta = listRes.data.meta
    currentPage.value = meta?.current_page ?? page
    lastPage.value = meta?.last_page ?? 1
    stats.value = statsRes.data
  } catch {
    error.value = 'Gagal memuat dismantle.'
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  form.value = {
    location: '',
    customer_code: '',
    customer_name: '',
    opened_at: todayInput(),
    closed_at: '',
    status: 'On-Progress',
  }
  error.value = ''
  showModal.value = true
}

function openEdit(item: DismantleItem) {
  editingId.value = item.id
  form.value = {
    location: item.location ?? '',
    customer_code: item.customer_code ?? '',
    customer_name: item.customer_name,
    opened_at: item.opened_at ?? '',
    closed_at: item.closed_at ?? '',
    status: item.status || 'On-Progress',
  }
  error.value = ''
  showModal.value = true
}

async function submit() {
  saving.value = true
  error.value = ''
  try {
    const payload = {
      ...form.value,
      opened_at: form.value.opened_at || null,
      closed_at: form.value.closed_at || null,
    }
    if (editingId.value) {
      await dismantleApi.update(editingId.value, payload)
    } else {
      await dismantleApi.create(payload)
    }
    showModal.value = false
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const first = err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat()[0]
      : undefined
    error.value = first ?? err.response?.data?.message ?? 'Gagal menyimpan dismantle.'
  } finally {
    saving.value = false
  }
}

function requestDelete(item: DismantleItem) {
  deleteTarget.value = item
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  error.value = ''
  try {
    await dismantleApi.destroy(deleteTarget.value.id)
    deleteTarget.value = null
    await load()
  } catch {
    error.value = 'Gagal menghapus dismantle.'
  } finally {
    deleting.value = false
  }
}

let searchTimeout: ReturnType<typeof setTimeout>
watch([search, statusFilter, fromDate, toDate], () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => load(1), 400)
})

onMounted(() => load(1))
</script>

<template>
  <AppLayout title="Dismantle" subtitle="Manajemen dismantle pelanggan">
    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
      <Card
        v-for="item in [
          { label: 'Total', key: 'total' },
          { label: 'Pending', key: 'pending' },
          { label: 'On-Progress', key: 'on_progress' },
          { label: 'Clear', key: 'clear' },
        ]"
        :key="item.key"
        padding="sm"
      >
        <p class="text-xl font-bold text-danger">{{ stats[item.key] ?? 0 }}</p>
        <p class="text-xs text-muted">{{ item.label }}</p>
      </Card>
    </div>

    <div v-if="error && !showModal && !deleteTarget" class="mb-4 rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">
      {{ error }}
    </div>

    <div class="mb-4 flex flex-wrap items-end gap-3">
      <SearchInput v-model="search" placeholder="Cari lokasi / ID pel / nama..." class="max-w-sm" />
      <DateRangePicker
        v-model:from="fromDate"
        v-model:to="toDate"
        class="w-64"
      />
      <div class="flex flex-wrap gap-1 rounded-xl border border-border p-1">
        <button
          v-for="tab in statusTabs"
          :key="tab.key"
          type="button"
          :class="[
            'rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
            statusFilter === tab.key ? 'bg-primary text-white' : 'text-muted',
          ]"
          @click="statusFilter = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>
      <div class="ml-auto flex gap-2">
        <Button variant="outline" @click="reportModalOpen = true">
          <FileText class="h-4 w-4" /> Generate
        </Button>
        <Button @click="openCreate"><Plus class="h-4 w-4" /> Tambah Dismantle</Button>
      </div>
    </div>

    <Card v-if="loading" class="p-8 text-center text-sm text-muted">Memuat data...</Card>

    <Card v-else class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-border text-left text-xs text-muted">
            <th class="pb-3 pr-4 font-medium">Lokasi</th>
            <th class="pb-3 pr-4 font-medium">ID Pel</th>
            <th class="pb-3 pr-4 font-medium">Nama</th>
            <th class="pb-3 pr-4 font-medium">Status</th>
            <th class="pb-3 pr-4 font-medium">Open Ticket</th>
            <th class="pb-3 pr-4 font-medium">Close Ticket</th>
            <th class="pb-3 font-medium">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!items.length">
            <td colspan="7" class="py-10 text-center text-muted">Belum ada data dismantle.</td>
          </tr>
          <tr
            v-for="item in items"
            :key="item.id"
            class="border-b border-border/50 hover:bg-muted/30"
          >
            <td class="py-3 pr-4">{{ item.location || '—' }}</td>
            <td class="py-3 pr-4 font-mono text-xs">{{ item.customer_code || '—' }}</td>
            <td class="py-3 pr-4 font-medium">{{ item.customer_name }}</td>
            <td class="py-3 pr-4">
              <Badge :variant="statusVariant(item.status)">{{ item.status }}</Badge>
            </td>
            <td class="py-3 pr-4">{{ item.opened_at || '—' }}</td>
            <td class="py-3 pr-4">{{ item.closed_at || '—' }}</td>
            <td class="py-3">
              <div class="flex gap-1">
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
      :open="showModal"
      :title="editingId ? 'Edit Dismantle' : 'Tambah Dismantle'"
      size="lg"
      @close="showModal = false"
    >
      <div class="space-y-4">
        <div v-if="error" class="rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">
          {{ error }}
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm font-medium">Lokasi</label>
            <Input v-model="form.location" placeholder="POP / Area / Site" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium">ID Pel</label>
            <Input v-model="form.customer_code" placeholder="Kode pelanggan" />
          </div>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium">Nama *</label>
          <Input v-model="form.customer_name" required />
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
          <div>
            <label class="mb-1.5 block text-sm font-medium">Status Tiket</label>
            <Select v-model="form.status" class="w-full">
              <option value="Pending">Pending</option>
              <option value="On-Progress">On-Progress</option>
              <option value="Clear">Clear</option>
            </Select>
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium">Open Ticket</label>
            <Input v-model="form.opened_at" type="date" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium">Close Ticket</label>
            <Input v-model="form.closed_at" type="date" />
          </div>
        </div>
      </div>
      <template #footer>
        <Button variant="outline" @click="showModal = false">Batal</Button>
        <Button :disabled="saving || !form.customer_name" @click="submit">
          {{ saving ? 'Menyimpan...' : 'Simpan' }}
        </Button>
      </template>
    </Modal>

    <Modal
      :open="!!deleteTarget"
      title="Hapus dismantle?"
      :subtitle="deleteTarget ? `Yakin ingin menghapus ${deleteTarget.customer_name}? Tindakan ini tidak bisa dibatalkan.` : undefined"
      size="sm"
      @close="deleteTarget = null"
    >
      <p class="text-sm text-muted">Data dismantle akan dihapus permanen.</p>
      <template #footer>
        <Button type="button" variant="outline" :disabled="deleting" @click="deleteTarget = null">Batal</Button>
        <Button type="button" variant="danger" :disabled="deleting" @click="confirmDelete">
          {{ deleting ? 'Menghapus...' : 'Hapus' }}
        </Button>
      </template>
    </Modal>

    <SectionReportModal
      v-model:open="reportModalOpen"
      section="dismantle"
      :from="fromDate || todayInput()"
      :to="toDate || fromDate || todayInput()"
    />
  </AppLayout>
</template>

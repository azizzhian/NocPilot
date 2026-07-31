<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import Select from '@/components/ui/Select.vue'
import Modal from '@/components/ui/Modal.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { reportTicketApi, type ReportTicketItem } from '@/services/api'
import { Plus, Pencil, Trash2 } from 'lucide-vue-next'

function todayInput() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const search = ref('')
const statusFilter = ref('all')
const items = ref<ReportTicketItem[]>([])
const stats = ref<Record<string, number>>({})
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const editingId = ref<number | null>(null)
const error = ref('')
const deleteTarget = ref<ReportTicketItem | null>(null)
const deleting = ref(false)

const form = ref({
  location: '',
  customer_code: '',
  customer_name: '',
  problem: '',
  action: '',
  status: 'On-Progress',
  opened_at: todayInput(),
  closed_at: '',
  notes: '',
})

const statusTabs = [
  { key: 'all', label: 'Semua' },
  { key: 'On-Progress', label: 'On-Progress' },
  { key: 'Clear', label: 'Clear' },
  { key: 'Closed', label: 'Closed' },
]

function statusVariant(status: string) {
  if (status === 'Clear') return 'success'
  if (status === 'Closed') return 'secondary'
  return 'warning'
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [listRes, statsRes] = await Promise.all([
      reportTicketApi.list({
        search: search.value || undefined,
        status: statusFilter.value,
      }),
      reportTicketApi.stats(),
    ])
    items.value = listRes.data.data
    stats.value = statsRes.data
  } catch {
    error.value = 'Gagal memuat ticket.'
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
    problem: '',
    action: '',
    status: 'On-Progress',
    opened_at: todayInput(),
    closed_at: '',
    notes: '',
  }
  showModal.value = true
}

function openEdit(item: ReportTicketItem) {
  editingId.value = item.id
  form.value = {
    location: item.location ?? '',
    customer_code: item.customer_code ?? '',
    customer_name: item.customer_name,
    problem: item.problem ?? '',
    action: item.action ?? '',
    status: item.status,
    opened_at: item.opened_at ?? '',
    closed_at: item.closed_at ?? '',
    notes: item.notes ?? '',
  }
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
      await reportTicketApi.update(editingId.value, payload)
    } else {
      await reportTicketApi.create(payload)
    }
    showModal.value = false
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const first = err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat()[0]
      : undefined
    error.value = first ?? err.response?.data?.message ?? 'Gagal menyimpan ticket.'
  } finally {
    saving.value = false
  }
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
watch([search, statusFilter], () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(load, 400)
})

onMounted(load)
</script>

<template>
  <AppLayout title="Report Ticket" subtitle="Ticket operasional: lokasi, pelanggan, problem, status">
    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
      <Card
        v-for="item in [
          { label: 'Total', key: 'total' },
          { label: 'On-Progress', key: 'on_progress' },
          { label: 'Clear', key: 'clear' },
          { label: 'Closed', key: 'closed' },
        ]"
        :key="item.key"
        padding="sm"
      >
        <p class="text-xl font-bold text-primary">{{ stats[item.key] ?? 0 }}</p>
        <p class="text-xs text-muted">{{ item.label }}</p>
      </Card>
    </div>

    <div v-if="error && !showModal" class="mb-4 rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">
      {{ error }}
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
      <div class="flex flex-wrap items-center gap-2">
        <SearchInput v-model="search" placeholder="Cari nama / ID / lokasi..." class="max-w-sm" />
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
      </div>
      <Button @click="openCreate"><Plus class="h-4 w-4" /> Tambah Ticket</Button>
    </div>

    <Card class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-border text-left text-xs text-muted">
            <th class="pb-3 pr-3 font-medium">Lokasi</th>
            <th class="pb-3 pr-3 font-medium">ID Pel</th>
            <th class="pb-3 pr-3 font-medium">Nama</th>
            <th class="pb-3 pr-3 font-medium">Problem</th>
            <th class="pb-3 pr-3 font-medium">Status</th>
            <th class="pb-3 pr-3 font-medium">Tgl Open</th>
            <th class="pb-3 pr-3 font-medium">Tgl Close</th>
            <th class="pb-3 font-medium">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="8" class="py-10 text-center text-muted">Memuat...</td>
          </tr>
          <tr v-else-if="!items.length">
            <td colspan="8" class="py-10 text-center text-muted">Belum ada ticket.</td>
          </tr>
          <tr
            v-for="item in items"
            :key="item.id"
            class="border-b border-border/50 hover:bg-muted/30"
          >
            <td class="py-3 pr-3">{{ item.location || '—' }}</td>
            <td class="py-3 pr-3 font-mono text-xs">{{ item.customer_code || '—' }}</td>
            <td class="py-3 pr-3 font-medium">{{ item.customer_name }}</td>
            <td class="py-3 pr-3 max-w-[12rem] truncate">{{ item.problem || '—' }}</td>
            <td class="py-3 pr-3"><Badge :variant="statusVariant(item.status)">{{ item.status }}</Badge></td>
            <td class="py-3 pr-3 text-xs text-muted">{{ item.opened_at || '—' }}</td>
            <td class="py-3 pr-3 text-xs text-muted">{{ item.closed_at || '—' }}</td>
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

    <Modal
      :open="showModal"
      :title="editingId ? 'Edit Ticket' : 'Tambah Ticket'"
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
            <Input v-model="form.location" placeholder="POP / Area / ODC" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium">ID Pelanggan</label>
            <Input v-model="form.customer_code" placeholder="Kode / ID" />
          </div>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium">Nama</label>
          <Input v-model="form.customer_name" required />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium">Problem</label>
          <Input v-model="form.problem" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium">Action</label>
          <Textarea v-model="form.action" :rows="2" />
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
          <div>
            <label class="mb-1.5 block text-sm font-medium">Status</label>
            <Select v-model="form.status" class="w-full">
              <option value="On-Progress">On-Progress</option>
              <option value="Clear">Clear</option>
              <option value="Closed">Closed</option>
            </Select>
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium">Tgl Open</label>
            <Input v-model="form.opened_at" type="date" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium">Tgl Close</label>
            <Input v-model="form.closed_at" type="date" />
          </div>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium">Keterangan</label>
          <Textarea v-model="form.notes" :rows="2" />
        </div>
      </div>
      <template #footer>
        <Button variant="outline" @click="showModal = false">Batal</Button>
        <Button :disabled="saving" @click="submit">{{ saving ? 'Menyimpan...' : 'Simpan' }}</Button>
      </template>
    </Modal>

    <Modal
      :open="!!deleteTarget"
      title="Hapus ticket?"
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
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import Modal from '@/components/ui/Modal.vue'
import ComplaintHistoryPanel from '@/components/daily/ComplaintHistoryPanel.vue'
import { customerApi, odcApi, dailyEntryApi, type Customer, type ComplaintHistoryItem, type ComplaintHistorySummary } from '@/services/api'
import { formatNumber } from '@/lib/utils'
import { Download, Upload, Users, UserCheck, UserX, UserMinus, Pencil, Trash2 } from 'lucide-vue-next'

const search = ref('')
const statusFilter = ref('')
const odcFilter = ref('')
const customers = ref<Customer[]>([])
const odcs = ref<{ id: number; name: string; code: string }[]>([])
const customerStats = ref({ total: 0, active: 0, inactive: 0, suspended: 0 })
const selectedCustomer = ref<Customer | null>(null)
const drawerOpen = ref(false)
const complaintHistory = ref<ComplaintHistoryItem[]>([])
const complaintHistoryTotal = ref(0)
const complaintHistorySummary = ref<ComplaintHistorySummary | null>(null)
const complaintHistoryLoading = ref(false)
const loading = ref(true)
const saving = ref(false)
const deleting = ref(false)
const deleteTarget = ref<Customer | null>(null)
const exporting = ref(false)
const importing = ref(false)
const modalOpen = ref(false)
const importModalOpen = ref(false)
const editingId = ref<number | null>(null)
const error = ref('')
const importResult = ref<{ success: number; failed: number; errors: string[] } | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const defaultForm = () => ({
  customer_code: '',
  name: '',
  address: '',
  phone: '',
  odc_id: '',
  status: 'active' as Customer['status'],
})

const form = ref(defaultForm())

const statusVariant = (s: string) => {
  const map: Record<string, 'success' | 'secondary' | 'danger'> = {
    active: 'success', inactive: 'secondary', suspended: 'danger',
  }
  return map[s] ?? 'secondary'
}

const statusLabel: Record<string, string> = {
  active: 'Aktif', inactive: 'Nonaktif', suspended: 'Suspend',
}

async function loadOdcs() {
  try {
    const res = await odcApi.list({ per_page: 200 })
    odcs.value = res.data.data.map((o) => ({
      id: o.id as number,
      name: String(o.name ?? ''),
      code: String(o.code ?? ''),
    }))
  } catch {
    odcs.value = []
  }
}

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [listRes, statsRes] = await Promise.all([
      customerApi.list({
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        odc_id: odcFilter.value ? Number(odcFilter.value) : undefined,
        per_page: 100,
      }),
      customerApi.stats(),
    ])
    customers.value = listRes.data.data
    customerStats.value = statsRes.data
    if (selectedCustomer.value) {
      selectedCustomer.value = customers.value.find((c) => c.id === selectedCustomer.value?.id) ?? null
    }
  } catch {
    error.value = 'Gagal memuat data pelanggan.'
  } finally {
    loading.value = false
  }
}

async function loadCustomerComplaintHistory(customerId: number) {
  complaintHistoryLoading.value = true
  try {
    const res = await dailyEntryApi.complaintHistory({ customer_id: customerId, days: 90 })
    complaintHistory.value = res.data.items
    complaintHistoryTotal.value = res.data.total
    complaintHistorySummary.value = res.data.summary ?? null
  } catch {
    complaintHistory.value = []
    complaintHistoryTotal.value = 0
    complaintHistorySummary.value = null
  } finally {
    complaintHistoryLoading.value = false
  }
}

function openCustomer(c: Customer) {
  selectedCustomer.value = c
  drawerOpen.value = true
  complaintHistory.value = []
  complaintHistoryTotal.value = 0
  complaintHistorySummary.value = null
  void loadCustomerComplaintHistory(c.id)
}

function openCreate() {
  editingId.value = null
  form.value = defaultForm()
  modalOpen.value = true
}

function openEdit(c: Customer) {
  editingId.value = c.id
  form.value = {
    customer_code: c.customer_code,
    name: c.name,
    address: c.address ?? '',
    phone: c.phone ?? '',
    odc_id: c.odc_id ? String(c.odc_id) : '',
    status: c.status,
  }
  drawerOpen.value = false
  modalOpen.value = true
}

function requestDelete(c: Customer, e?: Event) {
  e?.stopPropagation()
  deleteTarget.value = c
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  error.value = ''
  try {
    await customerApi.destroy(deleteTarget.value.id)
    if (selectedCustomer.value?.id === deleteTarget.value.id) {
      drawerOpen.value = false
      selectedCustomer.value = null
    }
    deleteTarget.value = null
    await loadData()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? 'Gagal menghapus pelanggan.'
  } finally {
    deleting.value = false
  }
}

async function submitForm() {
  saving.value = true
  error.value = ''
  try {
    const payload: Record<string, unknown> = {
      customer_code: form.value.customer_code,
      name: form.value.name,
      address: form.value.address || null,
      phone: form.value.phone || null,
      odc_id: form.value.odc_id ? Number(form.value.odc_id) : null,
      status: form.value.status,
    }

    if (editingId.value) {
      await customerApi.update(editingId.value, payload)
    } else {
      await customerApi.create(payload)
    }

    modalOpen.value = false
    await loadData()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const firstFieldError = err.response?.data?.errors
      ? Object.values(err.response.data.errors)[0]?.[0]
      : undefined
    error.value = firstFieldError ?? err.response?.data?.message ?? 'Gagal menyimpan pelanggan.'
  } finally {
    saving.value = false
  }
}

async function handleExport() {
  exporting.value = true
  error.value = ''
  try {
    await customerApi.exportCsv()
  } catch {
    error.value = 'Gagal mengekspor data pelanggan.'
  } finally {
    exporting.value = false
  }
}

function triggerImport() {
  fileInput.value?.click()
}

async function handleImportFile(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return

  importing.value = true
  error.value = ''
  importResult.value = null
  try {
    const res = await customerApi.importCsv(file)
    importResult.value = {
      success: res.data.success,
      failed: res.data.failed,
      errors: res.data.errors ?? [],
    }
    importModalOpen.value = true
    await Promise.all([loadData(), loadOdcs()])
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? 'Gagal mengimpor file.'
  } finally {
    importing.value = false
  }
}

let searchTimeout: ReturnType<typeof setTimeout>
watch([search, statusFilter, odcFilter], () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(loadData, 400)
})

onMounted(async () => {
  await Promise.all([loadOdcs(), loadData()])
})
</script>

<template>
  <AppLayout title="Master Pelanggan" subtitle="Import/export format sama dengan repot">
    <div v-if="error" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
      {{ error }}
    </div>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
      <Card padding="sm" v-for="stat in [
        { label: 'Total Pelanggan', value: customerStats.total, icon: Users, color: 'text-primary' },
        { label: 'Aktif', value: customerStats.active, icon: UserCheck, color: 'text-success' },
        { label: 'Nonaktif', value: customerStats.inactive, icon: UserX, color: 'text-muted' },
        { label: 'Suspend', value: customerStats.suspended, icon: UserMinus, color: 'text-danger' },
      ]" :key="stat.label">
        <div class="flex items-center justify-between">
          <div>
            <p :class="['text-2xl font-bold', stat.color]">{{ formatNumber(stat.value) }}</p>
            <p class="text-xs text-muted">{{ stat.label }}</p>
          </div>
          <component :is="stat.icon" :class="['h-8 w-8 opacity-20', stat.color]" />
        </div>
      </Card>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-3">
      <SearchInput v-model="search" placeholder="Cari nama, kode, alamat, HP, ODC..." class="max-w-sm" />
      <Select v-model="odcFilter" class="h-10 min-w-[160px]">
        <option value="">Semua ODC</option>
        <option v-for="o in odcs" :key="o.id" :value="String(o.id)">{{ o.name }}</option>
      </Select>
      <Select v-model="statusFilter" class="h-10 min-w-[140px]">
        <option value="">Semua status</option>
        <option value="aktif">Aktif</option>
        <option value="pending">Pending</option>
      </Select>
      <div class="flex flex-1 justify-end gap-2">
        <input ref="fileInput" type="file" accept=".csv,.txt,.xls,.xlsx" class="hidden" @change="handleImportFile" />
        <Button variant="outline" size="sm" :disabled="importing" @click="triggerImport">
          <Upload class="h-4 w-4" /> {{ importing ? 'Mengimpor...' : 'Import' }}
        </Button>
        <Button variant="outline" size="sm" :disabled="exporting" @click="handleExport">
          <Download class="h-4 w-4" /> {{ exporting ? 'Mengekspor...' : 'Export' }}
        </Button>
        <Button size="sm" @click="openCreate">
          <Users class="h-4 w-4" /> Tambah Pelanggan
        </Button>
      </div>
    </div>

    <p class="mt-2 text-xs text-muted">
      Format CSV: Kode Pelanggan, Nama Pelanggan, Alamat, No HP, ODC
    </p>

    <Card class="mt-4 overflow-x-auto">
      <div v-if="loading" class="py-12 text-center text-sm text-muted">Memuat data...</div>
      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-border text-left text-xs text-muted">
            <th class="pb-3 pr-4 font-medium">Kode</th>
            <th class="pb-3 pr-4 font-medium">Nama</th>
            <th class="pb-3 pr-4 font-medium">Alamat</th>
            <th class="pb-3 pr-4 font-medium">ODC</th>
            <th class="pb-3 pr-4 font-medium">Status</th>
            <th class="pb-3 pr-4 font-medium">Telepon</th>
            <th class="pb-3 font-medium text-right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="customer in customers"
            :key="customer.id"
            class="cursor-pointer border-b border-border/50 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
            @click="openCustomer(customer)"
          >
            <td class="py-3 pr-4 font-mono text-xs text-primary">{{ customer.customer_code }}</td>
            <td class="py-3 pr-4 font-medium">{{ customer.name }}</td>
            <td class="py-3 pr-4 text-muted">{{ customer.address || '—' }}</td>
            <td class="py-3 pr-4 text-xs text-muted">{{ customer.odc?.name ?? '—' }}</td>
            <td class="py-3 pr-4">
              <Badge :variant="statusVariant(customer.status)">{{ statusLabel[customer.status] }}</Badge>
            </td>
            <td class="py-3 pr-4 text-muted">{{ customer.phone ?? '—' }}</td>
            <td class="py-3 text-right" @click.stop>
              <div class="flex justify-end gap-1">
                <button
                  type="button"
                  class="rounded-lg p-1.5 text-muted hover:bg-muted"
                  title="Edit"
                  @click="openEdit(customer)"
                >
                  <Pencil class="h-4 w-4" />
                </button>
                <button
                  type="button"
                  class="rounded-lg p-1.5 text-danger hover:bg-danger/10"
                  title="Hapus"
                  @click="requestDelete(customer)"
                >
                  <Trash2 class="h-4 w-4" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!customers.length">
            <td colspan="7" class="py-12 text-center text-sm text-muted">Belum ada data pelanggan.</td>
          </tr>
        </tbody>
      </table>
    </Card>

    <Modal
      :open="modalOpen"
      :title="editingId ? 'Edit Pelanggan' : 'Tambah Pelanggan'"
      size="lg"
      @close="modalOpen = false"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Kode Pelanggan</label>
          <Input v-model="form.customer_code" required />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Nama Pelanggan</label>
          <Input v-model="form.name" required />
        </div>
        <div class="sm:col-span-2">
          <label class="mb-1.5 block text-sm font-medium text-foreground">Alamat</label>
          <Input v-model="form.address" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">No HP</label>
          <Input v-model="form.phone" placeholder="0812..." />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">ODC</label>
          <Select v-model="form.odc_id">
            <option value="">— Pilih ODC —</option>
            <option v-for="o in odcs" :key="o.id" :value="String(o.id)">{{ o.name }}</option>
          </Select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Status</label>
          <Select v-model="form.status" :options="[
            { value: 'active', label: 'Aktif' },
            { value: 'inactive', label: 'Nonaktif' },
            { value: 'suspended', label: 'Suspend' },
          ]" />
        </div>
      </div>
      <template #footer>
        <Button variant="outline" @click="modalOpen = false">Batal</Button>
        <Button :disabled="saving" @click="submitForm">
          {{ saving ? 'Menyimpan...' : (editingId ? 'Simpan Perubahan' : 'Simpan') }}
        </Button>
      </template>
    </Modal>

    <Modal :open="importModalOpen" title="Hasil Import" @close="importModalOpen = false">
      <div v-if="importResult" class="space-y-3 text-sm">
        <p>
          <span class="font-medium text-success">{{ importResult.success }} berhasil</span>
          ·
          <span class="font-medium text-danger">{{ importResult.failed }} gagal</span>
        </p>
        <div v-if="importResult.errors.length" class="max-h-48 overflow-y-auto rounded-xl border border-border bg-slate-50 p-3 text-xs dark:bg-slate-800/50">
          <p v-for="(err, i) in importResult.errors" :key="i" class="text-danger">{{ err }}</p>
        </div>
        <p v-else class="text-muted">Semua baris berhasil diimpor.</p>
      </div>
      <template #footer>
        <Button @click="importModalOpen = false">Tutup</Button>
      </template>
    </Modal>

    <Modal
      :open="!!deleteTarget"
      title="Hapus Pelanggan"
      :subtitle="deleteTarget ? `Yakin ingin menghapus ${deleteTarget.name} (${deleteTarget.customer_code})? Tindakan ini tidak bisa dibatalkan.` : undefined"
      size="sm"
      @close="deleteTarget = null"
    >
      <p class="text-sm text-muted">
        Data pelanggan akan dihapus permanen dari master.
      </p>
      <template #footer>
        <Button type="button" variant="outline" :disabled="deleting" @click="deleteTarget = null">Batal</Button>
        <Button type="button" variant="danger" :disabled="deleting" @click="confirmDelete">
          {{ deleting ? 'Menghapus...' : 'Hapus' }}
        </Button>
      </template>
    </Modal>

    <Teleport to="body">
      <Transition enter-active-class="transition duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100"
        leave-active-class="transition duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="drawerOpen" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm" @click="drawerOpen = false" />
      </Transition>
      <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="translate-x-full"
        enter-to-class="translate-x-0" leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-x-0" leave-to-class="translate-x-full">
        <div v-if="drawerOpen && selectedCustomer"
          class="fixed right-0 top-0 z-50 flex h-full w-full max-w-lg flex-col border-l border-border bg-card shadow-2xl">
          <div class="border-b border-border p-6">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-lg font-semibold">{{ selectedCustomer.name }}</h3>
                <p class="font-mono text-sm text-primary">{{ selectedCustomer.customer_code }}</p>
              </div>
              <div class="flex items-center gap-1">
                <Button variant="ghost" size="icon" title="Edit" @click="openEdit(selectedCustomer)">
                  <Pencil class="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="icon" title="Hapus" @click="requestDelete(selectedCustomer)">
                  <Trash2 class="h-4 w-4 text-danger" />
                </Button>
                <Button variant="ghost" size="icon" @click="drawerOpen = false">✕</Button>
              </div>
            </div>
            <Badge :variant="statusVariant(selectedCustomer.status)" class="mt-2">
              {{ statusLabel[selectedCustomer.status] }}
            </Badge>
          </div>
          <div class="flex-1 overflow-y-auto p-6">
            <div class="grid grid-cols-2 gap-3">
              <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50">
                <p class="text-xs text-muted">ODC</p>
                <p class="font-semibold">{{ selectedCustomer.odc?.name ?? '—' }}</p>
              </div>
              <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50">
                <p class="text-xs text-muted">Telepon</p>
                <p class="font-semibold">{{ selectedCustomer.phone ?? '—' }}</p>
              </div>
              <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50 sm:col-span-2">
                <p class="text-xs text-muted">Alamat</p>
                <p class="text-sm font-semibold">{{ selectedCustomer.address || '—' }}</p>
              </div>
            </div>

            <div class="mt-5">
              <h4 class="mb-2 text-sm font-semibold text-foreground">Riwayat Komplain</h4>
              <ComplaintHistoryPanel
                :total="complaintHistoryTotal"
                :days="90"
                :items="complaintHistory"
                :summary="complaintHistorySummary"
                :loading="complaintHistoryLoading"
              />
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </AppLayout>
</template>

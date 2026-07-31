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
import { activationApi, customerApi, technicianApi, type ActivationItem, type Customer } from '@/services/api'
import { Plus, Zap } from 'lucide-vue-next'

const search = ref('')
const statusFilter = ref('all')
const items = ref<ActivationItem[]>([])
const stats = ref<Record<string, number>>({})
const loading = ref(true)
const showCreate = ref(false)
const saving = ref(false)
const customers = ref<Customer[]>([])
const technicians = ref<{ id: number; name: string }[]>([])

const form = ref({
  customer_id: '', customer_name: '', phone: '', package: '50 Mbps',
  area: '', odp: '', address: '', scheduled_at: '', assigned_to: '', notes: '',
})

const statusTabs = [
  { key: 'all', label: 'Semua' },
  { key: 'pending', label: 'Pending' },
  { key: 'scheduled', label: 'Terjadwal' },
  { key: 'in_progress', label: 'Diproses' },
  { key: 'completed', label: 'Selesai' },
]

const statusLabel: Record<string, string> = {
  pending: 'Pending', scheduled: 'Terjadwal', in_progress: 'Diproses',
  completed: 'Selesai', cancelled: 'Dibatalkan',
}

const statusVariant = (s: string) => {
  const map: Record<string, 'warning' | 'info' | 'success' | 'secondary' | 'danger'> = {
    pending: 'warning', scheduled: 'info', in_progress: 'info', completed: 'success', cancelled: 'secondary',
  }
  return map[s] ?? 'secondary'
}

async function load() {
  loading.value = true
  try {
    const [listRes, statsRes] = await Promise.all([
      activationApi.list({ search: search.value || undefined, status: statusFilter.value }),
      activationApi.stats(),
    ])
    items.value = listRes.data.data
    stats.value = statsRes.data
  } finally {
    loading.value = false
  }
}

async function openCreate() {
  const [custRes, techRes] = await Promise.all([
    customerApi.list({ per_page: 50 }),
    technicianApi.list(),
  ])
  customers.value = custRes.data.data
  technicians.value = techRes.data.data
  form.value = { customer_id: '', customer_name: '', phone: '', package: '50 Mbps', area: '', odp: '', address: '', scheduled_at: '', assigned_to: '', notes: '' }
  showCreate.value = true
}

async function submit() {
  saving.value = true
  try {
    await activationApi.create({
      customer_id: form.value.customer_id ? Number(form.value.customer_id) : undefined,
      customer_name: form.value.customer_name,
      phone: form.value.phone || undefined,
      package: form.value.package,
      area: form.value.area || undefined,
      odp: form.value.odp || undefined,
      address: form.value.address || undefined,
      scheduled_at: form.value.scheduled_at || undefined,
      assigned_to: form.value.assigned_to ? Number(form.value.assigned_to) : undefined,
      notes: form.value.notes || undefined,
    })
    showCreate.value = false
    await load()
  } finally {
    saving.value = false
  }
}

async function updateStatus(item: ActivationItem, status: string) {
  await activationApi.update(item.id, { status })
  await load()
}

let searchTimeout: ReturnType<typeof setTimeout>
watch([search, statusFilter], () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(load, 400)
})

onMounted(load)
</script>

<template>
  <AppLayout title="Aktivasi" subtitle="Manajemen aktivasi pelanggan baru">
    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-5">
      <Card padding="sm" v-for="item in [
        { label: 'Total', key: 'total' }, { label: 'Pending', key: 'pending' },
        { label: 'Terjadwal', key: 'scheduled' }, { label: 'Diproses', key: 'in_progress' },
        { label: 'Selesai', key: 'completed' },
      ]" :key="item.key">
        <p class="text-xl font-bold text-primary">{{ stats[item.key] ?? 0 }}</p>
        <p class="text-xs text-muted">{{ item.label }}</p>
      </Card>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex flex-wrap gap-1 rounded-xl border border-border p-1">
        <button
          v-for="tab in statusTabs" :key="tab.key"
          :class="['rounded-lg px-3 py-1.5 text-xs font-medium transition-colors', statusFilter === tab.key ? 'bg-primary text-white' : 'text-muted']"
          @click="statusFilter = tab.key"
        >{{ tab.label }}</button>
      </div>
      <Button @click="openCreate"><Plus class="h-4 w-4" /> Tambah Aktivasi</Button>
    </div>

    <div class="mt-4">
      <SearchInput v-model="search" placeholder="Cari referensi, nama..." class="max-w-sm" />
    </div>

    <div class="mt-6 space-y-3">
      <Card v-for="item in items" :key="item.id" hover>
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <Zap class="h-4 w-4 text-warning" />
              <span class="font-mono text-sm text-primary">{{ item.reference }}</span>
              <Badge :variant="statusVariant(item.status)">{{ statusLabel[item.status] ?? item.status }}</Badge>
            </div>
            <h3 class="mt-2 font-semibold">{{ item.customer_name }}</h3>
            <p class="text-xs text-muted">{{ item.package }} · {{ item.area ?? '—' }}</p>
            <p v-if="item.assignee" class="mt-1 text-xs text-muted">Teknisi: {{ item.assignee.name }}</p>
          </div>
          <div class="flex gap-2">
            <Button v-if="item.status === 'pending'" size="sm" variant="outline" @click="updateStatus(item, 'in_progress')">Proses</Button>
            <Button v-if="item.status === 'in_progress'" size="sm" @click="updateStatus(item, 'completed')">Selesai</Button>
          </div>
        </div>
      </Card>
    </div>

    <Modal :open="showCreate" title="Tambah Aktivasi" size="lg" @close="showCreate = false">
      <div class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm font-medium">Pelanggan</label>
            <Select v-model="form.customer_id">
              <option value="">— Baru —</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
            </Select>
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium">Nama *</label>
            <Input v-model="form.customer_name" required />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium">Paket *</label>
            <Input v-model="form.package" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium">Area</label>
            <Input v-model="form.area" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium">ODP</label>
            <Input v-model="form.odp" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium">Jadwal</label>
            <Input v-model="form.scheduled_at" type="datetime-local" />
          </div>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium">Alamat</label>
          <Textarea v-model="form.address" />
        </div>
      </div>
      <template #footer>
        <Button variant="outline" @click="showCreate = false">Batal</Button>
        <Button :disabled="saving || !form.customer_name" @click="submit">{{ saving ? 'Menyimpan...' : 'Simpan' }}</Button>
      </template>
    </Modal>
  </AppLayout>
</template>

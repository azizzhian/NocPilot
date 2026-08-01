<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import Modal from '@/components/ui/Modal.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { Plus, Pencil, Trash2, PlugZap } from 'lucide-vue-next'

export interface ColumnDef {
  key: string
  label: string
  render?: (row: Record<string, unknown>) => string
}

export interface FieldDef {
  key: string
  label: string
  type?: 'text' | 'number' | 'select' | 'textarea' | 'password'
  required?: boolean
  placeholder?: string
  default?: string | number
  options?: { value: string | number; label: string }[]
  hiddenWhen?: (form: Record<string, unknown>) => boolean
}

export interface ConnectionTestResult {
  success: boolean
  message: string
  latency_ms?: number
  identity?: string | null
  host?: string
  port?: number
  username?: string
  username_source?: 'stored' | 'input'
  password_source?: 'stored' | 'input'
  password_length?: number
}

export interface ConnectionTestOptions {
  username?: string
  password?: string
  ip?: string
  api_port?: number
  monitor_via?: string
  snmp_community?: string
  snmp_port?: number
}

const props = defineProps<{
  title: string
  subtitle: string
  columns: ColumnDef[]
  fields: FieldDef[]
  list: (params?: Record<string, unknown>) => Promise<unknown>
  create: (data: Record<string, unknown>) => Promise<unknown>
  update: (id: number, data: Record<string, unknown>) => Promise<unknown>
  destroy: (id: number) => Promise<unknown>
  testConnection?: (id: number, opts?: ConnectionTestOptions) => Promise<ConnectionTestResult>
}>()

const search = ref('')
const loading = ref(true)
const saving = ref(false)
const rows = ref<Record<string, unknown>[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const modalOpen = ref(false)
const editingId = ref<number | null>(null)
const form = ref<Record<string, unknown>>({})
const error = ref('')
const testingId = ref<number | null>(null)
const connectionResult = ref<ConnectionTestResult | null>(null)
const deleteTarget = ref<Record<string, unknown> | null>(null)
const deleting = ref(false)

function emptyForm() {
  const obj: Record<string, unknown> = {}
  props.fields.forEach((f) => {
    if (f.default !== undefined) {
      obj[f.key] = f.default
    } else {
      obj[f.key] = f.type === 'number' ? '' : ''
    }
  })
  return obj
}

function cellValue(row: Record<string, unknown>, col: ColumnDef) {
  if (col.render) return col.render(row)
  const val = row[col.key]
  if (val === null || val === undefined || val === '') return '—'
  return String(val)
}

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const res = await props.list({ search: search.value || undefined, page }) as { data: { data?: Record<string, unknown>[]; current_page?: number; last_page?: number } }
    rows.value = res.data.data ?? []
    currentPage.value = res.data.current_page ?? 1
    lastPage.value = res.data.last_page ?? 1
  } catch {
    error.value = 'Gagal memuat data.'
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  form.value = emptyForm()
  connectionResult.value = null
  error.value = ''
  modalOpen.value = true
}

function openEdit(row: Record<string, unknown>) {
  editingId.value = row.id as number
  form.value = { ...emptyForm(), ...row }
  connectionResult.value = null
  error.value = ''
  modalOpen.value = true
}

function buildPayload() {
  const payload: Record<string, unknown> = {}
  props.fields.forEach((f) => {
    const value = form.value[f.key]
    if (f.type === 'number') {
      if (value === '' || value === null || value === undefined) {
        return
      }
      payload[f.key] = Number(value)
      return
    }
    payload[f.key] = value
  })
  if (editingId.value && (payload.password === '' || payload.password === null || payload.password === undefined)) {
    delete payload.password
  }
  if (editingId.value && (payload.snmp_community === '' || payload.snmp_community === null || payload.snmp_community === undefined)) {
    delete payload.snmp_community
  }
  return payload
}

async function submitForm() {
  saving.value = true
  error.value = ''
  try {
    const payload = buildPayload()
    if (editingId.value) {
      await props.update(editingId.value, payload)
    } else {
      await props.create(payload)
    }
    modalOpen.value = false
    await load(currentPage.value)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const fieldErrors = err.response?.data?.errors
    const firstFieldError = fieldErrors
      ? Object.values(fieldErrors).flat().find(Boolean)
      : undefined
    const message = firstFieldError ?? err.response?.data?.message ?? ''
    error.value = message.includes('SQLSTATE')
      ? 'Gagal menyimpan data. Periksa kembali isian formulir.'
      : (message || 'Gagal menyimpan data.')
  } finally {
    saving.value = false
  }
}

function deleteLabel(row: Record<string, unknown>) {
  const name = row.name ?? row.code ?? row.customer_name ?? row.id
  return String(name ?? 'data ini')
}

function requestDelete(row: Record<string, unknown>) {
  deleteTarget.value = row
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  error.value = ''
  try {
    await props.destroy(deleteTarget.value.id as number)
    deleteTarget.value = null
    await load(currentPage.value)
  } catch {
    error.value = 'Gagal menghapus data.'
  } finally {
    deleting.value = false
  }
}

async function checkConnection(id: number, opts?: ConnectionTestOptions) {
  if (!props.testConnection) return
  testingId.value = id
  connectionResult.value = null
  try {
    connectionResult.value = await props.testConnection(id, opts)
  } catch (e: unknown) {
    const err = e as { response?: { data?: ConnectionTestResult } }
    connectionResult.value = err.response?.data ?? {
      success: false,
      message: 'Gagal mengecek koneksi.',
    }
  } finally {
    testingId.value = null
    if (connectionResult.value?.success) {
      await load(currentPage.value)
    }
  }
}

function checkConnectionFromForm() {
  if (!editingId.value) return
  const password = form.value.password
  const username = form.value.username
  const ip = form.value.ip
  const apiPort = form.value.api_port
  const monitorVia = form.value.monitor_via
  const snmpCommunity = form.value.snmp_community
  const snmpPort = form.value.snmp_port
  checkConnection(editingId.value, {
    username: typeof username === 'string' && username.length > 0 ? username : undefined,
    password: typeof password === 'string' && password.length > 0 ? password : undefined,
    ip: typeof ip === 'string' && ip.length > 0 ? ip : undefined,
    api_port: typeof apiPort === 'number' ? apiPort : (typeof apiPort === 'string' && apiPort !== '' ? Number(apiPort) : undefined),
    monitor_via: typeof monitorVia === 'string' && monitorVia.length > 0 ? monitorVia : undefined,
    snmp_community: typeof snmpCommunity === 'string' && snmpCommunity.length > 0 ? snmpCommunity : undefined,
    snmp_port: typeof snmpPort === 'number' ? snmpPort : (typeof snmpPort === 'string' && snmpPort !== '' ? Number(snmpPort) : undefined),
  })
}

function checkConnectionFromRow(row: Record<string, unknown>) {
  const via = String(row.monitor_via ?? 'api')
  if (via === 'snmp') {
    if (!row.has_snmp_community) {
      connectionResult.value = {
        success: false,
        message: 'SNMP community belum tersimpan. Edit router dan isi community, lalu simpan.',
      }
      return
    }
  } else if (!row.has_api_password) {
    connectionResult.value = {
      success: false,
      message: 'Password API belum tersimpan. Edit router dan isi password, lalu simpan.',
    }
    return
  }
  checkConnection(row.id as number)
}

function statusVariant(status: unknown) {
  const s = String(status ?? '').toLowerCase()
  if (['online', 'active'].includes(s)) return 'success'
  if (['offline', 'inactive', 'los'].includes(s)) return 'danger'
  if (['warning', 'full', 'maintenance'].includes(s)) return 'warning'
  return 'secondary'
}

let searchTimeout: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => load(1), 400)
})

onMounted(() => load())
</script>

<template>
  <AppLayout :title="title" :subtitle="subtitle">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <SearchInput v-model="search" placeholder="Cari..." class="max-w-sm" />
      <Button @click="openCreate"><Plus class="h-4 w-4" /> Tambah</Button>
    </div>

    <div v-if="connectionResult && !modalOpen" :class="[
      'mb-4 rounded-xl border px-4 py-3 text-sm',
      connectionResult.success
        ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300'
        : 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300',
    ]">
      {{ connectionResult.message }}
      <span v-if="connectionResult.username" class="ml-1 text-xs opacity-80">
        · user: {{ connectionResult.username }}
        <template v-if="connectionResult.username_source">({{ connectionResult.username_source === 'input' ? 'dari form' : 'tersimpan' }})</template>
      </span>
      <span v-if="connectionResult.password_source" class="ml-1 text-xs opacity-80">
        · password: {{ connectionResult.password_source === 'input' ? 'dari form' : 'tersimpan' }}
        ({{ connectionResult.password_length }} karakter)
      </span>
      <span v-if="connectionResult.success && connectionResult.latency_ms" class="ml-1 text-xs opacity-80">
        · {{ connectionResult.latency_ms }} ms{{ connectionResult.identity ? ` · ${connectionResult.identity}` : '' }}
      </span>
    </div>

    <div v-if="error && !modalOpen" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
      {{ error }}
    </div>

    <Card v-if="loading" class="p-6"><Skeleton class="h-64 rounded-xl" /></Card>

    <Card v-else class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-border text-left text-xs text-muted">
            <th v-for="col in columns" :key="col.key" class="pb-3 pr-4 font-medium">{{ col.label }}</th>
            <th class="pb-3 font-medium">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="String(row.id)" class="border-b border-border/50 hover:bg-muted/30">
            <td v-for="col in columns" :key="col.key" class="py-3 pr-4">
              <Badge v-if="col.key === 'status'" :variant="statusVariant(row.status)">{{ cellValue(row, col) }}</Badge>
              <span v-else class="text-foreground">{{ cellValue(row, col) }}</span>
            </td>
            <td class="py-3">
              <div class="flex gap-1">
                <button
                  v-if="testConnection"
                  type="button"
                  class="rounded-lg p-1.5 text-primary hover:bg-primary/10 disabled:opacity-50"
                  :title="'Cek koneksi'"
                  :disabled="testingId === row.id"
                  @click="checkConnectionFromRow(row)"
                >
                  <PlugZap class="h-4 w-4" :class="testingId === row.id && 'animate-pulse'" />
                </button>
                <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-muted" @click="openEdit(row)"><Pencil class="h-4 w-4" /></button>
                <button type="button" class="rounded-lg p-1.5 text-danger hover:bg-danger/10" @click="requestDelete(row)"><Trash2 class="h-4 w-4" /></button>
              </div>
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td :colspan="columns.length + 1" class="py-10 text-center text-muted">Belum ada data.</td>
          </tr>
        </tbody>
      </table>
    </Card>

    <div v-if="lastPage > 1" class="mt-4 flex justify-center gap-2">
      <Button variant="outline" size="sm" :disabled="currentPage <= 1" @click="load(currentPage - 1)">Sebelumnya</Button>
      <span class="flex items-center px-3 text-sm text-muted">{{ currentPage }} / {{ lastPage }}</span>
      <Button variant="outline" size="sm" :disabled="currentPage >= lastPage" @click="load(currentPage + 1)">Selanjutnya</Button>
    </div>

    <Modal
      :open="modalOpen"
      :title="editingId ? 'Edit Data' : 'Tambah Data'"
      size="lg"
      @close="modalOpen = false"
    >
      <form class="space-y-4" @submit.prevent="submitForm">
        <div
          v-if="error"
          class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
        >
          {{ error }}
        </div>
        <div v-for="field in fields" v-show="!field.hiddenWhen?.(form)" :key="field.key">
          <label class="mb-1.5 block text-sm font-medium text-foreground">{{ field.label }}</label>
          <Select
            v-if="field.type === 'select'"
            v-model="form[field.key] as string"
            :options="field.options"
            class="w-full"
          />
          <Textarea
            v-else-if="field.type === 'textarea'"
            v-model="form[field.key] as string"
            :rows="3"
          />
          <Input
            v-else
            v-model="form[field.key] as string"
            :type="field.type === 'number' ? 'number' : field.type === 'password' ? 'password' : 'text'"
            :placeholder="field.placeholder"
            :autocomplete="field.type === 'password' ? 'new-password' : undefined"
          />
        </div>
        <div
          v-if="connectionResult"
          :class="[
            'rounded-xl border px-4 py-3 text-sm',
            connectionResult.success
              ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300'
              : 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300',
          ]"
        >
          {{ connectionResult.message }}
          <span v-if="connectionResult.username" class="ml-1 text-xs opacity-80">
        · user: {{ connectionResult.username }}
        <template v-if="connectionResult.username_source">({{ connectionResult.username_source === 'input' ? 'dari form' : 'tersimpan' }})</template>
      </span>
          <span v-if="connectionResult.password_source" class="ml-1 text-xs opacity-80">
            · password: {{ connectionResult.password_source === 'input' ? 'dari form' : 'tersimpan' }}
            ({{ connectionResult.password_length }} karakter)
          </span>
          <span v-if="connectionResult.success && connectionResult.latency_ms" class="ml-1 text-xs opacity-80">
            · {{ connectionResult.latency_ms }} ms{{ connectionResult.identity ? ` · ${connectionResult.identity}` : '' }}
          </span>
        </div>
      </form>
      <template #footer>
        <Button
          v-if="testConnection && editingId"
          variant="outline"
          :disabled="testingId !== null"
          @click="checkConnectionFromForm"
        >
          <PlugZap class="h-4 w-4" />
          {{ testingId !== null ? 'Mengecek...' : 'Cek Koneksi' }}
        </Button>
        <Button variant="outline" @click="modalOpen = false">Batal</Button>
        <Button :disabled="saving" @click="submitForm">{{ saving ? 'Menyimpan...' : 'Simpan' }}</Button>
      </template>
    </Modal>

    <Modal
      :open="!!deleteTarget"
      title="Hapus data?"
      :subtitle="deleteTarget ? `Yakin ingin menghapus ${deleteLabel(deleteTarget)}? Tindakan ini tidak bisa dibatalkan.` : undefined"
      size="sm"
      @close="deleteTarget = null"
    >
      <p class="text-sm text-muted">
        Data akan dihapus permanen.
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

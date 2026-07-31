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
import Skeleton from '@/components/ui/Skeleton.vue'
import { userApi, roleApi, type ApiUser } from '@/services/api'
import { Plus, Shield } from 'lucide-vue-next'

const search = ref('')
const loading = ref(true)
const saving = ref(false)
const users = ref<ApiUser[]>([])
const roles = ref<{ name: string; label: string; permissions: string[] }[]>([])
const permissions = ref<string[]>([])
const modalOpen = ref(false)
const editingId = ref<number | null>(null)
const error = ref('')
const deleteTarget = ref<ApiUser | null>(null)
const deleting = ref(false)
const form = ref({
  name: '',
  username: '',
  email: '',
  password: '',
  department: '',
  role: 'noc',
  status: 'active',
  telegram_id: '',
  telegram_username: '',
})

const roleVariant = (r: string) => {
  const map: Record<string, 'danger' | 'default' | 'info' | 'success' | 'warning' | 'secondary'> = {
    administrator: 'danger', noc: 'default', engineer: 'info', teknisi: 'success', manager: 'warning', finance: 'secondary',
  }
  return map[r] ?? 'secondary'
}

function formatLogin(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('id-ID')
}

async function load() {
  loading.value = true
  try {
    const [usersRes, rolesRes] = await Promise.all([
      userApi.list({ search: search.value || undefined }),
      roleApi.list(),
    ])
    users.value = usersRes.data.data
    roles.value = rolesRes.data.roles
    permissions.value = rolesRes.data.permissions
  } finally {
    loading.value = false
  }
}

function extractError(e: unknown, fallback: string): string {
  const err = e as {
    response?: {
      data?: {
        message?: string
        errors?: Record<string, string[]>
      }
    }
  }
  const fieldErrors = err.response?.data?.errors
  if (fieldErrors) {
    const first = Object.values(fieldErrors).flat()[0]
    if (first) return first
  }
  return err.response?.data?.message ?? fallback
}

function openCreate() {
  editingId.value = null
  error.value = ''
  form.value = {
    name: '',
    username: '',
    email: '',
    password: '',
    department: '',
    role: 'noc',
    status: 'active',
    telegram_id: '',
    telegram_username: '',
  }
  modalOpen.value = true
}

function openEdit(user: ApiUser) {
  editingId.value = user.id
  error.value = ''
  form.value = {
    name: user.name,
    username: user.username ?? '',
    email: user.email,
    password: '',
    department: user.department ?? '',
    role: user.role,
    status: user.status,
    telegram_id: user.telegram_id ?? '',
    telegram_username: user.telegram_username ?? '',
  }
  modalOpen.value = true
}

async function submitForm() {
  saving.value = true
  error.value = ''
  try {
    const payload: Record<string, unknown> = {
      ...form.value,
      telegram_id: form.value.telegram_id.trim() || null,
      telegram_username: form.value.telegram_username.trim() || null,
      email: form.value.email.trim() || null,
    }
    if (editingId.value && !payload.password) delete payload.password
    if (editingId.value) {
      await userApi.update(editingId.value, payload)
    } else {
      await userApi.create(payload)
    }
    modalOpen.value = false
    await load()
  } catch (e: unknown) {
    error.value = extractError(e, 'Gagal menyimpan user.')
  } finally {
    saving.value = false
  }
}

function requestDelete(user: ApiUser) {
  deleteTarget.value = user
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  error.value = ''
  try {
    await userApi.destroy(deleteTarget.value.id)
    deleteTarget.value = null
    await load()
  } catch (e: unknown) {
    error.value = extractError(e, 'Gagal menghapus user.')
  } finally {
    deleting.value = false
  }
}

let searchTimeout: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(load, 400)
})

onMounted(load)
</script>

<template>
  <AppLayout title="Kelola User" subtitle="Manajemen pengguna enterprise dengan role & permission">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <SearchInput v-model="search" placeholder="Cari user..." class="max-w-sm" />
      <Button @click="openCreate"><Plus class="h-4 w-4" /> Tambah User</Button>
    </div>

    <div v-if="error && !modalOpen" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ error }}</div>

    <Card v-if="loading" class="p-6"><Skeleton class="h-64 rounded-xl" /></Card>

    <Card v-else class="overflow-x-auto mb-6">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-border text-left text-xs text-muted">
            <th class="pb-3 pr-4 font-medium">User</th>
            <th class="pb-3 pr-4 font-medium">Role</th>
            <th class="pb-3 pr-4 font-medium">Department</th>
            <th class="pb-3 pr-4 font-medium">Last Login</th>
            <th class="pb-3 pr-4 font-medium">Status</th>
            <th class="pb-3 font-medium">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id" class="border-b border-border/50 hover:bg-muted/30">
            <td class="py-3 pr-4">
              <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">{{ user.name.charAt(0) }}</div>
                <div>
                  <p class="font-medium text-foreground">{{ user.name }}</p>
                  <p class="text-xs text-muted">@{{ user.username }}{{ user.email ? ` · ${user.email}` : '' }}</p>
                  <p v-if="user.telegram_id" class="text-xs text-muted">TG: {{ user.telegram_username ? `@${user.telegram_username}` : user.telegram_id }}</p>
                </div>
              </div>
            </td>
            <td class="py-3 pr-4"><Badge :variant="roleVariant(user.role)">{{ user.role }}</Badge></td>
            <td class="py-3 pr-4 text-muted">{{ user.department || '—' }}</td>
            <td class="py-3 pr-4 text-xs text-muted">{{ formatLogin(user.last_login_at) }}</td>
            <td class="py-3 pr-4">
              <Badge :variant="user.status === 'active' ? 'success' : 'secondary'">{{ user.status === 'active' ? 'Aktif' : 'Nonaktif' }}</Badge>
            </td>
            <td class="py-3">
              <div class="flex gap-2">
                <Button variant="outline" size="sm" @click="openEdit(user)">Edit</Button>
                <Button variant="outline" size="sm" class="text-danger" @click="requestDelete(user)">Hapus</Button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </Card>

    <Card>
      <div class="flex items-center gap-2 mb-4">
        <Shield class="h-4 w-4 text-primary" />
        <h3 class="text-sm font-semibold text-foreground">Permission Matrix</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="border-b border-border text-muted">
              <th class="pb-2 pr-3 text-left font-medium">Permission</th>
              <th v-for="role in roles" :key="role.name" class="pb-2 px-2 text-center font-medium capitalize">{{ role.name }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="perm in permissions" :key="perm" class="border-b border-border/50">
              <td class="py-2 pr-3 font-medium text-foreground">{{ perm }}</td>
              <td v-for="role in roles" :key="role.name" class="py-2 px-2 text-center">
                <span v-if="role.permissions.includes(perm)" class="text-success">✓</span>
                <span v-else class="text-muted">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>

    <Modal :open="modalOpen" :title="editingId ? 'Edit User' : 'Tambah User'" @close="modalOpen = false">
      <div class="space-y-4">
        <div
          v-if="error"
          class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
        >
          {{ error }}
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Nama</label>
          <Input v-model="form.name" required />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Username</label>
          <Input v-model="form.username" autocomplete="username" required />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Email (opsional)</label>
          <Input v-model="form.email" type="email" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">{{ editingId ? 'Password (kosongkan jika tidak diubah)' : 'Password' }}</label>
          <Input v-model="form.password" type="password" :required="!editingId" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-foreground">Telegram ID</label>
            <Input v-model="form.telegram_id" placeholder="123456789" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-foreground">Telegram Username</label>
            <Input v-model="form.telegram_username" placeholder="@username" />
          </div>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Department</label>
          <Input v-model="form.department" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-foreground">Role</label>
            <Select v-model="form.role" :options="roles.map((r) => ({ value: r.name, label: r.label }))" class="w-full" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-foreground">Status</label>
            <Select v-model="form.status" :options="[{ value: 'active', label: 'Aktif' }, { value: 'inactive', label: 'Nonaktif' }]" class="w-full" />
          </div>
        </div>
      </div>
      <template #footer>
        <Button variant="outline" @click="modalOpen = false">Batal</Button>
        <Button :disabled="saving" @click="submitForm">{{ saving ? 'Menyimpan...' : 'Simpan' }}</Button>
      </template>
    </Modal>

    <Modal
      :open="!!deleteTarget"
      title="Hapus user?"
      :subtitle="deleteTarget ? `Yakin ingin menghapus ${deleteTarget.name}? Tindakan ini tidak bisa dibatalkan.` : undefined"
      size="sm"
      @close="deleteTarget = null"
    >
      <p class="text-sm text-muted">
        Akun user akan dihapus dan tidak bisa login lagi.
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

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const saving = ref(false)
const error = ref('')
const success = ref('')

const form = ref({
  name: '',
  username: '',
  email: '',
  department: '',
  telegram_id: '',
  telegram_username: '',
  current_password: '',
  password: '',
  password_confirmation: '',
})

function loadForm() {
  const u = auth.user
  if (!u) return
  form.value = {
    name: u.name ?? '',
    username: u.username ?? '',
    email: u.email?.endsWith('@nocpilot.local') ? '' : (u.email ?? ''),
    department: u.department === '—' ? '' : (u.department ?? ''),
    telegram_id: u.telegram_id ?? '',
    telegram_username: u.telegram_username ?? '',
    current_password: '',
    password: '',
    password_confirmation: '',
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

async function submit() {
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    const payload: Record<string, unknown> = {
      name: form.value.name.trim(),
      username: form.value.username.trim(),
      email: form.value.email.trim() || null,
      department: form.value.department.trim() || null,
      telegram_id: form.value.telegram_id.trim() || null,
      telegram_username: form.value.telegram_username.trim() || null,
    }
    if (form.value.password) {
      payload.current_password = form.value.current_password
      payload.password = form.value.password
      payload.password_confirmation = form.value.password_confirmation
    }
    const data = await auth.updateProfile(payload)
    success.value = data.message
    form.value.current_password = ''
    form.value.password = ''
    form.value.password_confirmation = ''
    loadForm()
  } catch (e: unknown) {
    error.value = extractError(e, 'Gagal menyimpan profil.')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  if (!auth.user) await auth.fetchUser()
  loadForm()
})
</script>

<template>
  <AppLayout title="Profil Saya" subtitle="Perbarui data akun Anda">
    <div class="mx-auto max-w-2xl">
      <Card class="p-6">
        <div class="mb-6 flex items-center gap-3">
          <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary text-lg font-bold text-white">
            {{ form.name.charAt(0) || 'U' }}
          </div>
          <div>
            <p class="font-semibold text-foreground">{{ form.name || '—' }}</p>
            <p class="text-xs text-muted capitalize">{{ auth.user?.role }}</p>
          </div>
        </div>

        <div
          v-if="error"
          class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
        >
          {{ error }}
        </div>
        <div
          v-if="success"
          class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
        >
          {{ success }}
        </div>

        <form class="space-y-4" @submit.prevent="submit">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-foreground">Nama</label>
            <Input v-model="form.name" required />
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-foreground">Username</label>
              <Input v-model="form.username" autocomplete="username" required />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-foreground">Email (opsional)</label>
              <Input v-model="form.email" type="email" autocomplete="email" />
            </div>
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-foreground">Department</label>
            <Input v-model="form.department" />
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-foreground">Telegram ID</label>
              <Input v-model="form.telegram_id" placeholder="123456789" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-foreground">Telegram Username</label>
              <Input v-model="form.telegram_username" placeholder="@username" />
            </div>
          </div>

          <div class="border-t border-border pt-4">
            <p class="mb-3 text-sm font-semibold text-foreground">Ubah Password</p>
            <p class="mb-3 text-xs text-muted">Kosongkan jika tidak ingin mengubah password.</p>
            <div class="space-y-3">
              <div>
                <label class="mb-1.5 block text-sm font-medium text-foreground">Password saat ini</label>
                <Input v-model="form.current_password" type="password" autocomplete="current-password" />
              </div>
              <div class="grid gap-4 sm:grid-cols-2">
                <div>
                  <label class="mb-1.5 block text-sm font-medium text-foreground">Password baru</label>
                  <Input v-model="form.password" type="password" autocomplete="new-password" />
                </div>
                <div>
                  <label class="mb-1.5 block text-sm font-medium text-foreground">Konfirmasi password</label>
                  <Input v-model="form.password_confirmation" type="password" autocomplete="new-password" />
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <Button type="submit" :disabled="saving">
              {{ saving ? 'Menyimpan...' : 'Simpan Profil' }}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  </AppLayout>
</template>

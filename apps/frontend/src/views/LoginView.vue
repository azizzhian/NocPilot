<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { Zap, Eye, EyeOff } from 'lucide-vue-next'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Card from '@/components/ui/Card.vue'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/services/api'

declare global {
  interface Window {
    onTelegramAuth?: (user: Record<string, unknown>) => void
  }
}

const router = useRouter()
const authStore = useAuthStore()

const username = ref('')
const password = ref('')
const showPassword = ref(false)
const loading = ref(false)
const error = ref('')
const telegramEnabled = ref(false)
const telegramContainer = ref<HTMLElement | null>(null)

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

async function handleLogin() {
  loading.value = true
  error.value = ''
  try {
    const success = await authStore.login(username.value, password.value)
    if (success) router.push('/')
  } catch (e: unknown) {
    error.value = extractError(e, 'Username atau password salah')
  } finally {
    loading.value = false
  }
}

async function handleTelegramAuth(user: Record<string, unknown>) {
  loading.value = true
  error.value = ''
  try {
    const success = await authStore.loginTelegram(user)
    if (success) router.push('/')
  } catch (e: unknown) {
    error.value = extractError(e, 'Login Telegram gagal')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  window.onTelegramAuth = handleTelegramAuth
  try {
    const { data } = await authApi.telegramConfig()
    telegramEnabled.value = data.enabled && !!data.bot_username
    if (!telegramEnabled.value || !telegramContainer.value || !data.bot_username) return

    const script = document.createElement('script')
    script.src = 'https://telegram.org/js/telegram-widget.js?22'
    script.async = true
    script.setAttribute('data-telegram-login', data.bot_username)
    script.setAttribute('data-size', 'large')
    script.setAttribute('data-radius', '8')
    script.setAttribute('data-onauth', 'onTelegramAuth(user)')
    script.setAttribute('data-request-access', 'write')
    telegramContainer.value.appendChild(script)
  } catch {
    telegramEnabled.value = false
  }
})

onBeforeUnmount(() => {
  delete window.onTelegramAuth
})
</script>

<template>
  <div class="flex min-h-screen">
    <!-- Left panel -->
    <div class="hidden w-1/2 flex-col justify-between bg-sidebar p-12 lg:flex">
      <div class="flex items-center gap-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary">
          <Zap class="h-6 w-6 text-white" />
        </div>
        <div>
          <h1 class="text-lg font-bold text-white">NocPilot</h1>
          <p class="text-xs text-slate-400">ISP NOC Enterprise Platform</p>
        </div>
      </div>

      <div>
        <h2 class="text-3xl font-bold leading-tight text-white">
          Pusat Kendali Jaringan<br />untuk ISP Enterprise
        </h2>
        <p class="mt-4 max-w-md text-slate-400">
          Monitoring real-time, input harian, dan analytics — semua dalam satu platform.
        </p>
        <div class="mt-8 grid grid-cols-2 gap-4">
          <div v-for="stat in [
            { value: '3,080', label: 'Pelanggan Aktif' },
            { value: '50', label: 'Router Terkelola' },
            { value: '99.7%', label: 'Uptime' },
            { value: '24/7', label: 'Monitoring' },
          ]" :key="stat.label" class="rounded-xl bg-white/5 p-4">
            <p class="text-xl font-bold text-white">{{ stat.value }}</p>
            <p class="text-xs text-slate-400">{{ stat.label }}</p>
          </div>
        </div>
      </div>

      <p class="text-xs text-slate-500">© 2026 NocPilot. Enterprise ISP NOC Platform.</p>
    </div>

    <!-- Right panel -->
    <div class="flex flex-1 items-center justify-center bg-background p-8">
      <div class="w-full max-w-md animate-slide-up">
        <div class="mb-8 lg:hidden flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary">
            <Zap class="h-6 w-6 text-white" />
          </div>
          <h1 class="text-lg font-bold">NocPilot</h1>
        </div>

        <h2 class="text-2xl font-bold">Masuk ke akun Anda</h2>
        <p class="mt-2 text-sm text-muted">Masukkan kredensial untuk mengakses dashboard NOC</p>

        <Card class="mt-8">
          <form @submit.prevent="handleLogin" class="space-y-4">
            <div>
              <label class="mb-1.5 block text-sm font-medium">Username</label>
              <Input v-model="username" type="text" autocomplete="username" placeholder="admin" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium">Password</label>
              <div class="relative">
                <Input
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  placeholder="••••••••"
                />
                <button
                  type="button"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-muted"
                  @click="showPassword = !showPassword"
                >
                  <EyeOff v-if="showPassword" class="h-4 w-4" />
                  <Eye v-else class="h-4 w-4" />
                </button>
              </div>
            </div>

            <p v-if="error" class="text-sm text-danger">{{ error }}</p>

            <Button type="submit" class="w-full" :disabled="loading">
              {{ loading ? 'Memproses...' : 'Masuk' }}
            </Button>
          </form>

          <template v-if="telegramEnabled">
            <div class="relative my-6">
              <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-border" />
              </div>
              <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-card px-2 text-muted">atau</span>
              </div>
            </div>
            <div ref="telegramContainer" class="flex justify-center min-h-[40px]" />
          </template>
        </Card>

      
      </div>
    </div>
  </div>
</template>

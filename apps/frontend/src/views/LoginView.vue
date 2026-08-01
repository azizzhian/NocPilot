<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { Zap, Eye, EyeOff, User, Lock } from 'lucide-vue-next'
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
  <div class="login-page">
    <div class="login-bg" aria-hidden="true">
      <div class="login-mesh" />
      <div class="login-map" />
      <div class="login-grid" />
      <div class="login-glow" />
    </div>

    <div class="login-card">
      <div class="login-logo">
        <div class="login-logo-ring">
          <Zap class="h-7 w-7 text-white" stroke-width="2.25" />
        </div>
      </div>

      <h1 class="login-title">Masuk ke akun</h1>

      <div class="login-divider" aria-hidden="true">
        <span class="login-divider-line" />
        <span class="login-divider-dot" />
        <span class="login-divider-line" />
      </div>

      <form class="login-form" @submit.prevent="handleLogin">
        <div class="login-field">
          <label for="login-username">Username</label>
          <div class="login-input-wrap">
            <User class="login-input-icon" />
            <input
              id="login-username"
              v-model="username"
              type="text"
              autocomplete="username"
              placeholder="Masukkan username Anda"
              class="login-input"
            />
          </div>
        </div>

        <div class="login-field">
          <label for="login-password">Password</label>
          <div class="login-input-wrap">
            <Lock class="login-input-icon" />
            <input
              id="login-password"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              placeholder="Masukkan password Anda"
              class="login-input login-input--password"
            />
            <button
              type="button"
              class="login-eye"
              tabindex="-1"
              :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
              @click="showPassword = !showPassword"
            >
              <EyeOff v-if="showPassword" class="h-4 w-4" />
              <Eye v-else class="h-4 w-4" />
            </button>
          </div>
        </div>

        <p v-if="error" class="login-error">{{ error }}</p>

        <button type="submit" class="login-submit" :disabled="loading">
          {{ loading ? 'Memproses...' : 'Masuk' }}
        </button>
      </form>

      <template v-if="telegramEnabled">
        <div class="login-or">
          <span>atau</span>
        </div>
        <div ref="telegramContainer" class="login-telegram" />
      </template>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  --login-accent: #3b82f6;
  --login-accent-2: #2563eb;
  --login-card: #ffffff;
  --login-border: rgba(59, 130, 246, 0.14);
  --login-text: #0f172a;
  --login-muted: #64748b;

  position: relative;
  display: flex;
  min-height: 100vh;
  min-height: 100dvh;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  padding: 1.75rem;
  background:
    radial-gradient(ellipse 90% 60% at 50% 110%, rgba(59, 130, 246, 0.22), transparent 55%),
    radial-gradient(ellipse 50% 40% at 85% 10%, rgba(96, 165, 250, 0.2), transparent 50%),
    radial-gradient(ellipse 45% 35% at 10% 15%, rgba(147, 197, 253, 0.25), transparent 50%),
    linear-gradient(165deg, #200961 0%, #120949 42%, #082f61 100%);
  color: var(--login-text);
  font-family: 'Segoe UI', ui-sans-serif, system-ui, sans-serif;
}

.login-bg {
  pointer-events: none;
  position: absolute;
  inset: 0;
}

.login-mesh {
  position: absolute;
  inset: 0;
  opacity: 0.45;
  background-image:
    radial-gradient(circle at 12% 18%, rgba(59, 130, 246, 0.55) 1.2px, transparent 1.5px),
    radial-gradient(circle at 28% 42%, rgba(37, 99, 235, 0.4) 1px, transparent 1.4px),
    radial-gradient(circle at 48% 22%, rgba(59, 130, 246, 0.45) 1.1px, transparent 1.5px),
    radial-gradient(circle at 68% 38%, rgba(96, 165, 250, 0.5) 1px, transparent 1.4px),
    radial-gradient(circle at 82% 16%, rgba(37, 99, 235, 0.4) 1.2px, transparent 1.5px),
    radial-gradient(circle at 18% 68%, rgba(59, 130, 246, 0.35) 1px, transparent 1.4px),
    radial-gradient(circle at 55% 72%, rgba(37, 99, 235, 0.3) 1px, transparent 1.4px),
    radial-gradient(circle at 78% 62%, rgba(59, 130, 246, 0.35) 1px, transparent 1.4px),
    linear-gradient(115deg, transparent 48%, rgba(59, 130, 246, 0.08) 49%, transparent 51%),
    linear-gradient(55deg, transparent 46%, rgba(37, 99, 235, 0.07) 49%, transparent 52%);
  background-size: 100% 100%;
  animation: mesh-drift 18s ease-in-out infinite alternate;
}

.login-map {
  position: absolute;
  left: 50%;
  top: 42%;
  width: min(920px, 120vw);
  height: min(420px, 55vh);
  transform: translate(-50%, -50%);
  opacity: 0.28;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1000 500'%3E%3Cg fill='%233b82f6'%3E%3Ccircle cx='180' cy='160' r='1.4'/%3E%3Ccircle cx='200' cy='150' r='1.2'/%3E%3Ccircle cx='220' cy='170' r='1.3'/%3E%3Ccircle cx='240' cy='145' r='1.1'/%3E%3Ccircle cx='260' cy='165' r='1.4'/%3E%3Ccircle cx='280' cy='155' r='1.2'/%3E%3Ccircle cx='300' cy='175' r='1.3'/%3E%3Ccircle cx='320' cy='148' r='1.1'/%3E%3Ccircle cx='340' cy='162' r='1.4'/%3E%3Ccircle cx='360' cy='152' r='1.2'/%3E%3Ccircle cx='380' cy='172' r='1.3'/%3E%3Ccircle cx='400' cy='140' r='1.1'/%3E%3Ccircle cx='420' cy='158' r='1.4'/%3E%3Ccircle cx='440' cy='168' r='1.2'/%3E%3Ccircle cx='460' cy='150' r='1.3'/%3E%3Ccircle cx='480' cy='165' r='1.1'/%3E%3Ccircle cx='500' cy='145' r='1.4'/%3E%3Ccircle cx='520' cy='160' r='1.2'/%3E%3Ccircle cx='540' cy='175' r='1.3'/%3E%3Ccircle cx='560' cy='148' r='1.1'/%3E%3Ccircle cx='580' cy='162' r='1.4'/%3E%3Ccircle cx='600' cy='155' r='1.2'/%3E%3Ccircle cx='620' cy='170' r='1.3'/%3E%3Ccircle cx='640' cy='145' r='1.1'/%3E%3Ccircle cx='660' cy='160' r='1.4'/%3E%3Ccircle cx='680' cy='150' r='1.2'/%3E%3Ccircle cx='700' cy='168' r='1.3'/%3E%3Ccircle cx='720' cy='155' r='1.1'/%3E%3Ccircle cx='740' cy='172' r='1.4'/%3E%3Ccircle cx='760' cy='148' r='1.2'/%3E%3Ccircle cx='780' cy='162' r='1.3'/%3E%3Ccircle cx='200' cy='220' r='1.2'/%3E%3Ccircle cx='250' cy='230' r='1.3'/%3E%3Ccircle cx='300' cy='210' r='1.1'/%3E%3Ccircle cx='350' cy='240' r='1.4'/%3E%3Ccircle cx='400' cy='225' r='1.2'/%3E%3Ccircle cx='450' cy='215' r='1.3'/%3E%3Ccircle cx='500' cy='235' r='1.1'/%3E%3Ccircle cx='550' cy='220' r='1.4'/%3E%3Ccircle cx='600' cy='230' r='1.2'/%3E%3Ccircle cx='650' cy='210' r='1.3'/%3E%3Ccircle cx='700' cy='240' r='1.1'/%3E%3Ccircle cx='750' cy='225' r='1.4'/%3E%3Ccircle cx='280' cy='280' r='1.2'/%3E%3Ccircle cx='330' cy='295' r='1.3'/%3E%3Ccircle cx='380' cy='270' r='1.1'/%3E%3Ccircle cx='430' cy='290' r='1.4'/%3E%3Ccircle cx='480' cy='275' r='1.2'/%3E%3Ccircle cx='530' cy='300' r='1.3'/%3E%3Ccircle cx='580' cy='280' r='1.1'/%3E%3Ccircle cx='630' cy='295' r='1.4'/%3E%3Ccircle cx='680' cy='270' r='1.2'/%3E%3Ccircle cx='160' cy='300' r='1.1'/%3E%3Ccircle cx='800' cy='200' r='1.3'/%3E%3Ccircle cx='820' cy='250' r='1.2'/%3E%3Ccircle cx='150' cy='200' r='1.4'/%3E%3Ccircle cx='120' cy='250' r='1.1'/%3E%3C/g%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: center;
  background-size: contain;
  mask-image: radial-gradient(ellipse at center, black 30%, transparent 75%);
}

.login-grid {
  position: absolute;
  left: -10%;
  right: -10%;
  bottom: -8%;
  height: 42%;
  background:
    linear-gradient(to top, rgba(59, 130, 246, 0.18), transparent 70%),
    repeating-linear-gradient(
      90deg,
      transparent 0,
      transparent 48px,
      rgba(59, 130, 246, 0.12) 48px,
      rgba(59, 130, 246, 0.12) 49px
    ),
    repeating-linear-gradient(
      0deg,
      transparent 0,
      transparent 36px,
      rgba(37, 99, 235, 0.1) 36px,
      rgba(37, 99, 235, 0.1) 37px
    );
  transform: perspective(500px) rotateX(58deg);
  transform-origin: center bottom;
  opacity: 0.5;
  mask-image: linear-gradient(to top, black 15%, transparent 90%);
}

.login-glow {
  position: absolute;
  left: 50%;
  bottom: 0;
  width: min(560px, 75vw);
  height: 200px;
  transform: translateX(-50%);
  background: radial-gradient(ellipse at center, rgba(59, 130, 246, 0.35), transparent 70%);
  filter: blur(24px);
  animation: glow-pulse 4s ease-in-out infinite;
}

.login-card {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 480px;
  border-radius: 1.5rem;
  border: 1px solid var(--login-border);
  background: var(--login-card);
  padding: 2.75rem 2.5rem 2.25rem;
  box-shadow:
    0 0 0 1px rgba(255, 255, 255, 0.8),
    0 20px 50px -16px rgba(37, 99, 235, 0.22),
    0 8px 24px -8px rgba(15, 23, 42, 0.08);
  animation: card-in 0.55s ease-out;
}

.login-logo {
  display: flex;
  justify-content: center;
  margin-bottom: 1.35rem;
}

.login-logo-ring {
  display: flex;
  width: 4rem;
  height: 4rem;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  background: linear-gradient(145deg, var(--login-accent), var(--login-accent-2));
  box-shadow:
    0 0 0 8px rgba(59, 130, 246, 0.12),
    0 10px 28px rgba(37, 99, 235, 0.35);
  animation: logo-glow 3s ease-in-out infinite;
}

.login-title {
  text-align: center;
  font-size: 1.75rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: #0f172a;
}

.login-divider {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 1.25rem 0 1.75rem;
}

.login-divider-line {
  height: 1px;
  flex: 1;
  background: linear-gradient(90deg, transparent, rgba(148, 163, 184, 0.55), transparent);
}

.login-divider-dot {
  width: 5px;
  height: 5px;
  border-radius: 9999px;
  background: rgba(59, 130, 246, 0.55);
  box-shadow: 0 0 8px rgba(59, 130, 246, 0.35);
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 1.15rem;
}

.login-field label {
  display: block;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #334155;
}

.login-input-wrap {
  position: relative;
}

.login-input-icon {
  position: absolute;
  left: 1rem;
  top: 50%;
  width: 1.05rem;
  height: 1.05rem;
  transform: translateY(-50%);
  color: #94a3b8;
  pointer-events: none;
}

.login-input {
  width: 100%;
  height: 3.1rem;
  border-radius: 0.85rem;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  padding: 0 1rem 0 2.75rem;
  color: #0f172a;
  font-size: 0.9375rem;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
}

.login-input--password {
  padding-right: 2.85rem;
}

.login-input::placeholder {
  color: #94a3b8;
}

.login-input:focus {
  border-color: rgba(59, 130, 246, 0.65);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.login-eye {
  position: absolute;
  right: 0.85rem;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  transition: color 0.15s;
}

.login-eye:hover {
  color: #475569;
}

.login-error {
  margin: 0;
  font-size: 0.8125rem;
  color: #dc2626;
}

.login-submit {
  margin-top: 0.5rem;
  height: 3.15rem;
  width: 100%;
  border: none;
  border-radius: 0.9rem;
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 55%, #1d4ed8 100%);
  color: #fff;
  font-size: 1rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  cursor: pointer;
  box-shadow:
    0 10px 24px rgba(37, 99, 235, 0.28),
    inset 0 1px 0 rgba(255, 255, 255, 0.2);
  transition: transform 0.15s, filter 0.15s, opacity 0.15s;
}

.login-submit:hover:not(:disabled) {
  filter: brightness(1.05);
  transform: translateY(-1px);
}

.login-submit:active:not(:disabled) {
  transform: translateY(0);
}

.login-submit:disabled {
  cursor: not-allowed;
  opacity: 0.65;
}

.login-or {
  position: relative;
  margin: 1.5rem 0 1rem;
  text-align: center;
}

.login-or::before {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  top: 50%;
  height: 1px;
  background: #e2e8f0;
}

.login-or span {
  position: relative;
  z-index: 1;
  padding: 0 0.75rem;
  background: #ffffff;
  font-size: 0.7rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--login-muted);
}

.login-telegram {
  display: flex;
  min-height: 40px;
  justify-content: center;
}

@keyframes card-in {
  from {
    opacity: 0;
    transform: translateY(12px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@keyframes logo-glow {
  0%, 100% {
    box-shadow:
      0 0 0 8px rgba(59, 130, 246, 0.1),
      0 10px 24px rgba(37, 99, 235, 0.28);
  }
  50% {
    box-shadow:
      0 0 0 10px rgba(59, 130, 246, 0.16),
      0 14px 32px rgba(37, 99, 235, 0.4);
  }
}

@keyframes glow-pulse {
  0%, 100% { opacity: 0.65; }
  50% { opacity: 1; }
}

@keyframes mesh-drift {
  from { transform: translate3d(0, 0, 0); }
  to { transform: translate3d(-1.5%, 1%, 0); }
}

@media (max-width: 520px) {
  .login-card {
    max-width: 100%;
    padding: 2rem 1.35rem 1.5rem;
  }

  .login-title {
    font-size: 1.5rem;
  }
}
</style>

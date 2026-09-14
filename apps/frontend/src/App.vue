<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterView } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const IDLE_TIMEOUT_MS = 60 * 60 * 1000
const WARNING_BEFORE_MS = 2 * 60 * 1000
const activityEvents = ['pointerdown', 'keydown', 'touchstart', 'scroll'] as const

const auth = useAuthStore()
const showIdleWarning = ref(false)
let warningTimer: number | undefined
let logoutTimer: number | undefined

function clearIdleTimers() {
  if (warningTimer) window.clearTimeout(warningTimer)
  if (logoutTimer) window.clearTimeout(logoutTimer)
  warningTimer = undefined
  logoutTimer = undefined
}

function stopIdleTracking() {
  clearIdleTimers()
  showIdleWarning.value = false
}

function scheduleIdleLogout() {
  clearIdleTimers()
  if (!auth.isAuthenticated) return

  showIdleWarning.value = false
  warningTimer = window.setTimeout(() => {
    showIdleWarning.value = true
  }, IDLE_TIMEOUT_MS - WARNING_BEFORE_MS)
  logoutTimer = window.setTimeout(async () => {
    showIdleWarning.value = false
    await auth.logout()
    window.location.href = '/login'
  }, IDLE_TIMEOUT_MS)
}

function registerActivity() {
  if (auth.isAuthenticated) scheduleIdleLogout()
}

watch(
  () => auth.isAuthenticated,
  (isAuthenticated) => {
    if (isAuthenticated) scheduleIdleLogout()
    else stopIdleTracking()
  },
)

onMounted(() => activityEvents.forEach((event) => window.addEventListener(event, registerActivity, { passive: true })))
onBeforeUnmount(() => {
  activityEvents.forEach((event) => window.removeEventListener(event, registerActivity))
  stopIdleTracking()
})
</script>

<template>
  <RouterView />
  <div v-if="showIdleWarning" class="idle-warning" role="alertdialog" aria-modal="true">
    <div class="idle-warning__card">
      <h2>Sesi akan berakhir</h2>
      <p>Tidak ada aktivitas selama 58 menit. Anda akan logout otomatis dalam 2 menit.</p>
      <button type="button" @click="scheduleIdleLogout">Tetap masuk</button>
    </div>
  </div>
</template>

<style scoped>
.idle-warning {
  position: fixed;
  z-index: 10000;
  inset: 0;
  display: grid;
  place-items: center;
  padding: 1.5rem;
  background: rgb(15 23 42 / 0.55);
}

.idle-warning__card {
  width: min(100%, 25rem);
  padding: 1.5rem;
  border-radius: 0.75rem;
  background: #fff;
  color: #0f172a;
  box-shadow: 0 1.5rem 4rem rgb(15 23 42 / 0.3);
}

.idle-warning__card h2 { margin: 0 0 0.5rem; font-size: 1.125rem; }
.idle-warning__card p { margin: 0 0 1rem; line-height: 1.5; }
.idle-warning__card button {
  border: 0;
  border-radius: 0.5rem;
  padding: 0.625rem 1rem;
  background: #2563eb;
  color: #fff;
  cursor: pointer;
}
</style>

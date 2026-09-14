<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Button from '@/components/ui/Button.vue'

const IDLE_MS = 60 * 60 * 1000
const WARN_BEFORE_MS = 2 * 60 * 1000
const ACTIVITY_EVENTS = ['pointerdown', 'keydown', 'touchstart', 'scroll'] as const

const auth = useAuthStore()
const router = useRouter()
const showIdleWarning = ref(false)

let warnTimer: ReturnType<typeof setTimeout> | undefined
let logoutTimer: ReturnType<typeof setTimeout> | undefined

function clearTimers() {
  if (warnTimer) clearTimeout(warnTimer)
  if (logoutTimer) clearTimeout(logoutTimer)
  warnTimer = undefined
  logoutTimer = undefined
}

function stopIdleWatch() {
  clearTimers()
  showIdleWarning.value = false
}

function scheduleIdleLogout() {
  clearTimers()
  if (!auth.isAuthenticated) return

  showIdleWarning.value = false
  warnTimer = setTimeout(() => {
    showIdleWarning.value = true
  }, IDLE_MS - WARN_BEFORE_MS)

  logoutTimer = setTimeout(async () => {
    showIdleWarning.value = false
    await auth.logout()
    await router.replace('/login')
  }, IDLE_MS)
}

function onActivity() {
  if (!auth.isAuthenticated) return
  // Jangan reset timer saat dialog warning terbuka — user harus klik "Tetap masuk"
  if (showIdleWarning.value) return
  scheduleIdleLogout()
}

watch(
  () => auth.isAuthenticated,
  (ok) => {
    if (ok) scheduleIdleLogout()
    else stopIdleWatch()
  },
)

onMounted(() => {
  ACTIVITY_EVENTS.forEach((event) => {
    window.addEventListener(event, onActivity, { passive: true })
  })
  if (auth.isAuthenticated) scheduleIdleLogout()
})

onBeforeUnmount(() => {
  ACTIVITY_EVENTS.forEach((event) => {
    window.removeEventListener(event, onActivity)
  })
  stopIdleWatch()
})
</script>

<template>
  <RouterView />

  <Teleport to="body">
    <div
      v-if="showIdleWarning"
      class="fixed inset-0 z-[10000] flex items-center justify-center bg-slate-900/55 p-6"
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="idle-warning-title"
    >
      <div class="w-full max-w-sm rounded-2xl border border-border bg-card p-6 shadow-xl">
        <h2 id="idle-warning-title" class="text-lg font-semibold text-foreground">
          Sesi akan berakhir
        </h2>
        <p class="mt-2 text-sm text-muted">
          Tidak ada aktivitas selama 58 menit. Anda akan logout otomatis dalam 2 menit.
        </p>
        <div class="mt-5 flex justify-end">
          <Button type="button" @click="scheduleIdleLogout">Tetap masuk</Button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

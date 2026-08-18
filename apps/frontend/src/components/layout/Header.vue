<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { cn } from '@/lib/utils'
import { useAppStore } from '@/stores/app'
import { useAuthStore } from '@/stores/auth'
import { appUpdatesApi, type AppUpdateItem } from '@/services/api'
import {
  Search,
  Moon,
  Sun,
  LogOut,
  ChevronDown,
  User,
  Menu,
  Bell,
  CheckCheck,
} from 'lucide-vue-next'
import Button from '@/components/ui/Button.vue'

defineProps<{
  title: string
  subtitle?: string
}>()

const router = useRouter()
const appStore = useAppStore()
const authStore = useAuthStore()
const profileOpen = ref(false)
const updatesOpen = ref(false)
const updates = ref<AppUpdateItem[]>([])
const unreadCount = ref(0)
const updatesLoading = ref(false)

async function loadUpdates() {
  updatesLoading.value = true
  try {
    const { data } = await appUpdatesApi.list()
    updates.value = data.updates
    unreadCount.value = data.unread
  } catch {
    // abaikan jika endpoint belum tersedia
  } finally {
    updatesLoading.value = false
  }
}

async function markUpdatesRead() {
  try {
    const { data } = await appUpdatesApi.markRead()
    unreadCount.value = data.unread
    updates.value = updates.value.map((u) => ({ ...u, is_unread: false }))
  } catch {
    // abaikan
  }
}

function toggleUpdates() {
  updatesOpen.value = !updatesOpen.value
  profileOpen.value = false
  if (updatesOpen.value) {
    loadUpdates()
  }
}

function formatDeployedAt(iso: string) {
  return new Date(iso).toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function handleLogout() {
  profileOpen.value = false
  await authStore.logout()
  router.push('/login')
}

function goProfile() {
  profileOpen.value = false
  router.push('/profile')
}

function onDocClick(e: MouseEvent) {
  const target = e.target as HTMLElement | null
  if (!target?.closest('[data-profile-menu]')) {
    profileOpen.value = false
  }
  if (!target?.closest('[data-updates-menu]')) {
    updatesOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', onDocClick)
  loadUpdates()
})
onUnmounted(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <header
    :class="cn(
      'sticky top-0 z-30 flex h-16 items-center justify-between border-b border-border bg-card/80 px-4 sm:px-6 backdrop-blur-xl transition-all duration-300',
    )"
  >
    <div class="flex min-w-0 items-center gap-2">
      <Button
        variant="ghost"
        size="icon"
        class="shrink-0 lg:hidden"
        aria-label="Buka menu"
        @click="appStore.openMobileSidebar()"
      >
        <Menu class="h-5 w-5" />
      </Button>
      <div class="animate-fade-in min-w-0">
        <h2 class="truncate text-base font-semibold text-foreground sm:text-lg">{{ title }}</h2>
        <p v-if="subtitle" class="hidden truncate text-xs text-muted sm:block">{{ subtitle }}</p>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <button
        type="button"
        class="hidden h-9 min-w-[220px] items-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-3 text-sm shadow-sm transition-colors hover:bg-[#F8FAFC] md:flex dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800"
        @click="appStore.openCommand()"
      >
        <Search class="h-4 w-4 text-[#94A3B8]" />
        <span class="flex-1 text-left text-[#94A3B8]">Cari...</span>
        <kbd class="rounded bg-[#F1F5F9] px-1.5 py-0.5 text-[10px] font-mono text-[#94A3B8] dark:bg-slate-800">Ctrl+K</kbd>
      </button>

      <div class="relative" data-updates-menu>
        <Button
          variant="ghost"
          size="icon"
          class="relative"
          aria-label="Update aplikasi"
          @click.stop="toggleUpdates"
        >
          <Bell class="h-4 w-4" />
          <span
            v-if="unreadCount > 0"
            class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold text-white"
          >
            {{ unreadCount > 9 ? '9+' : unreadCount }}
          </span>
        </Button>

        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="updatesOpen"
            class="absolute right-0 top-full z-50 mt-2 w-[min(100vw-2rem,380px)] overflow-hidden rounded-[18px] border border-border bg-card card-shadow-hover"
            @click.stop
          >
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
              <div>
                <p class="text-sm font-semibold text-foreground">Update Aplikasi</p>
                <p class="text-[11px] text-muted">Perubahan dari deploy terbaru</p>
              </div>
              <button
                v-if="unreadCount > 0"
                type="button"
                class="flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-medium text-primary transition-colors hover:bg-primary/10"
                @click="markUpdatesRead"
              >
                <CheckCheck class="h-3.5 w-3.5" />
                Tandai dibaca
              </button>
            </div>

            <div class="max-h-[min(70vh,420px)] overflow-y-auto">
              <div v-if="updatesLoading" class="px-4 py-8 text-center text-sm text-muted">
                Memuat...
              </div>
              <div v-else-if="updates.length === 0" class="px-4 py-8 text-center text-sm text-muted">
                Belum ada catatan update.
              </div>
              <div v-else class="divide-y divide-border">
                <div
                  v-for="update in updates"
                  :key="update.id"
                  class="px-4 py-3"
                  :class="update.is_unread ? 'bg-primary/5' : ''"
                >
                  <div class="mb-2 flex items-start justify-between gap-2">
                    <p class="text-xs font-medium text-foreground">
                      Deploy
                      <span v-if="update.branch" class="text-muted">({{ update.branch }})</span>
                    </p>
                    <time class="shrink-0 text-[10px] text-muted">{{ formatDeployedAt(update.deployed_at) }}</time>
                  </div>
                  <ul class="space-y-1.5">
                    <li
                      v-for="(change, idx) in update.changes"
                      :key="change.hash + idx"
                      class="flex gap-2 text-xs text-foreground"
                    >
                      <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary" />
                      <span class="leading-relaxed">{{ change.message }}</span>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </div>

      <Button variant="ghost" size="icon" @click="appStore.toggleDark()">
        <Sun v-if="appStore.isDark" class="h-4 w-4" />
        <Moon v-else class="h-4 w-4" />
      </Button>

      <div class="relative" data-profile-menu>
        <button
          type="button"
          class="flex items-center gap-2 rounded-xl px-2 py-1.5 transition-colors hover:bg-[#F8FAFC] dark:hover:bg-slate-800"
          @click.stop="profileOpen = !profileOpen"
        >
          <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">
            {{ authStore.user?.name?.charAt(0) ?? 'A' }}
          </div>
          <div class="hidden text-left md:block">
            <p class="text-xs font-medium">{{ authStore.user?.name }}</p>
            <p class="text-[10px] text-muted capitalize">{{ authStore.user?.role }}</p>
          </div>
          <ChevronDown class="hidden h-3.5 w-3.5 text-muted md:block" />
        </button>

        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="profileOpen"
            class="absolute right-0 top-full mt-2 w-52 rounded-[18px] border border-border bg-card p-1 card-shadow-hover"
            @click.stop
          >
            <button
              type="button"
              class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm text-foreground transition-colors hover:bg-muted/40"
              @click="goProfile"
            >
              <User class="h-4 w-4 text-muted" />
              Edit Profil
            </button>
            <button
              type="button"
              class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm text-danger transition-colors hover:bg-danger/10"
              @click="handleLogout"
            >
              <LogOut class="h-4 w-4" />
              Keluar
            </button>
          </div>
        </Transition>
      </div>
    </div>
  </header>
</template>

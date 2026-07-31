<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { cn } from '@/lib/utils'
import { useAppStore } from '@/stores/app'
import { useAuthStore } from '@/stores/auth'
import {
  Search,
  Moon,
  Sun,
  LogOut,
  ChevronDown,
  User,
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
}

onMounted(() => document.addEventListener('click', onDocClick))
onUnmounted(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <header
    :class="cn(
      'sticky top-0 z-30 flex h-16 items-center justify-between border-b border-border bg-card/80 px-6 backdrop-blur-xl transition-all duration-300',
    )"
  >
    <div class="animate-fade-in">
      <h2 class="text-lg font-semibold text-foreground">{{ title }}</h2>
      <p v-if="subtitle" class="text-xs text-muted">{{ subtitle }}</p>
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
          leave-from-class="opacity-100"
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

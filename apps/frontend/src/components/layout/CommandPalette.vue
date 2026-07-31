<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { cn } from '@/lib/utils'
import { useAppStore } from '@/stores/app'
import { navigation } from '@/data/navigation'
import { customerApi, monitoringApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { Search, ArrowRight, Command } from 'lucide-vue-next'

interface SearchItem {
  label: string
  type: string
  to: string
}

const router = useRouter()
const appStore = useAppStore()
const authStore = useAuthStore()
const query = ref('')
const selectedIndex = ref(0)
const searching = ref(false)
const apiResults = ref<SearchItem[]>([])

const defaultItems = computed<SearchItem[]>(() =>
  navigation
    .flatMap((s) => s.items)
    .filter((item) => !item.permission || authStore.can(item.permission))
    .slice(0, 10)
    .map((item) => ({ label: item.label, type: 'Menu', to: item.to })),
)

const filtered = computed(() => {
  if (query.value.length < 2) {
    if (!query.value) return defaultItems.value
    const q = query.value.toLowerCase()
    return defaultItems.value.filter(
      (item) => item.label.toLowerCase().includes(q) || item.type.toLowerCase().includes(q),
    )
  }
  return apiResults.value
})

let searchTimeout: ReturnType<typeof setTimeout>

async function runSearch(q: string) {
  if (q.length < 2) {
    apiResults.value = []
    return
  }
  searching.value = true
  try {
    const [customers, routers] = await Promise.all([
      customerApi.list({ search: q, per_page: 5 }),
      monitoringApi.routers({ search: q }),
    ])
    const items: SearchItem[] = [
      ...customers.data.data.map((c) => ({
        label: `${c.name} (${c.customer_code})`,
        type: 'Pelanggan',
        to: `/pelanggan`,
      })),
      ...routers.data.data.slice(0, 5).map((r) => ({
        label: `${r.name} (${r.ip})`,
        type: 'Router',
        to: '/monitoring',
      })),
    ]
    apiResults.value = items
  } finally {
    searching.value = false
  }
}

watch(query, (q) => {
  selectedIndex.value = 0
  clearTimeout(searchTimeout)
  if (q.length >= 2) {
    searchTimeout = setTimeout(() => runSearch(q), 300)
  } else {
    apiResults.value = []
  }
})

function navigate(to: string) {
  appStore.closeCommand()
  query.value = ''
  apiResults.value = []
  router.push(to)
}

function onKeydown(e: KeyboardEvent) {
  if (!appStore.commandOpen) return

  if (e.key === 'ArrowDown') {
    e.preventDefault()
    selectedIndex.value = Math.min(selectedIndex.value + 1, filtered.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
  } else if (e.key === 'Enter' && filtered.value[selectedIndex.value]) {
    navigate(filtered.value[selectedIndex.value].to)
  } else if (e.key === 'Escape') {
    appStore.closeCommand()
  }
}

function onGlobalKeydown(e: KeyboardEvent) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault()
    appStore.commandOpen ? appStore.closeCommand() : appStore.openCommand()
  }
}

onMounted(() => {
  window.addEventListener('keydown', onGlobalKeydown)
  window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onGlobalKeydown)
  window.removeEventListener('keydown', onKeydown)
  clearTimeout(searchTimeout)
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="appStore.commandOpen"
        class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 backdrop-blur-sm pt-[20vh]"
        @click.self="appStore.closeCommand()"
      >
        <div class="w-full max-w-lg animate-slide-up rounded-[18px] border border-border bg-card card-shadow-hover overflow-hidden">
          <div class="flex items-center gap-3 border-b border-border px-4">
            <Search class="h-4 w-4 text-muted shrink-0" />
            <input
              v-model="query"
              type="text"
              placeholder="Cari pelanggan, router..."
              class="form-control flex-1 border-0 bg-transparent py-4 shadow-none focus:ring-0"
              autofocus
            />
            <kbd class="hidden rounded bg-slate-100 px-2 py-1 text-[10px] font-mono text-muted sm:block dark:bg-slate-800">ESC</kbd>
          </div>

          <div class="max-h-72 overflow-y-auto p-2">
            <p v-if="searching" class="px-3 py-8 text-center text-sm text-muted">Mencari...</p>
            <p v-else-if="filtered.length === 0" class="px-3 py-8 text-center text-sm text-muted">
              {{ query.length >= 2 ? `Tidak ada hasil untuk "${query}"` : 'Ketik minimal 2 karakter untuk mencari data' }}
            </p>
            <button
              v-for="(item, index) in filtered"
              :key="`${item.type}-${item.to}-${item.label}`"
              :class="cn(
                'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition-colors',
                index === selectedIndex ? 'bg-primary/10 text-primary' : 'hover:bg-slate-50 dark:hover:bg-slate-800',
              )"
              @click="navigate(item.to)"
              @mouseenter="selectedIndex = index"
            >
              <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-xs font-medium dark:bg-slate-800">
                {{ item.type.charAt(0) }}
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium truncate">{{ item.label }}</p>
                <p class="text-xs text-muted">{{ item.type }}</p>
              </div>
              <ArrowRight class="h-4 w-4 text-muted shrink-0" />
            </button>
          </div>

          <div class="flex items-center gap-4 border-t border-border px-4 py-2.5 text-[10px] text-muted">
            <span class="flex items-center gap-1"><Command class="h-3 w-3" /> Pencarian Global</span>
            <span>↑↓ navigasi</span>
            <span>↵ pilih</span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

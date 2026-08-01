<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { cn } from '@/lib/utils'
import { navigation, type NavItem } from '@/data/navigation'
import { useAppStore } from '@/stores/app'
import { useAuthStore } from '@/stores/auth'
import {
  ChevronLeft,
  ChevronRight,
  Star,
  Search,
  Zap,
} from 'lucide-vue-next'
import Badge from '@/components/ui/Badge.vue'

const route = useRoute()
const appStore = useAppStore()
const authStore = useAuthStore()

function filterByAccess(items: NavItem[]) {
  return items.filter((item) => {
    if (item.permission) return authStore.can(item.permission)
    if (!item.roles) return true
    const role = authStore.user?.role
    return role ? item.roles.includes(role) : false
  })
}

const filteredNav = computed(() =>
  navigation
    .map((section) => ({
      ...section,
      items: filterByAccess(section.items),
    }))
    .filter((section) => section.items.length > 0),
)

const favorites = computed(() =>
  navigation.flatMap((s) => s.items).filter((i) => i.favorite && filterByAccess([i]).length),
)

function badgeForItem(to: string): number | undefined {
  const map: Record<string, 'monitoring' | 'input_harian' | 'activations' | 'dismantles'> = {
    '/monitoring': 'monitoring',
    '/komplain': 'input_harian',
    '/aktivasi': 'activations',
    '/dismantle': 'dismantles',
    '/report/dismantle': 'dismantles',
  }
  const key = map[to]
  if (!key) return undefined
  const count = appStore.navBadges[key]
  return count > 0 ? count : undefined
}

function isActive(path: string) {
  if (path === '/') return route.path === '/'
  // Hindari /report ikut aktif saat di /report/dismantle atau /report/ticket
  if (path === '/report') return route.path === '/report'
  return route.path === path || route.path.startsWith(`${path}/`)
}
</script>

<template>
  <aside
    :class="cn(
      'fixed left-0 top-0 z-40 flex h-screen flex-col bg-sidebar text-sidebar-foreground transition-all duration-300 sidebar-shadow',
      appStore.sidebarCollapsed ? 'w-[72px]' : 'w-[260px]',
    )"
  >
    <!-- Logo -->
    <div class="flex h-16 items-center gap-3 border-b border-white/10 px-4">
      <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary">
        <Zap class="h-5 w-5 text-white" />
      </div>
      <div v-if="!appStore.sidebarCollapsed" class="animate-fade-in overflow-hidden">
        <h1 class="text-sm font-bold text-white tracking-tight">NocPilot</h1>
        <p class="text-[10px] text-slate-400">Aplikasi Untuk Report NOC</p>
      </div>
    </div>

    <!-- Search trigger -->
    <button
      v-if="!appStore.sidebarCollapsed"
      class="mx-3 mt-4 flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-slate-400 transition-colors hover:bg-white/10 hover:text-slate-200"
      @click="appStore.openCommand()"
    >
      <Search class="h-3.5 w-3.5" />
      <span class="flex-1 text-left">Cari...</span>
      <kbd class="rounded bg-white/10 px-1.5 py-0.5 text-[10px] font-mono">Ctrl+K</kbd>
    </button>

    <!-- Favorites -->
    <div v-if="!appStore.sidebarCollapsed && favorites.length" class="mt-4 px-3">
      <p class="mb-2 flex items-center gap-1.5 px-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
        <Star class="h-3 w-3" /> Favorit
      </p>
      <div class="space-y-0.5">
        <RouterLink
          v-for="item in favorites"
          :key="item.to"
          :to="item.to"
          :class="cn(
            'flex items-center gap-2.5 rounded-xl px-2.5 py-2 text-sm transition-all duration-200',
            isActive(item.to)
              ? 'bg-primary/20 text-white font-medium'
              : 'text-slate-400 hover:bg-white/5 hover:text-slate-200',
          )"
        >
          <component :is="item.icon" class="h-4 w-4 shrink-0" />
          <span class="truncate">{{ item.label }}</span>
          <Badge v-if="badgeForItem(item.to)" variant="danger" class="ml-auto text-[10px]">{{ badgeForItem(item.to) }}</Badge>
        </RouterLink>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-scroll flex-1 overflow-y-auto overflow-x-hidden px-3 py-4">
      <div v-for="section in filteredNav" :key="section.title" class="mb-5">
        <p
          v-if="!appStore.sidebarCollapsed"
          class="mb-2 px-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500"
        >
          {{ section.title }}
        </p>
        <div class="space-y-0.5">
          <RouterLink
            v-for="item in section.items"
            :key="item.to"
            :to="item.to"
            :title="appStore.sidebarCollapsed ? item.label : undefined"
            :class="cn(
              'group relative flex items-center gap-2.5 rounded-xl px-2.5 py-2 text-sm transition-all duration-200',
              isActive(item.to)
                ? 'bg-primary text-white font-medium shadow-lg shadow-primary/25'
                : 'text-slate-400 hover:bg-white/5 hover:text-slate-200',
              appStore.sidebarCollapsed && 'justify-center px-0',
            )"
          >
            <component :is="item.icon" class="h-4 w-4 shrink-0" />
            <span v-if="!appStore.sidebarCollapsed" class="truncate">{{ item.label }}</span>
            <Badge
              v-if="badgeForItem(item.to) && !appStore.sidebarCollapsed"
              :variant="isActive(item.to) ? 'secondary' : 'danger'"
              class="ml-auto text-[10px]"
            >
              {{ badgeForItem(item.to) }}
            </Badge>
            <span
              v-if="badgeForItem(item.to) && appStore.sidebarCollapsed"
              class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-danger text-[9px] font-bold text-white"
            >
              {{ (badgeForItem(item.to) ?? 0) > 9 ? '9+' : badgeForItem(item.to) }}
            </span>
          </RouterLink>
        </div>
      </div>
    </nav>

    <!-- Collapse toggle -->
    <button
      class="flex h-12 items-center justify-center border-t border-white/10 text-slate-400 transition-colors hover:bg-white/5 hover:text-white"
      @click="appStore.toggleSidebar()"
    >
      <ChevronLeft v-if="!appStore.sidebarCollapsed" class="h-4 w-4" />
      <ChevronRight v-else class="h-4 w-4" />
    </button>
  </aside>
</template>

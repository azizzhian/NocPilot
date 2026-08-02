<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { cn } from '@/lib/utils'
import { useAppStore } from '@/stores/app'
import Sidebar from '@/components/layout/Sidebar.vue'
import Header from '@/components/layout/Header.vue'
import CommandPalette from '@/components/layout/CommandPalette.vue'

defineProps<{
  title: string
  subtitle?: string
}>()

const route = useRoute()
const appStore = useAppStore()

const mainMargin = computed(() => {
  if (!appStore.isDesktop) return 'ml-0'
  return appStore.sidebarCollapsed ? 'ml-[72px]' : 'ml-[260px]'
})

watch(() => route.fullPath, () => {
  appStore.closeMobileSidebar()
})

onMounted(() => {
  appStore.fetchNavBadges()
  void appStore.fetchSidebarFavorites()
})
</script>

<template>
  <div class="min-h-screen bg-background">
    <!-- Mobile overlay -->
    <Transition
      enter-active-class="transition duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="appStore.sidebarMobileOpen && !appStore.isDesktop"
        class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden"
        @click="appStore.closeMobileSidebar()"
      />
    </Transition>

    <Sidebar />
    <div :class="cn('flex min-h-screen flex-col transition-all duration-300', mainMargin)">
      <Header :title="title" :subtitle="subtitle" />
      <main class="flex-1 p-4 sm:p-6">
        <slot />
      </main>
    </div>
    <CommandPalette />
  </div>
</template>

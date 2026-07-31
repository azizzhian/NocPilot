<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { cn } from '@/lib/utils'
import { useAppStore } from '@/stores/app'
import Sidebar from '@/components/layout/Sidebar.vue'
import Header from '@/components/layout/Header.vue'
import CommandPalette from '@/components/layout/CommandPalette.vue'

defineProps<{
  title: string
  subtitle?: string
}>()

const appStore = useAppStore()

const mainMargin = computed(() =>
  appStore.sidebarCollapsed ? 'ml-[72px]' : 'ml-[260px]',
)

onMounted(() => {
  appStore.fetchNavBadges()
})
</script>

<template>
  <div class="min-h-screen bg-background">
    <Sidebar />
    <div :class="cn('flex min-h-screen flex-col transition-all duration-300', mainMargin)">
      <Header :title="title" :subtitle="subtitle" />
      <main class="flex-1 p-6">
        <slot />
      </main>
    </div>
    <CommandPalette />
  </div>
</template>

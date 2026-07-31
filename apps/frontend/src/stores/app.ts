import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import { useDark, useToggle } from '@vueuse/core'
import { dashboardApi } from '@/services/api'

export const useAppStore = defineStore('app', () => {
  const sidebarCollapsed = ref(false)
  const commandOpen = ref(false)
  const navBadges = ref<Record<string, number>>({})
  const navBadgesLoaded = ref(false)

  const isDark = useDark({
    selector: 'html',
    attribute: 'class',
    valueDark: 'dark',
    valueLight: '',
    storageKey: 'nocpilot-theme',
  })
  const toggleDark = useToggle(isDark)

  function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value
  }

  function openCommand() {
    commandOpen.value = true
  }

  function closeCommand() {
    commandOpen.value = false
  }

  watch(commandOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : ''
  })

  async function fetchNavBadges() {
    if (navBadgesLoaded.value) return
    try {
      const { data } = await dashboardApi.navBadges()
      navBadges.value = data.nav_badges
      navBadgesLoaded.value = true
    } catch { /* silent */ }
  }

  return {
    sidebarCollapsed,
    commandOpen,
    navBadges,
    isDark,
    toggleDark,
    toggleSidebar,
    openCommand,
    closeCommand,
    fetchNavBadges,
  }
})

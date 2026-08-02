import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import { useDark, useToggle, useMediaQuery } from '@vueuse/core'
import { dashboardApi, settingsApi } from '@/services/api'
import { defaultFavoritePaths } from '@/data/navigation'

export const useAppStore = defineStore('app', () => {
  const sidebarCollapsed = ref(false)
  const sidebarMobileOpen = ref(false)
  const isDesktop = useMediaQuery('(min-width: 1024px)')
  const commandOpen = ref(false)
  const navBadges = ref<Record<string, number>>({})
  const navBadgesLoaded = ref(false)
  const sidebarFavoritePaths = ref<string[]>(defaultFavoritePaths())
  const sidebarFavoritesLoaded = ref(false)

  const isDark = useDark({
    selector: 'html',
    attribute: 'class',
    valueDark: 'dark',
    valueLight: '',
    storageKey: 'nocpilot-theme',
  })
  const toggleDark = useToggle(isDark)

  function toggleSidebar() {
    if (isDesktop.value) {
      sidebarCollapsed.value = !sidebarCollapsed.value
    } else {
      sidebarMobileOpen.value = !sidebarMobileOpen.value
    }
  }

  function openMobileSidebar() {
    sidebarMobileOpen.value = true
  }

  function closeMobileSidebar() {
    sidebarMobileOpen.value = false
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

  watch(sidebarMobileOpen, (open) => {
    if (!isDesktop.value) {
      document.body.style.overflow = open ? 'hidden' : ''
    }
  })

  watch(isDesktop, (desktop) => {
    if (desktop) sidebarMobileOpen.value = false
  })

  async function fetchNavBadges() {
    if (navBadgesLoaded.value) return
    try {
      const { data } = await dashboardApi.navBadges()
      navBadges.value = data.nav_badges
      navBadgesLoaded.value = true
    } catch { /* silent */ }
  }

  async function fetchSidebarFavorites(force = false) {
    if (sidebarFavoritesLoaded.value && !force) return
    try {
      const { data } = await settingsApi.get()
      const paths = data.sidebar_favorites
      sidebarFavoritePaths.value = Array.isArray(paths) && paths.length
        ? paths
        : defaultFavoritePaths()
      sidebarFavoritesLoaded.value = true
    } catch {
      sidebarFavoritePaths.value = defaultFavoritePaths()
    }
  }

  function setSidebarFavoritePaths(paths: string[]) {
    sidebarFavoritePaths.value = paths
    sidebarFavoritesLoaded.value = true
  }

  return {
    sidebarCollapsed,
    sidebarMobileOpen,
    isDesktop,
    commandOpen,
    navBadges,
    sidebarFavoritePaths,
    isDark,
    toggleDark,
    toggleSidebar,
    openMobileSidebar,
    closeMobileSidebar,
    openCommand,
    closeCommand,
    fetchNavBadges,
    fetchSidebarFavorites,
    setSidebarFavoritePaths,
  }
})

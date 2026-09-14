import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { UserRole } from '@/data/navigation'
import { authApi, type ApiUser } from '@/services/api'

const TOKEN_KEY = 'nocpilot_token'
const TAB_ID_KEY = 'nocpilot_tab_id'
const AUTH_SYNC_KEY = 'nocpilot_auth_sync'

export interface User {
  id: number
  name: string
  username: string
  email: string
  telegram_id: string | null
  telegram_username: string | null
  role: UserRole
  department: string
  permissions: string[]
  roles: string[]
}

function mapUser(apiUser: ApiUser): User {
  return {
    id: apiUser.id,
    name: apiUser.name,
    username: apiUser.username,
    email: apiUser.email,
    telegram_id: apiUser.telegram_id ?? null,
    telegram_username: apiUser.telegram_username ?? null,
    role: (apiUser.role ?? 'noc') as UserRole,
    department: apiUser.department ?? '—',
    permissions: apiUser.permissions ?? [],
    roles: apiUser.roles ?? [],
  }
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  // A browser profile can have several NocPilot tabs. Keep each tab's account
  // isolated so logging in/out in one tab cannot replace another tab's account.
  const token = ref<string | null>(sessionStorage.getItem(TOKEN_KEY))
  const loading = ref(false)
  let sessionVersion = 0
  const tabId = sessionStorage.getItem(TAB_ID_KEY) ?? crypto.randomUUID()
  sessionStorage.setItem(TAB_ID_KEY, tabId)

  const isAuthenticated = computed(() => !!token.value && !!user.value)

  const isAdmin = computed(
    () => user.value?.role === 'administrator' || (user.value?.roles ?? []).includes('administrator'),
  )

  function setSession(data: { token: string; user: ApiUser }) {
    sessionVersion += 1
    token.value = data.token
    user.value = mapUser(data.user)
    sessionStorage.setItem(TOKEN_KEY, data.token)
  }

  function clearSession() {
    sessionVersion += 1
    token.value = null
    user.value = null
    sessionStorage.removeItem(TOKEN_KEY)
  }

  function notifyOtherTabs(type: 'login' | 'logout') {
    localStorage.setItem(AUTH_SYNC_KEY, JSON.stringify({ type, source: tabId, at: Date.now() }))
  }

  function redirectToLogin() {
    if (!window.location.pathname.includes('/login')) window.location.href = '/login'
  }

  window.addEventListener('storage', (event) => {
    if (event.key !== AUTH_SYNC_KEY || !event.newValue) return

    try {
      const message = JSON.parse(event.newValue) as { source?: string; type?: string }
      if (message.source === tabId || (message.type !== 'login' && message.type !== 'logout')) return
      clearSession()
      redirectToLogin()
    } catch {
      // Ignore malformed values written by an old client or browser extension.
    }
  })

  window.addEventListener('nocpilot:auth-invalid', () => {
    clearSession()
  })

  function can(permission?: string | string[]): boolean {
    if (!permission) return true
    if (!user.value) return false
    if (isAdmin.value) return true
    const needed = Array.isArray(permission) ? permission : [permission]
    const have = new Set(user.value.permissions)
    return needed.some((p) => have.has(p))
  }

  async function login(username: string, password: string) {
    loading.value = true
    try {
      const { data } = await authApi.login(username, password)
      setSession(data)
      notifyOtherTabs('login')
      return true
    } finally {
      loading.value = false
    }
  }

  async function loginTelegram(payload: Record<string, unknown>) {
    loading.value = true
    try {
      const { data } = await authApi.loginTelegram(payload)
      setSession(data)
      notifyOtherTabs('login')
      return true
    } finally {
      loading.value = false
    }
  }

  async function fetchUser() {
    const expectedToken = token.value
    const expectedVersion = sessionVersion
    if (!expectedToken) return false
    try {
      const { data } = await authApi.me()
      if (token.value !== expectedToken || sessionVersion !== expectedVersion) return false
      user.value = mapUser(data.user)
      return true
    } catch {
      if (token.value === expectedToken && sessionVersion === expectedVersion) clearSession()
      return false
    }
  }

  async function updateProfile(payload: Record<string, unknown>) {
    const { data } = await authApi.updateProfile(payload)
    user.value = mapUser(data.user)
    return data
  }

  async function logout() {
    const currentToken = token.value
    // Invalidate this tab immediately, so a pending /auth/me response from the
    // prior user cannot restore that user while logout is in flight.
    clearSession()
    notifyOtherTabs('logout')
    try {
      if (currentToken) await authApi.logout(currentToken)
    } catch {
      // Local logout must still succeed if the server token has already expired.
    }
  }

  async function init() {
    // Remove the legacy shared token once. Tokens now belong to a browser tab.
    localStorage.removeItem(TOKEN_KEY)
    if (token.value && !user.value) {
      return fetchUser()
    }
    return !!user.value
  }

  return {
    user,
    token,
    loading,
    isAuthenticated,
    isAdmin,
    can,
    login,
    loginTelegram,
    updateProfile,
    logout,
    fetchUser,
    init,
  }
})

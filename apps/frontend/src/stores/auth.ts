import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { UserRole } from '@/data/navigation'
import { authApi, type ApiUser } from '@/services/api'

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
  const token = ref<string | null>(localStorage.getItem('nocpilot_token'))
  const loading = ref(false)

  const isAuthenticated = computed(() => !!token.value && !!user.value)

  const isAdmin = computed(
    () => user.value?.role === 'administrator' || (user.value?.roles ?? []).includes('administrator'),
  )

  function setSession(data: { token: string; user: ApiUser }) {
    token.value = data.token
    user.value = mapUser(data.user)
    localStorage.setItem('nocpilot_token', data.token)
  }

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
      return true
    } finally {
      loading.value = false
    }
  }

  async function fetchUser() {
    if (!token.value) return false
    try {
      const { data } = await authApi.me()
      user.value = mapUser(data.user)
      return true
    } catch {
      token.value = null
      user.value = null
      localStorage.removeItem('nocpilot_token')
      return false
    }
  }

  async function updateProfile(payload: Record<string, unknown>) {
    const { data } = await authApi.updateProfile(payload)
    user.value = mapUser(data.user)
    return data
  }

  async function logout() {
    try {
      if (token.value) await authApi.logout()
    } finally {
      token.value = null
      user.value = null
      localStorage.removeItem('nocpilot_token')
    }
  }

  async function init() {
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

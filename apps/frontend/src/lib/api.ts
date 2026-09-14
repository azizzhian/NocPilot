import axios from 'axios'

const TOKEN_KEY = 'nocpilot_token'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api/v1',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = sessionStorage.getItem(TOKEN_KEY)
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const message = error.response?.data?.message
    if (typeof message === 'string' && message.includes('SQLSTATE')) {
      error.response.data.message = 'Gagal memproses data. Periksa kembali isian formulir.'
    }

    if (error.response?.status === 401) {
      // An older request may fail after a new account has logged in. Only clear
      // the token when this response belongs to the account that sent it.
      const requestToken = configToken(error.config)
      const isCurrentSession = !requestToken || requestToken === sessionStorage.getItem(TOKEN_KEY)
      if (isCurrentSession) {
        sessionStorage.removeItem(TOKEN_KEY)
        window.dispatchEvent(new CustomEvent('nocpilot:auth-invalid'))
        if (!window.location.pathname.includes('/login')) {
          window.location.href = '/login'
        }
      }
    }
    return Promise.reject(error)
  },
)

function configToken(config?: { headers?: unknown }): string | null {
  const headers = config?.headers as { Authorization?: string } | undefined
  const authorization = headers?.Authorization
  return typeof authorization === 'string' && authorization.startsWith('Bearer ')
    ? authorization.slice('Bearer '.length)
    : null
}

export default api

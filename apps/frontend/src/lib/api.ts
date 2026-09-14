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
  const token = localStorage.getItem(TOKEN_KEY)
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
      localStorage.removeItem(TOKEN_KEY)
      // Auth store mendengarkan event ini untuk reset Pinia + redirect
      window.dispatchEvent(new CustomEvent('nocpilot:auth-invalid'))
    }
    return Promise.reject(error)
  },
)

export default api

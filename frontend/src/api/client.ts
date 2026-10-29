import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios'

const api = axios.create({
  // Same-origin in dev (Vite proxies /api and /sanctum to the API).
  // A full URL here splits localhost vs 127.0.0.1 and breaks CSRF cookies.
  baseURL: import.meta.env.VITE_API_URL ?? '',
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

let csrfReady = false
let stepUpHandler: (() => Promise<boolean>) | null = null

export function setStepUpHandler(handler: (() => Promise<boolean>) | null): void {
  stepUpHandler = handler
}

export async function ensureCsrf(): Promise<void> {
  if (csrfReady) {
    return
  }
  await api.get('/sanctum/csrf-cookie')
  csrfReady = true
}

function readXsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : null
}

api.interceptors.request.use(async (config: InternalAxiosRequestConfig) => {
  const method = config.method?.toLowerCase()
  if (method && method !== 'get' && method !== 'head' && method !== 'options') {
    await ensureCsrf()
    const token = readXsrfToken()
    if (token) {
      config.headers.set('X-XSRF-TOKEN', token)
    }
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError<{ code?: string; message?: string }>) => {
    const original = error.config as InternalAxiosRequestConfig & { _retried?: boolean }
    const status = error.response?.status
    const code = error.response?.data?.code
    const message = error.response?.data?.message ?? ''

    if (status === 419 && original && !original._retried) {
      csrfReady = false
      original._retried = true
      await ensureCsrf()
      const token = readXsrfToken()
      if (token) {
        original.headers.set('X-XSRF-TOKEN', token)
      }
      return api.request(original)
    }

    if (code === 'mfa_step_up_required' && stepUpHandler && original && !original._retried) {
      const ok = await stepUpHandler()
      if (ok) {
        original._retried = true
        return api.request(original)
      }
    }

    if (status === 419 || message.toLowerCase().includes('csrf')) {
      error.message = 'انتهت جلسة الحماية. حدّث الصفحة ثم أعد المحاولة.'
    }

    throw error
  },
)

export default api

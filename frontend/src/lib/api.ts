import axios from 'axios'

export const isNativeApp = process.env.MODE === 'capacitor'
export const defaultApiUrl = process.env.PAW_API_URL || '/api'

export function normalizeApiUrl(value: string): string {
  const url = new URL(value.trim())
  if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password || url.search || url.hash) {
    throw new Error('Enter a plain HTTP or HTTPS server address, without a username, password, query, or fragment.')
  }
  url.pathname = url.pathname.replace(/\/+$/, '')
  if (!url.pathname.endsWith('/api')) url.pathname += '/api'
  return url.toString().replace(/\/+$/, '')
}

export function getApiUrl(): string {
  if (!isNativeApp) return import.meta.env.VITE_API_URL || '/api'
  const saved = localStorage.getItem('pawadopt-api-url')
  try { return normalizeApiUrl(saved || defaultApiUrl) }
  catch { return defaultApiUrl }
}

export function saveApiUrl(value: string): void {
  const normalized = normalizeApiUrl(value)
  if (normalized !== getApiUrl()) sessionStorage.removeItem('pawadopt-token')
  localStorage.setItem('pawadopt-api-url', normalized)
}

export const api = axios.create({
  baseURL: getApiUrl(),
  timeout: 12000,
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use(config => {
  const token = sessionStorage.getItem('pawadopt-token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(response => response, error => {
  if (axios.isAxiosError(error) && error.response?.status === 401) {
    sessionStorage.removeItem('pawadopt-token')
    window.dispatchEvent(new Event('pawadopt:session-ended'))
  }
  return Promise.reject(error)
})

export function errorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const errors = error.response?.data?.errors
    if (errors) return Object.values(errors).flat().join(' ')
    if (error.response?.data?.message) return error.response.data.message
    return 'We could not reach PAWADOPT. Please check that the backend is running and try again.'
  }
  return 'Something went wrong. Please try again.'
}

export interface Pet {
  id: number; slug: string; name: string; species: string; breed: string;
  age_months: number; gender: string; size: string; location: string;
  description: string; traits: string[]; image: string; adoption_fee: number; status: string;
}
export interface Application {
  id: number; reference: string; pet: Pet; status: string; created_at: string; review_note?: string | null;
}
export function statusLabel(status: string): string {
  return ({ submitted: 'Submitted', reviewing: 'Under review', approved: 'Approved', rejected: 'Not selected', available: 'Available', on_hold: 'On hold', adopted: 'Adopted', admin: 'Admin', manager: 'Manager', adopter: 'Adopter' } as Record<string, string>)[status] || status
}
export function ageLabel(months: number): string {
  if (months < 12) return `${months} months`
  const years = Math.floor(months / 12)
  const remaining = months % 12
  return `${years} ${years === 1 ? 'year' : 'years'}${remaining ? ` ${remaining} mo` : ''}`
}
export const money = (value: number) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(value)

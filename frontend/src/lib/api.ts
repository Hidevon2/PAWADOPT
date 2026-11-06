import axios from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  timeout: 12000,
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use(config => {
  const token = sessionStorage.getItem('pawadopt-token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
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
  id: number; reference: string; pet: Pet; status: string; created_at: string;
}
export function ageLabel(months: number): string {
  if (months < 12) return `${months} months`
  const years = Math.floor(months / 12)
  const remaining = months % 12
  return `${years} ${years === 1 ? 'year' : 'years'}${remaining ? ` ${remaining} mo` : ''}`
}
export const money = (value: number) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(value)

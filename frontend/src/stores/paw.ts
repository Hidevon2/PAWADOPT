import { defineStore } from 'pinia'
import { onScopeDispose, ref } from 'vue'
import { api, type Pet } from '../lib/api'

function guestFavorites(): number[] {
  try {
    const value = JSON.parse(localStorage.getItem('pawadopt-saved') || '[]')
    return Array.isArray(value) ? value.filter(Number.isInteger) : []
  } catch { return [] }
}

export const usePawStore = defineStore('paw', () => {
  const pets = ref<Pet[]>([])
  const loading = ref(true)
  const catalogError = ref(false)
  const user = ref<{ id: number; name: string; email: string; role: 'adopter' | 'manager' | 'admin' } | null>(null)
  const favorites = ref<number[]>(guestFavorites())
  const saving = ref<number[]>([])
  const connected = ref(false)
  const endSession = () => { user.value = null; favorites.value = guestFavorites(); saving.value = [] }
  window.addEventListener('pawadopt:session-ended', endSession)
  onScopeDispose(() => window.removeEventListener('pawadopt:session-ended', endSession))

  async function loadPets() {
    loading.value = true
    catalogError.value = false
    try {
      const { data } = await api.get('/pets')
      pets.value = data.data
      connected.value = true
    } catch { catalogError.value = true; connected.value = false }
    finally { loading.value = false }
  }

  async function restoreSession() {
    if (!sessionStorage.getItem('pawadopt-token')) return
    try {
      user.value = (await api.get('/me')).data.user
      favorites.value = (await api.get('/favorites')).data.data
    } catch {
      sessionStorage.removeItem('pawadopt-token')
      user.value = null
      favorites.value = guestFavorites()
    }
  }

  async function authenticate(mode: 'login' | 'register', fields: Record<string, string>) {
    const { data } = await api.post(`/${mode}`, fields)
    sessionStorage.setItem('pawadopt-token', data.token)
    user.value = data.user
    const saved = (await api.get('/favorites')).data.data as number[]
    const guest = guestFavorites().filter(id => pets.value.some(pet => pet.id === id))
    for (const id of guest) await api.put(`/favorites/${id}`)
    favorites.value = [...new Set([...saved, ...guest])]
    localStorage.removeItem('pawadopt-saved')
  }

  async function logout() {
    await api.post('/logout')
    sessionStorage.removeItem('pawadopt-token')
    user.value = null
    favorites.value = guestFavorites()
  }

  async function toggleFavorite(id: number) {
    if (saving.value.includes(id)) return
    saving.value.push(id)
    const exists = favorites.value.includes(id)
    try {
      if (user.value) {
        if (exists) await api.delete(`/favorites/${id}`)
        else await api.put(`/favorites/${id}`)
      }
      favorites.value = exists ? favorites.value.filter(value => value !== id) : [...favorites.value, id]
      if (!user.value) localStorage.setItem('pawadopt-saved', JSON.stringify(favorites.value))
    } finally { saving.value = saving.value.filter(value => value !== id) }
  }

  return { pets, loading, catalogError, user, favorites, saving, connected, loadPets, restoreSession, authenticate, logout, toggleFavorite }
})

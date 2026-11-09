<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { usePawStore } from '../stores/paw'
import { api, errorMessage, ageLabel, money, statusLabel, type Pet } from '../lib/api'
import PawIcon from './PawIcon.vue'

defineEmits<{ signIn: [] }>()
const store = usePawStore()
const $q = useQuasar()
const isStaff = computed(() => ['admin', 'manager'].includes(store.user?.role || ''))
const isAdmin = computed(() => store.user?.role === 'admin')
const tab = ref('Applications')
const tabs = computed(() => ['Applications', 'Pets', 'Messages', ...(isAdmin.value ? ['Accounts'] : [])])
const stats = ref({ available_pets: 0, pending_applications: 0, adopted_pets: 0, open_messages: 0 })
const loading = ref(false), busy = ref(false), error = ref(''), actionError = ref('')
const search = ref(''), status = ref('All statuses'), currentPage = ref(1), lastPage = ref(1)
interface Review { id: number; reference: string; status: string; pet: Pet; user: { name: string; email: string }; phone: string; city: string; housing: string; experience: string; message: string; review_note: string | null; created_at: string }
interface Message { id: number; name: string; email: string; subject: string; message: string; resolved_at: string | null }
interface Account { id: number; name: string; email: string; role: string }
const applications = ref<Review[]>([]), messages = ref<Message[]>([]), accounts = ref<Account[]>([])
const roleChoices = ref<Record<number, string>>({})
const visiblePets = computed(() => store.pets.filter(p => `${p.name} ${p.breed} ${p.location}`.toLowerCase().includes(search.value.toLowerCase())))
const selected = ref<Review | null>(null), reviewNote = ref(''), reviewOpen = ref(false)
const petOpen = ref(false), editingId = ref<number | null>(null), traitsText = ref('')
const emptyPet = () => ({ name: '', species: 'Dogs', breed: '', age_months: 12, gender: 'Male', size: 'Small', location: '', description: '', traits: [] as string[], image: '/images/biscuit.jpg', adoption_fee: 0, status: 'available' })
const petFields = ref(emptyPet())
const imageOptions = ['biscuit', 'luna', 'milo', 'echo', 'olive', 'cedar', 'sage', 'willow'].map(name => ({ label: name.charAt(0).toUpperCase() + name.slice(1) + ' photo', value: `/images/${name}.jpg` }))
const required = (v: unknown) => !!String(v ?? '').trim() || 'This field is required'
const date = (value: string) => new Date(value).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })

async function load(reset = false) {
  if (!isStaff.value) return
  if (reset) currentPage.value = 1
  loading.value = true; error.value = ''
  try {
    stats.value = (await api.get('/staff/overview')).data.data
    const params = { page: currentPage.value, search: search.value, ...(status.value !== 'All statuses' ? { status: status.value } : {}) }
    if (tab.value === 'Pets') { await store.loadPets(); if (store.catalogError) throw new Error('Catalog unavailable'); lastPage.value = 1 }
    else {
      const endpoint = tab.value === 'Applications' ? 'applications' : tab.value === 'Messages' ? 'messages' : 'users'
      const { data } = await api.get(`/staff/${endpoint}`, { params })
      lastPage.value = data.last_page
      if (endpoint === 'applications') applications.value = data.data
      else if (endpoint === 'messages') messages.value = data.data
      else { accounts.value = data.data; roleChoices.value = Object.fromEntries(accounts.value.map(u => [u.id, u.role])) }
    }
  } catch (e) { error.value = errorMessage(e) }
  finally { loading.value = false }
}
watch(tab, () => { search.value = ''; status.value = 'All statuses'; void load(true) })
watch(() => store.user?.role, () => { if (!tabs.value.includes(tab.value)) tab.value = 'Applications'; void load(true) })
onMounted(() => { void load() })
function openReview(application: Review) { selected.value = application; reviewNote.value = application.review_note || ''; actionError.value = ''; reviewOpen.value = true }
async function review(decision: string) {
  if (!selected.value || busy.value) return
  busy.value = true; actionError.value = ''
  try {
    await api.patch(`/staff/applications/${selected.value.id}`, { status: decision, review_note: reviewNote.value })
    reviewOpen.value = false; await load(); await store.loadPets()
    $q.notify({ message: `Application ${statusLabel(decision).toLowerCase()}.`, color: 'dark', icon: 'sym_r_check_circle' })
  } catch (e) { actionError.value = errorMessage(e) }
  finally { busy.value = false }
}
function editPet(pet?: Pet) { editingId.value = pet?.id || null; petFields.value = pet ? { ...pet, traits: [...pet.traits] } : emptyPet(); traitsText.value = pet?.traits.join(', ') || ''; actionError.value = ''; petOpen.value = true }
async function savePet() {
  if (busy.value) return
  busy.value = true; actionError.value = ''
  try {
    const data = { ...petFields.value, traits: traitsText.value.split(',').map(s => s.trim()).filter(Boolean) }
    if (editingId.value) await api.put(`/staff/pets/${editingId.value}`, data)
    else await api.post('/staff/pets', data)
    petOpen.value = false; await load()
    $q.notify({ message: 'Pet profile saved.', color: 'dark', icon: 'sym_r_check_circle' })
  } catch (e) { actionError.value = errorMessage(e) }
  finally { busy.value = false }
}
async function resolveMessage(message: Message) {
  busy.value = true; error.value = ''
  try { await api.patch(`/staff/messages/${message.id}`, { resolved: !message.resolved_at }); await load() }
  catch (e) { error.value = errorMessage(e) }
  finally { busy.value = false }
}
async function saveRole(account: Account) {
  busy.value = true; error.value = ''
  try { await api.patch(`/staff/users/${account.id}/role`, { role: roleChoices.value[account.id] }); await load(); $q.notify({ message: 'Role updated. This account must sign in again.', color: 'dark' }) }
  catch (e) { error.value = errorMessage(e) }
  finally { busy.value = false }
}
const passwordOpen = ref(false), passwordError = ref('')
const passwords = ref({ current_password: '', password: '', password_confirmation: '' })
async function changePassword() {
  busy.value = true; passwordError.value = ''
  try { await api.put('/account/password', passwords.value); passwordOpen.value = false; $q.notify({ message: 'Password changed. Other sessions have been signed out.', color: 'dark' }) }
  catch (e) { passwordError.value = errorMessage(e) }
  finally { busy.value = false }
}
</script>

<template>
  <section v-if="!isStaff" class="empty-state panel"><PawIcon name="admin_panel_settings" /><h2>Staff workspace</h2><p>Sign in with an admin or manager account to manage this project.</p><button v-if="!store.user" class="primary-button" @click="$emit('signIn')">Sign in</button><router-link v-else to="/" class="primary-button">Back to pets</router-link></section>
  <section v-else class="staff-workspace">
    <div class="staff-heading"><div><span class="eyebrow">{{ isAdmin ? 'ADMIN' : 'MANAGER' }} WORKSPACE</span><h1>A little care.<br />A brighter future.</h1><p>Welcome, {{ store.user?.name }}. Keep every adoption moving with care.</p></div><button class="text-link" @click="passwordOpen = true; passwordError = ''"><PawIcon name="lock_reset" />Change password</button></div>
    <div class="staff-stats"><article v-for="(label, key) in { available_pets: 'Available pets', pending_applications: 'Awaiting review', adopted_pets: 'Adopted pets', open_messages: 'Open messages' }" :key="key"><span>{{ label }}</span><strong>{{ stats[key] }}</strong></article></div>
    <div class="staff-tabs" aria-label="Workspace sections"><button v-for="item in tabs" :key="item" :class="{ active: tab === item }" :aria-pressed="tab === item" :disabled="loading || busy" @click="tab = item">{{ item }}</button></div>
    <q-form class="staff-toolbar" @submit="load(true)"><q-input v-if="tab !== 'Messages'" v-model="search" outlined dense :label="tab === 'Accounts' ? 'Search name or email' : tab === 'Pets' ? 'Search pets' : 'Search applicant, pet or reference'" maxlength="100" /><q-select v-if="tab === 'Applications'" v-model="status" outlined dense label="Application status" :options="['All statuses', 'submitted', 'reviewing', 'approved', 'rejected']" /><q-btn outline no-caps color="primary" label="Refresh" :loading="loading" type="submit" /><q-btn v-if="tab === 'Pets'" unelevated no-caps color="primary" text-color="dark" label="Add pet" icon="sym_r_add" @click="editPet()" /></q-form>
    <div v-if="error" class="form-error" role="alert">{{ error }}</div>
    <div v-if="loading" class="staff-empty" role="status">Loading your workspace…</div>
    <template v-else>
      <div v-if="tab === 'Applications'" class="staff-list"><article v-for="item in applications" :key="item.id" class="staff-card"><img :src="item.pet.image" :alt="item.pet.name" /><div class="staff-card-copy"><span class="section-kicker">{{ item.reference }} · {{ date(item.created_at) }}</span><h3>{{ item.user.name }} <span class="staff-for">for</span> {{ item.pet.name }}</h3><p>{{ item.city }} · {{ item.housing }}</p><span class="staff-status" :class="item.status">{{ statusLabel(item.status) }}</span></div><q-btn outline no-caps color="primary" label="View application" @click="openReview(item)" /></article><p v-if="!applications.length" class="staff-empty">No applications match this view.</p></div>
      <div v-else-if="tab === 'Pets'" class="staff-list"><article v-for="pet in visiblePets" :key="pet.id" class="staff-card"><img :src="pet.image" :alt="pet.name" /><div class="staff-card-copy"><h3>{{ pet.name }}</h3><p>{{ pet.breed }} · {{ ageLabel(pet.age_months) }} · {{ money(pet.adoption_fee) }}</p><span class="staff-status" :class="pet.status">{{ statusLabel(pet.status) }}</span></div><q-btn outline no-caps color="primary" :label="`Edit ${pet.name}`" @click="editPet(pet)" /></article><p v-if="!visiblePets.length" class="staff-empty">No pets match your search.</p></div>
      <div v-else-if="tab === 'Messages'" class="staff-list"><article v-for="message in messages" :key="message.id" class="staff-message panel"><div class="staff-message-top"><div><span class="section-kicker">{{ message.resolved_at ? 'RESOLVED' : 'OPEN MESSAGE' }}</span><h3>{{ message.subject }}</h3><p>{{ message.name }} · {{ message.email }}</p></div><q-btn outline no-caps color="primary" :label="message.resolved_at ? 'Reopen' : 'Mark resolved'" :disable="busy" @click="resolveMessage(message)" /></div><p class="staff-body-text">{{ message.message }}</p></article><p v-if="!messages.length" class="staff-empty">Your inbox is clear.</p></div>
      <div v-else-if="isAdmin && tab === 'Accounts'" class="staff-list"><p class="staff-hint">Role changes sign the affected account out. You cannot change your own role.</p><article v-for="account in accounts" :key="account.id" class="staff-card staff-account"><div class="staff-account-avatar"><PawIcon name="person" /></div><div class="staff-card-copy"><h3>{{ account.name }}</h3><p>{{ account.email }}</p><span class="staff-status">{{ statusLabel(account.role) }}</span></div><div class="staff-role-control"><q-select v-model="roleChoices[account.id]" outlined dense label="Account role" :options="['adopter', 'manager', 'admin']" :disable="busy || account.id === store.user?.id" /><q-btn outline no-caps color="primary" label="Save role" :disable="busy || account.id === store.user?.id || roleChoices[account.id] === account.role" @click="saveRole(account)" /></div></article><p v-if="!accounts.length" class="staff-empty">No matching accounts.</p></div>
      <div v-if="tab !== 'Pets' && lastPage > 1" class="staff-pagination"><q-btn outline no-caps color="primary" label="Previous" :disable="currentPage <= 1" @click="currentPage--; load()" /><span>Page {{ currentPage }} of {{ lastPage }}</span><q-btn outline no-caps color="primary" label="Next" :disable="currentPage >= lastPage" @click="currentPage++; load()" /></div>
    </template>
    <p class="staff-hint">A fictional classroom workflow. Decisions are saved in this project; no shelter or applicant is contacted.</p>
  </section>

  <q-dialog v-model="reviewOpen" :persistent="busy"><div v-if="selected" class="dialog-surface staff-dialog"><button class="close-dialog" aria-label="Close application review" :disabled="busy" @click="reviewOpen = false"><PawIcon name="close" /></button><span class="eyebrow">{{ selected.reference }}</span><h2>{{ selected.user.name }} &amp; {{ selected.pet.name }}</h2><span class="staff-status" :class="selected.status">{{ statusLabel(selected.status) }}</span><dl class="staff-details"><div><dt>Email</dt><dd>{{ selected.user.email }}</dd></div><div><dt>Phone</dt><dd>{{ selected.phone }}</dd></div><div><dt>Home</dt><dd>{{ selected.housing }} · {{ selected.city }}</dd></div><div><dt>Experience</dt><dd>{{ selected.experience }}</dd></div></dl><p class="staff-body-text">{{ selected.message }}</p><template v-if="['submitted', 'reviewing'].includes(selected.status)"><q-input v-model="reviewNote" outlined type="textarea" label="Note for the applicant (optional)" maxlength="2000" :disable="busy" /><p class="staff-hint">Approving marks this pet adopted and closes other pending applications for the same pet.</p><div v-if="actionError" class="form-error" role="alert">{{ actionError }}</div><div class="staff-review-actions"><q-btn outline no-caps color="primary" label="Under review" :disable="busy" @click="review('reviewing')" /><q-btn outline no-caps color="negative" label="Reject application" :disable="busy" @click="review('rejected')" /><q-btn unelevated no-caps color="primary" text-color="dark" label="Approve adoption" :loading="busy" @click="review('approved')" /></div></template><p v-else class="gentle-note">{{ selected.review_note || 'This application has been decided.' }}</p></div></q-dialog>

  <q-dialog v-model="petOpen" :persistent="busy"><div class="dialog-surface staff-dialog"><button class="close-dialog" aria-label="Close pet editor" :disabled="busy" @click="petOpen = false"><PawIcon name="close" /></button><span class="eyebrow">PET PROFILES</span><h2>{{ editingId ? 'A little profile care.' : 'Welcome a new friend.' }}</h2><q-form @submit="savePet"><div class="form-row"><q-input v-model="petFields.name" outlined label="Pet name" maxlength="80" :rules="[required]" /><q-input v-model="petFields.breed" outlined label="Breed" maxlength="100" :rules="[required]" /></div><div class="form-row"><q-select v-model="petFields.species" outlined label="Species" :options="['Dogs', 'Cats', 'Rabbits', 'Birds', 'Small pets']" /><q-input v-model.number="petFields.age_months" outlined label="Age in months" type="number" min="1" max="600" :rules="[v => Number.isInteger(v) && v >= 1 && v <= 600 || 'Use 1–600 months']" /></div><div class="form-row"><q-select v-model="petFields.gender" outlined label="Gender" :options="['Male', 'Female']" /><q-select v-model="petFields.size" outlined label="Size" :options="['Small', 'Medium', 'Large']" /></div><q-input v-model="petFields.location" outlined label="Location" maxlength="120" :rules="[required]" /><q-input v-model="petFields.description" outlined type="textarea" label="Pet story and care needs" maxlength="4000" :rules="[v => v.trim().length >= 20 || 'Write at least 20 characters']" /><q-input v-model="traitsText" outlined label="Traits, separated by commas" hint="Up to 8 traits, each 40 characters or fewer." /><q-select v-model="petFields.image" outlined label="Illustrative photo" :options="imageOptions" emit-value map-options /><div class="form-row"><q-input v-model.number="petFields.adoption_fee" outlined label="Illustrative fee (PHP)" type="number" min="0" max="100000" /><q-select v-model="petFields.status" outlined label="Availability" :options="['available', 'on_hold', 'adopted']" /></div><div v-if="actionError" class="form-error" role="alert">{{ actionError }}</div><q-btn class="form-submit" type="submit" label="Save pet profile" unelevated color="primary" text-color="dark" :loading="busy" /></q-form></div></q-dialog>

  <q-dialog v-model="passwordOpen" :persistent="busy" @hide="passwords = { current_password: '', password: '', password_confirmation: '' }"><div class="dialog-surface staff-dialog"><button class="close-dialog" aria-label="Close password settings" :disabled="busy" @click="passwordOpen = false"><PawIcon name="close" /></button><span class="eyebrow">YOUR ACCOUNT</span><h2>A password of your own.</h2><q-form @submit="changePassword"><q-input v-model="passwords.current_password" outlined label="Current password" type="password" autocomplete="current-password" :rules="[required]" /><q-input v-model="passwords.password" outlined label="New password" type="password" autocomplete="new-password" :rules="[v => v.length >= 12 || 'Use at least 12 characters']" /><q-input v-model="passwords.password_confirmation" outlined label="Confirm new password" type="password" autocomplete="new-password" :rules="[v => v === passwords.password || 'Passwords must match']" /><p v-if="passwordError" class="form-error" role="alert">{{ passwordError }}</p><q-btn class="form-submit" type="submit" label="Change password" unelevated color="primary" text-color="dark" :loading="busy" /></q-form></div></q-dialog>
</template>

<style scoped>
.staff-heading { display:flex; align-items:center; justify-content:space-between; gap:20px; margin:16px 0 28px; }
.staff-heading h1 { font-size:38px; letter-spacing:-1.5px; line-height:1.2; margin:12px 0; }
.staff-heading p,.staff-hint { color:var(--muted); font-size:12px; }
.staff-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:28px; }
.staff-stats article { border:1px solid var(--line); background:var(--surface); padding:22px; border-radius:12px; }
.staff-stats span { color:var(--muted); font-size:11px; display:block; }
.staff-stats strong { font:600 35px 'Manrope',sans-serif; display:block; margin-top:10px; color:var(--accent); }
.staff-tabs { display:flex; gap:8px; flex-wrap:wrap; border-bottom:1px solid var(--line); padding-bottom:12px; margin-bottom:20px; }
.staff-tabs button { padding:12px 18px; border-radius:8px; color:var(--muted); }
.staff-tabs button.active { color:var(--accent); background:var(--surface-raised); }
.staff-toolbar { display:flex; gap:12px; align-items:flex-start; margin-bottom:16px; flex-wrap:wrap; }
.staff-toolbar .q-input { flex:1; min-width:200px; }.staff-toolbar .q-select { min-width:180px; }.staff-toolbar .q-btn { min-height:40px; }
.staff-list { display:grid; gap:14px; }.staff-card { display:flex; align-items:center; gap:18px; border:1px solid var(--line); border-radius:12px; background:var(--surface); padding:18px; }
.staff-card>img { width:70px; height:80px; border-radius:8px; object-fit:cover; }.staff-card-copy { flex:1; min-width:0; }.staff-card h3 { font-size:17px; margin:7px 0; }.staff-card p,.staff-message p { color:var(--muted); font-size:12px; overflow-wrap:anywhere; }.staff-for { color:var(--muted); font-weight:400; }
.staff-status { display:inline-block; padding:5px 9px; border-radius:6px; font-size:10px; color:var(--accent); background:var(--surface-raised); margin-top:7px; }.staff-status.approved,.staff-status.available { color:var(--green); }.staff-status.rejected,.staff-status.adopted { color:var(--muted); }
.staff-message { padding:23px; }.staff-message h3 { margin:9px 0; font-size:18px; }.staff-message-top { display:flex; justify-content:space-between; align-items:center; gap:16px; }.staff-body-text { white-space:pre-wrap; overflow-wrap:anywhere; color:var(--text)!important; line-height:1.8; margin:22px 0; font-size:13px; }
.staff-account-avatar { width:46px; height:46px; background:var(--surface-raised); color:var(--accent); display:grid; place-items:center; border-radius:50%; }.staff-role-control { display:flex; gap:10px; align-items:flex-start; }.staff-role-control .q-select { width:130px; margin:0; }.staff-role-control .q-btn { min-height:40px; }
.staff-pagination { display:flex; justify-content:center; align-items:center; gap:18px; margin:24px 0; font-size:12px; }.staff-empty { padding:40px 20px; text-align:center; color:var(--muted); }.staff-hint { margin:20px 0; line-height:1.8; }
.staff-dialog { width:620px; padding:34px; max-width:calc(100vw - 30px)!important; }.staff-dialog h2 { margin:14px 35px 20px 0; font-size:25px; line-height:1.4; }.staff-details { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin:24px 0; }.staff-details dt { color:var(--muted); font-size:10px; margin-bottom:5px; }.staff-details dd { margin:0; font-size:12px; overflow-wrap:anywhere; }.staff-review-actions { display:flex; gap:10px; flex-wrap:wrap; }.staff-review-actions .q-btn { min-height:44px; }
@media(max-width:760px) { .staff-heading { align-items:flex-start; flex-direction:column; gap:2px; }.staff-heading h1 { font-size:31px; }.staff-stats { grid-template-columns:1fr 1fr; gap:10px; }.staff-stats article { padding:17px; }.staff-stats strong { font-size:29px; }.staff-tabs { gap:3px; }.staff-tabs button { padding:12px; font-size:12px; }.staff-card { flex-wrap:wrap; gap:12px; padding:15px; }.staff-card-copy { flex:1 1 calc(100% - 90px); }.staff-card>.q-btn { margin-left:82px; flex:1 0 calc(100% - 82px); }.staff-message-top { align-items:flex-start; flex-direction:column; }.staff-role-control { width:100%; }.staff-role-control .q-select { flex:1; }.staff-dialog { padding:29px 22px; }.staff-dialog .form-row { grid-template-columns:1fr; gap:0; }.staff-details { grid-template-columns:1fr; gap:12px; }.staff-toolbar .q-input { min-width:100%; }.staff-toolbar .q-select { flex:1; min-width:145px; } }
</style>

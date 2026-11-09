<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { usePawStore } from '../stores/paw'
import { api, errorMessage, ageLabel, money, type Pet, type Application } from '../lib/api'
import PawIcon from '../components/PawIcon.vue'
import PetCard from '../components/PetCard.vue'
import MobileConnection from '../components/MobileConnection.vue'
import { isNativeApp } from '../lib/api'
import { version as appVersion } from '../../package.json'

const store = usePawStore()
const route = useRoute()
const router = useRouter()
const $q = useQuasar()
const page = computed(() => route.path.split('/')[1] || 'home')
const isHome = computed(() => page.value === 'home')
const mobileMenu = ref(false)
const light = ref(localStorage.getItem('pawadopt-theme') === 'light')
watch(light, value => { localStorage.setItem('pawadopt-theme', value ? 'light' : 'dark'); $q.dark.set(!value) }, { immediate: true })
const nav = [
  { page: 'home', path: '/', label: 'Home', icon: 'space_dashboard' },
  { page: 'pets', path: '/pets', label: 'Find a friend', icon: 'pets' },
  { page: 'saved', path: '/saved', label: 'Saved pets', icon: 'favorite' },
  { page: 'applications', path: '/applications', label: 'My applications', icon: 'assignment' },
]
const secondaryNav = [
  { page: 'about', path: '/about', label: 'About us', icon: 'info' },
  { page: 'faq', path: '/faq', label: 'Help & FAQs', icon: 'help' },
  { page: 'contact', path: '/contact', label: 'Get in touch', icon: 'mail' },
]
const categories = [
  { label: 'All pets', icon: 'pets' }, { label: 'Dogs', icon: 'sound_detection_dog_barking' },
  { label: 'Cats', icon: 'cat' }, { label: 'Rabbits', icon: 'cruelty_free' },
  { label: 'Birds', icon: 'raven' }, { label: 'Small pets', icon: 'eco' },
]
const species = ref('All pets')
const search = ref('')
const location = ref('All locations')
const gender = ref('Any gender')
const sort = ref('Newest arrivals')
const locations = computed(() => ['All locations', ...new Set(store.pets.map(pet => pet.location))])
const filteredPets = computed(() => {
  const query = search.value.trim().toLowerCase()
  const results = store.pets.filter(pet =>
    (species.value === 'All pets' || pet.species === species.value) &&
    (location.value === 'All locations' || pet.location === location.value) &&
    (gender.value === 'Any gender' || pet.gender === gender.value) &&
    (page.value !== 'saved' || store.favorites.includes(pet.id)) &&
    (!query || [pet.name, pet.breed, pet.species, pet.location].join(' ').toLowerCase().includes(query)),
  )
  if (sort.value === 'Name: A–Z') results.sort((a, b) => a.name.localeCompare(b.name))
  if (sort.value === 'Youngest first') results.sort((a, b) => a.age_months - b.age_months)
  return isHome.value ? results.slice(0, 4) : results
})
const filterActive = computed(() => search.value || species.value !== 'All pets' || location.value !== 'All locations' || gender.value !== 'Any gender')
function clearFilters() { search.value = ''; species.value = 'All pets'; location.value = 'All locations'; gender.value = 'Any gender' }
function go(path: string) { mobileMenu.value = false; void router.push(path) }
function browse(category = 'All pets') { species.value = category; go('/pets') }
const selectedPet = ref<Pet | null>(null)
const petDialog = ref(false)
function openPet(pet: Pet) { selectedPet.value = pet; petDialog.value = true }
async function savePet(id: number) {
  try {
    await store.toggleFavorite(id)
    $q.notify({ message: store.favorites.includes(id) ? 'A little love, saved for later.' : 'Pet removed from your saved list.', icon: 'sym_r_favorite', position: 'bottom-right', timeout: 1800, color: 'dark' })
  } catch (error) { $q.notify({ message: errorMessage(error), color: 'negative' }) }
}

const authDialog = ref(false)
const authMode = ref<'login' | 'register'>('login')
const authFields = ref({ name: '', email: '', password: '', password_confirmation: '' })
const authError = ref('')
const authBusy = ref(false)
const showPassword = ref(false)
const pendingAdoption = ref<Pet | null>(null)
function openAuth(mode: 'login' | 'register') { authMode.value = mode; authError.value = ''; showPassword.value = false; authDialog.value = true; mobileMenu.value = false }
function closeAuth() { pendingAdoption.value = null; authFields.value.password = ''; authFields.value.password_confirmation = ''; showPassword.value = false; authError.value = '' }
watch(authMode, () => { authError.value = '' })
async function submitAuth() {
  if (authBusy.value) return
  authBusy.value = true; authError.value = ''
  try {
    await store.authenticate(authMode.value, authFields.value)
    authDialog.value = false
    authFields.value = { name: '', email: '', password: '', password_confirmation: '' }
    $q.notify({ message: `Welcome${store.user?.name ? `, ${store.user.name.split(' ')[0]}` : ''}! Your next chapter starts here.`, color: 'dark', icon: 'sym_r_waving_hand' })
    if (pendingAdoption.value) { selectedPet.value = pendingAdoption.value; pendingAdoption.value = null; applicationDialog.value = true }
    if (page.value === 'applications') await loadApplications()
  } catch (error) { authError.value = errorMessage(error) }
  finally { authBusy.value = false }
}
async function signOut() {
  try { await store.logout(); applications.value = []; $q.notify({ message: 'You have signed out.', color: 'dark' }) }
  catch (error) { $q.notify({ message: errorMessage(error), color: 'negative' }) }
}
const applicationDialog = ref(false)
const applicationBusy = ref(false)
const applicationError = ref('')
const applicationFields = ref({ phone: '', city: '', housing: 'House', experience: 'First-time adopter', message: '' })
const applicationConsent = ref(false)
const successReference = ref('')
function startApplication(pet: Pet) {
  selectedPet.value = pet; petDialog.value = false; applicationError.value = ''; successReference.value = ''; applicationConsent.value = false
  if (!store.user) { pendingAdoption.value = pet; openAuth('register'); return }
  applicationDialog.value = true
}
async function submitApplication() {
  if (!selectedPet.value || !applicationConsent.value) return
  applicationBusy.value = true; applicationError.value = ''
  try {
    const { data } = await api.post(`/pets/${selectedPet.value.id}/applications`, applicationFields.value)
    successReference.value = data.data.reference
    applicationFields.value = { phone: '', city: '', housing: 'House', experience: 'First-time adopter', message: '' }
    if (page.value === 'applications') await loadApplications()
  } catch (error) { applicationError.value = errorMessage(error) }
  finally { applicationBusy.value = false }
}
const applications = ref<Application[]>([])
const applicationsBusy = ref(false)
const applicationsError = ref('')
async function loadApplications() {
  if (!store.user) return
  applicationsBusy.value = true; applicationsError.value = ''
  try { applications.value = (await api.get('/applications')).data.data }
  catch (error) { applicationsError.value = errorMessage(error) }
  finally { applicationsBusy.value = false }
}
watch(page, value => {
  document.title = `${[...nav, ...secondaryNav].find(item => item.page === value)?.label || 'Home'} · PAWADOPT`
  mobileMenu.value = false
  if (value === 'applications') void loadApplications()
})
const contactFields = ref({ name: '', email: '', subject: 'Adoption question', message: '' })
const contactBusy = ref(false)
const contactError = ref('')
const contactReference = ref('')
async function submitContact() {
  contactBusy.value = true; contactError.value = ''
  try {
    contactReference.value = (await api.post('/contact', contactFields.value)).data.reference
    contactFields.value = { name: '', email: '', subject: 'Adoption question', message: '' }
  } catch (error) { contactError.value = errorMessage(error) }
  finally { contactBusy.value = false }
}
const backendDialog = ref(false)
const backendBusy = ref(false)
const backendMessage = ref('')
const backendError = ref(false)
async function testBackend() {
  backendDialog.value = true; backendBusy.value = true; backendMessage.value = ''; backendError.value = false
  try { backendMessage.value = (await api.get('/hello')).data.message; store.connected = true }
  catch (error) { backendMessage.value = errorMessage(error); backendError.value = true; store.connected = false }
  finally { backendBusy.value = false }
}
const faqs = [
  { question: 'How does adoption work?', answer: 'Find a pet you connect with, open their profile, and select “Apply to adopt.” Create an account, tell us about your home, and submit your application. In a real shelter workflow, the team would then review your application, arrange a meet-and-greet, and help you prepare. This classroom project stores applications locally; it does not arrange real adoptions.' },
  { question: 'Do I need an account to browse?', answer: 'You can explore pets and save favorites as a guest. Guest favorites stay in this browser. Create an account to submit adoption applications and keep your saved pets in the database. Your guest favorites move into your account when you sign in.' },
  { question: 'Is there an adoption fee?', answer: 'Each pet profile shows an illustrative adoption fee in Philippine pesos. In real shelters, fees may support vaccinations, food, and everyday care. PAWADOPT does not collect payments, and the amounts here are for demonstration only.' },
  { question: 'Can I adopt if I live in an apartment?', answer: 'A suitable home depends on the individual pet’s needs, your available space, your routine, and your landlord or building rules. Select your housing type when applying and explain the home you can provide.' },
  { question: 'What happens after I submit an application?', answer: 'You receive a reference number and can see your submitted application in “My applications.” This version has no shelter staff dashboard or email delivery, so applications remain marked Submitted. No live shelter is contacted.' },
  { question: 'Are these real pets and shelters?', answer: 'No. PAWADOPT is a fictional student project. Pet names, profiles, fees, and stories are sample data. Photographs are illustrative and do not represent actual animals available for adoption.' },
]
const steps = [
  { number: '01', icon: 'search', title: 'Find your connection', text: 'Big paws or tiny whiskers? Meet the friend who feels like you.' },
  { number: '02', icon: 'edit_note', title: 'Tell us your story', text: 'A simple application helps us find a happy match for everyone.' },
  { number: '03', icon: 'cottage', title: 'Begin a new chapter', text: 'Meet your companion and get ready for a lifetime of little moments.' },
]
const required = (value: unknown) => !!String(value || '').trim() || 'This field is required'
const emailRule = (value: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) || 'Enter a valid email address'
onMounted(async () => {
  await Promise.all([store.loadPets(), store.restoreSession()])
  if (page.value === 'applications') await loadApplications()
})
</script>

<template>
  <div class="paw-app" :class="{ 'light-theme': light }">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <div v-if="mobileMenu" class="nav-overlay" @click="mobileMenu = false" />
    <aside class="sidebar" :class="{ 'is-open': mobileMenu }" aria-label="Main navigation">
      <router-link to="/" class="brand" @click="mobileMenu = false"><span class="brand-mark"><PawIcon name="pets" filled /></span><span>PAW<span class="brand-accent">ADOPT</span><small>A home for every paw.</small></span></router-link>
      <div class="nav-caption">YOUR NEXT CHAPTER</div>
      <nav>
        <router-link v-for="item in nav" :key="item.page" :to="item.path" class="nav-item" :class="{ active: page === item.page }" :aria-current="page === item.page ? 'page' : undefined" @click="mobileMenu = false"><PawIcon :name="item.icon" :filled="page === item.page" />{{ item.label }}<span v-if="item.page === 'saved' && store.favorites.length" class="nav-count">{{ store.favorites.length }}</span><span v-if="page === item.page" class="nav-dot" /></router-link>
      </nav>
      <div class="nav-divider" />
      <nav><router-link v-for="item in secondaryNav" :key="item.page" :to="item.path" class="nav-item secondary" :class="{ active: page === item.page }" @click="mobileMenu = false"><PawIcon :name="item.icon" />{{ item.label }}</router-link></nav>
      <div class="sidebar-bottom">
        <MobileConnection v-if="isNativeApp" />
        <div class="sidebar-note"><div class="note-icon"><PawIcon name="volunteer_activism" /></div><h3>A small act.<br />A whole new world.</h3><p>Somewhere, a little friend is waiting just for you.</p><button @click="browse()">Be their person <PawIcon name="arrow_forward" /></button></div>
        <div class="sidebar-foot"><span class="status-dot" />Made with love, for paws.</div>
      </div>
    </aside>

    <div class="app-main">
      <header class="topbar">
        <div class="header-start"><button class="icon-button menu-toggle" aria-label="Open navigation" :aria-expanded="mobileMenu" @click="mobileMenu = !mobileMenu"><PawIcon name="menu" /></button><span class="breadcrumb">PAWADOPT <PawIcon name="chevron_right" /><span>{{ [...nav, ...secondaryNav].find(item => item.page === page)?.label || 'Home' }}</span></span></div>
        <div class="header-actions"><button class="mission-link" @click="go('/about')">Our mission <PawIcon name="north_east" /></button><button class="icon-button theme-toggle" :aria-label="`Switch to ${light ? 'dark' : 'light'} theme`" @click="light = !light"><PawIcon :name="light ? 'dark_mode' : 'light_mode'" /></button><span class="header-divider" /><template v-if="!store.user"><button class="signin-button" @click="openAuth('login')">Sign in</button><button class="primary-button header-join" @click="openAuth('register')">Get started <PawIcon name="arrow_forward" /></button></template><template v-else><span class="user-greeting">Hi, {{ store.user.name.split(' ')[0] }}</span><button class="icon-button" aria-label="Sign out" @click="signOut"><PawIcon name="logout" /></button></template></div>
      </header>

      <main id="main-content" tabindex="-1">
        <template v-if="isHome">
          <div class="welcome-line"><span><PawIcon name="wb_sunny" /> A little love goes a long way.</span><span class="welcome-right">GOOD HOMES. HAPPY HEARTS.</span></div>
          <section class="hero">
            <div class="hero-photo" role="img" aria-label="A golden retriever ready for a new best friend" />
            <div class="hero-shade" />
            <div class="hero-copy"><span class="eyebrow"><span />SOME FRIENDSHIPS CHANGE EVERYTHING</span><h1>A little love.<br />A <em>forever</em> home.</h1><p>Your new best friend is out there.<br />Let’s help you find each other.</p><div class="hero-buttons"><button class="primary-button" @click="browse()">Meet your new friend <PawIcon name="arrow_forward" /></button><button class="hero-how" @click="go('/faq')"><span><PawIcon name="play_arrow" filled /></span>How it works</button></div><div class="hero-footnote"><PawIcon name="favorite" filled />Because every paw deserves a place to belong.</div></div>
            <div class="hero-sticker"><span class="sticker-heart"><PawIcon name="favorite" filled /></span><div>A best friend.<br /><strong>A fresh start.</strong></div><span class="sticker-spark">✧</span></div>
            <span class="hero-image-label">BIG HEARTS. WAGGING TAILS.</span>
          </section>
          <section class="discovery-strip" aria-label="Find pets by type"><div class="discovery-label"><PawIcon name="pets" /><div>Who’s your kind<br /><strong>of companion?</strong></div></div><button v-for="category in categories.slice(1)" :key="category.label" class="discovery-item" @click="browse(category.label)"><PawIcon :name="category.icon" /><span>{{ category.label }}</span><PawIcon name="north_east" class="category-arrow" /></button></section>
        </template>

        <template v-if="['home', 'pets', 'saved'].includes(page)">
          <section class="pets-section">
            <div v-if="!isHome" class="page-intro"><span class="eyebrow">{{ page === 'saved' ? 'A LITTLE LOVE, BOOKMARKED' : 'YOUR PERSON IS WAITING FOR YOU, TOO' }}</span><h1>{{ page === 'saved' ? 'The ones you love.' : 'Find your new best friend.' }}</h1><p>{{ page === 'saved' ? 'Keep your favorites close. Your next chapter could be right here.' : 'Different personalities. The same wish: a place to call home.' }}</p></div>
            <div class="section-heading"><div><div class="section-kicker"><span />{{ isHome ? 'HELLO, NEW FRIENDS' : page === 'saved' ? 'YOUR LITTLE SHORTLIST' : 'MEET THE GANG' }}</div><h2>{{ isHome ? 'New paws on the block' : page === 'saved' ? 'Saved pets' : 'All our lovely companions' }}<span v-if="!store.loading" class="heading-count">{{ isHome ? store.pets.length : filteredPets.length }}</span></h2><p v-if="isHome">Fresh faces, full hearts, and so much love to give.</p></div><button v-if="isHome" class="text-link" @click="browse()">Meet all pets <PawIcon name="arrow_forward" /></button></div>
            <div class="catalog-toolbar"><div class="category-tabs" aria-label="Filter by pet type"><button v-for="category in categories" :key="category.label" :class="{ selected: species === category.label }" :aria-pressed="species === category.label" @click="species = category.label"><PawIcon :name="category.icon" />{{ category.label }}</button></div><label class="quick-search"><PawIcon name="search" /><input v-model="search" aria-label="Search pets" placeholder="Find a little friend…" /><button v-if="search" aria-label="Clear search" @click="search = ''"><PawIcon name="close" /></button></label></div>
            <div v-if="!isHome" class="filters-row"><q-select v-model="location" :options="locations" outlined dense label="Location" /><q-select v-model="gender" :options="['Any gender', 'Female', 'Male']" outlined dense label="Gender" /><q-select v-model="sort" :options="['Newest arrivals', 'Name: A–Z', 'Youngest first']" outlined dense label="Sort by" /><button v-if="filterActive" class="text-link" @click="clearFilters">Clear filters <PawIcon name="close" /></button><span class="results-count">{{ filteredPets.length }} {{ filteredPets.length === 1 ? 'friend' : 'friends' }} to meet</span></div>
            <div v-if="store.loading" class="pet-grid" aria-label="Loading pets"><div v-for="n in 4" :key="n" class="pet-skeleton"><div /><span /><span /></div></div>
            <div v-else-if="store.catalogError" class="empty-state"><PawIcon name="cloud_off" /><h3>Our friends are a little out of reach.</h3><p>The catalog could not load. Check that the backend is running, then try again.</p><button class="primary-button" @click="store.loadPets">Try again <PawIcon name="refresh" /></button></div>
            <div v-else-if="!filteredPets.length" class="empty-state"><PawIcon :name="page === 'saved' && !filterActive ? 'favorite' : 'search_off'" /><h3>{{ page === 'saved' && !filterActive ? 'Your heart has room for a new friend.' : 'No little faces found just yet.' }}</h3><p>{{ page === 'saved' && !filterActive ? 'Tap the heart on any pet to keep them here for later.' : 'Try another name, type, or location. Someone wonderful is waiting.' }}</p><button class="primary-button" @click="page === 'saved' && !filterActive ? browse() : clearFilters()">{{ page === 'saved' && !filterActive ? 'Meet the pets' : 'Reset filters' }}<PawIcon name="arrow_forward" /></button></div>
            <div v-else class="pet-grid"><PetCard v-for="pet in filteredPets" :key="pet.id" :pet="pet" :saved="store.favorites.includes(pet.id)" :saving="store.saving.includes(pet.id)" @open="openPet" @save="savePet" /></div>
            <div v-if="page === 'saved' && !store.user && store.favorites.length" class="gentle-note"><PawIcon name="bookmark_heart" /><span>Your favorites are saved in this browser. <button @click="openAuth('register')">Create an account</button> to keep them with you.</span></div>
          </section>
        </template>

        <section v-if="isHome || page === 'about'" class="how-section">
          <div class="section-heading"><div><div class="section-kicker"><span />A NEW BEGINNING, SIMPLIFIED</div><h2>Good things start with hello.</h2><p>A few small steps. A friendship that lasts a lifetime.</p></div><span class="handwritten">Let’s make a match <span>↙</span></span></div>
          <div class="steps-grid"><article v-for="step in steps" :key="step.number"><div class="step-top"><span class="step-icon"><PawIcon :name="step.icon" /></span><span class="step-number">{{ step.number }}</span></div><h3>{{ step.title }}</h3><p>{{ step.text }}</p></article></div>
        </section>

        <template v-if="page === 'about'">
          <section class="about-story"><div><span class="eyebrow">LITTLE PAWS. BIG POSSIBILITIES.</span><h1>Every pet deserves<br />their kind of person.</h1><p>PAWADOPT began with a simple idea: finding a companion should feel thoughtful, welcoming, and full of possibility.</p><p>We bring pet profiles, saved favorites, and adoption applications into one calm place. Whether your heart is set on a walking buddy or a quiet little companion, there’s a story here waiting to meet yours.</p><button class="primary-button" @click="browse()">Find your connection <PawIcon name="arrow_forward" /></button></div><img src="/images/biscuit.jpg" alt="A happy dog enjoying the outdoors" /></section>
          <div class="project-note"><PawIcon name="school" /><div><h3>A student project with a lot of heart.</h3><p>PAWADOPT is a fictional classroom application built with Vue 3, Quasar, and Laravel. Every pet profile, story, and fee is sample data. Photos are illustrative. Applications and messages are stored for demonstration; no real shelter is contacted.</p></div></div>
        </template>

        <template v-if="page === 'applications'">
          <div class="page-intro"><span class="eyebrow">THE START OF SOMETHING LOVELY</span><h1>Your adoption journey.</h1><p>Keep track of the little friendships you’ve taken a step toward.</p></div>
          <div v-if="!store.user" class="empty-state panel"><PawIcon name="assignment_ind" /><h3>Your next chapter starts here.</h3><p>Sign in to view your applications and continue your adoption journey.</p><button class="primary-button" @click="openAuth('login')">Sign in <PawIcon name="arrow_forward" /></button></div>
          <div v-else-if="applicationsBusy" class="empty-state"><q-spinner size="40px" color="primary" /><p>Gathering your applications…</p></div>
          <div v-else-if="applicationsError" class="empty-state"><p class="form-error">{{ applicationsError }}</p><button class="primary-button" @click="loadApplications">Try again</button></div>
          <div v-else-if="!applications.length" class="empty-state panel"><PawIcon name="pets" /><h3>Every friendship has a first step.</h3><p>Meet our pets and apply when you find a companion who feels right.</p><button class="primary-button" @click="browse()">Find a friend <PawIcon name="arrow_forward" /></button></div>
          <div v-else class="applications-list"><article v-for="application in applications" :key="application.id" class="application-card"><img :src="application.pet.image" :alt="application.pet.name" /><div><span class="section-kicker">{{ application.reference }}</span><h3>{{ application.pet.name }}</h3><p>{{ application.pet.breed }} · {{ application.pet.location }}</p><span class="application-date">Applied {{ new Date(application.created_at).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'Asia/Manila' }) }}</span></div><div class="application-status"><span><PawIcon name="check_circle" />Submitted</span><button class="text-link" @click="openPet(application.pet)">View pet <PawIcon name="arrow_forward" /></button></div></article><div class="gentle-note"><PawIcon name="info" /><span>These are demo applications. No shelter is contacted, and no review or email is sent.</span></div></div>
        </template>

        <template v-if="page === 'faq'">
          <div class="page-intro centered"><span class="eyebrow">A LITTLE HELP ALONG THE WAY</span><h1>Good questions.<br />Thoughtful answers.</h1><p>Everything you need to take that first little step.</p></div><section class="faq-list"><q-expansion-item v-for="(faq, index) in faqs" :key="faq.question" :label="faq.question" :default-opened="index === 0" expand-icon="sym_r_add" expanded-icon="sym_r_remove" group="faq"><p>{{ faq.answer }}</p></q-expansion-item></section><div class="faq-contact"><PawIcon name="chat_bubble" /><h3>Still have something on your mind?</h3><p>We’d love to hear it.</p><button class="primary-button" @click="go('/contact')">Let’s talk <PawIcon name="arrow_forward" /></button></div>
        </template>

        <template v-if="page === 'contact'">
          <div class="page-intro"><span class="eyebrow">WE’RE ALL EARS (AND PAWS)</span><h1>Let’s start a conversation.</h1><p>A question, an idea, or just a little hello. There’s room for it here.</p></div><div class="contact-layout"><div class="contact-copy"><PawIcon name="waving_hand" /><h2>Good connections<br />begin with a hello.</h2><p>Have a question about a pet or the adoption process? Leave a note and keep your message reference.</p><div class="gentle-note"><PawIcon name="info" /><span>This demo saves messages locally in the project database. It does not send emails or contact a live shelter.</span></div><button class="text-link" @click="go('/faq')">Explore common questions <PawIcon name="arrow_forward" /></button></div><div class="contact-form panel"><div v-if="contactReference" class="success-state"><PawIcon name="mark_email_read" /><h2>A little hello, received.</h2><p>Your message has been saved in this demo.</p><span class="reference-badge">{{ contactReference }}</span><button class="primary-button" @click="contactReference = ''">Write another note</button></div><q-form v-else @submit="submitContact"><h2>Send a little note</h2><div class="form-row"><q-input v-model="contactFields.name" outlined label="Your name" maxlength="100" :rules="[required]" /><q-input v-model="contactFields.email" outlined label="Email address" type="email" maxlength="255" :rules="[required, emailRule]" /></div><q-select v-model="contactFields.subject" outlined label="What’s on your mind?" :options="['Adoption question', 'Pet care question', 'Project feedback', 'Something else']" /><q-input v-model="contactFields.message" outlined label="Your message" type="textarea" maxlength="3000" counter :rules="[required, value => value.length >= 10 || 'Please write at least 10 characters']" /><p v-if="contactError" class="form-error" role="alert">{{ contactError }}</p><q-btn type="submit" class="form-submit" unelevated color="primary" text-color="dark" label="Send message" icon-right="sym_r_arrow_forward" :loading="contactBusy" /></q-form></div></div>
        </template>

        <div v-if="!['home', 'pets', 'saved', 'applications', 'about', 'faq', 'contact'].includes(page)" class="empty-state"><PawIcon name="explore" /><h1>A little off the beaten path.</h1><p>Let’s get you back to the pets.</p><button class="primary-button" @click="go('/')">Go home</button></div>

        <section v-if="isHome" class="closing-banner"><div class="closing-paw"><PawIcon name="pets" /></div><div><h2>Your everyday could use a little extraordinary.</h2><p>Sometimes, the best things in life have four paws.</p></div><button class="primary-button" @click="browse()">Find your friend <PawIcon name="arrow_forward" /></button></section>
        <footer class="footer"><div><span class="footer-brand"><PawIcon name="pets" />PAWADOPT <span class="release-version">v{{ appVersion }}</span></span><p>A fictional project. Real love for animals.</p></div><span class="footer-credit">Made with a little heart <PawIcon name="favorite" filled /></span><div class="backend-tools"><span :class="['connection-label', { online: store.connected }]"><span />{{ store.connected ? 'API connected' : 'API offline' }}</span><button @click="testBackend"><PawIcon name="terminal" />Test backend</button></div></footer>
      </main>
    </div>

    <q-dialog v-model="petDialog"><div v-if="selectedPet" class="pet-profile dialog-surface"><div class="profile-photo"><img :src="selectedPet.image" :alt="selectedPet.name" /><button class="close-dialog" aria-label="Close pet profile" @click="petDialog = false"><PawIcon name="close" /></button><span class="available-pill"><span />Ready for a home</span></div><div class="profile-content"><div class="profile-heading"><div><span class="section-kicker">YOUR NEW CHAPTER?</span><h2>Meet {{ selectedPet.name }}.</h2><p><PawIcon name="location_on" />{{ selectedPet.location }}</p></div><button class="profile-save" :class="{ saved: store.favorites.includes(selectedPet.id) }" :aria-pressed="store.favorites.includes(selectedPet.id)" :aria-label="`${store.favorites.includes(selectedPet.id) ? 'Unsave' : 'Save'} ${selectedPet.name}`" @click="savePet(selectedPet.id)"><PawIcon name="favorite" :filled="store.favorites.includes(selectedPet.id)" /></button></div><div class="pet-facts"><div><span>Age</span><strong>{{ ageLabel(selectedPet.age_months) }}</strong></div><div><span>Gender</span><strong>{{ selectedPet.gender }}</strong></div><div><span>Size</span><strong>{{ selectedPet.size }}</strong></div><div><span>Breed</span><strong>{{ selectedPet.breed }}</strong></div></div><h3>A little about me</h3><p class="pet-description">{{ selectedPet.description }}</p><div class="trait-list"><span v-for="trait in selectedPet.traits" :key="trait"><PawIcon name="check" />{{ trait }}</span></div><div class="adoption-fee"><span>Illustrative adoption fee</span><strong>{{ money(selectedPet.adoption_fee) }}</strong></div><button class="primary-button full-width" @click="startApplication(selectedPet)">Apply to adopt {{ selectedPet.name }}<PawIcon name="arrow_forward" /></button><p class="profile-disclaimer">Fictional pet profile. Photo is illustrative. No payment is collected.</p></div></div></q-dialog>

    <q-dialog v-model="authDialog" :persistent="authBusy" @hide="closeAuth"><div class="auth-dialog dialog-surface" aria-labelledby="auth-title" aria-describedby="auth-description" :aria-busy="authBusy"><button class="close-dialog" aria-label="Close sign in" :disabled="authBusy" @click="authDialog = false"><PawIcon name="close" /></button><span class="auth-paw"><PawIcon name="pets" /></span><span class="section-kicker">A HOME FOR EVERY PAW</span><h2 id="auth-title">{{ authMode === 'login' ? 'Hello again, friend.' : 'Your next chapter starts here.' }}</h2><p id="auth-description">{{ pendingAdoption ? `Sign in or create an account to apply for ${pendingAdoption.name}.` : authMode === 'login' ? 'A little closer to finding your forever friend.' : 'Save the ones you love. Take the first step toward adoption.' }}</p><div class="auth-tabs"><button type="button" :class="{ active: authMode === 'login' }" :aria-pressed="authMode === 'login'" :disabled="authBusy" @click="authMode = 'login'">Sign in</button><button type="button" :class="{ active: authMode === 'register' }" :aria-pressed="authMode === 'register'" :disabled="authBusy" @click="authMode = 'register'">Create account</button></div><q-form @submit="submitAuth"><q-input v-if="authMode === 'register'" v-model="authFields.name" outlined label="Your name" autocomplete="name" maxlength="100" :disable="authBusy" :rules="[required]" /><q-input v-model.trim="authFields.email" outlined label="Email address" type="email" inputmode="email" autocomplete="email" autocapitalize="off" autocorrect="off" spellcheck="false" maxlength="255" :disable="authBusy" :rules="[required, emailRule]" /><q-input v-model="authFields.password" outlined label="Password" :type="showPassword ? 'text' : 'password'" :autocomplete="authMode === 'login' ? 'current-password' : 'new-password'" :disable="authBusy" :rules="[required, value => authMode === 'login' || value.length >= 8 || 'Use at least 8 characters']"><template #append><button class="icon-button" type="button" :aria-label="showPassword ? 'Hide password' : 'Show password'" :aria-pressed="showPassword" :disabled="authBusy" @click="showPassword = !showPassword"><PawIcon :name="showPassword ? 'visibility_off' : 'visibility'" /></button></template></q-input><q-input v-if="authMode === 'register'" v-model="authFields.password_confirmation" outlined label="Confirm password" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" :disable="authBusy" :rules="[required, value => value === authFields.password || 'Passwords must match']" /><p v-if="authError" class="form-error" role="alert">{{ authError }}</p><q-btn class="form-submit" type="submit" unelevated color="primary" text-color="dark" :label="authMode === 'login' ? 'Sign in' : 'Create your account'" icon-right="sym_r_arrow_forward" :loading="authBusy" /></q-form><p class="auth-note">A fictional student project. Your account is saved in this project’s database.</p></div></q-dialog>

    <q-dialog v-model="applicationDialog" :persistent="applicationBusy"><div class="application-dialog dialog-surface"><button class="close-dialog" aria-label="Close application" :disabled="applicationBusy" @click="applicationDialog = false"><PawIcon name="close" /></button><div v-if="successReference" class="success-state"><span class="success-icon"><PawIcon name="check_circle" /></span><span class="section-kicker">ONE LOVELY STEP FORWARD</span><h2>Your story has a new beginning.</h2><p>Your application for {{ selectedPet?.name }} is saved. Keep this reference for your records.</p><span class="reference-badge">{{ successReference }}</span><button class="primary-button" @click="applicationDialog = false; go('/applications')">View my applications <PawIcon name="arrow_forward" /></button><p class="profile-disclaimer">This is a demo application. No shelter is contacted.</p></div><template v-else><span class="section-kicker">A LITTLE ABOUT YOUR WORLD</span><h2>A home for {{ selectedPet?.name }}.</h2><p>Tell us about the life you could share together.</p><div class="application-pet"><img :src="selectedPet?.image" :alt="selectedPet?.name" /><div><strong>{{ selectedPet?.name }}</strong><span>{{ selectedPet?.breed }} · {{ selectedPet?.location }}</span></div><PawIcon name="favorite" /></div><q-form @submit="submitApplication"><div class="form-row"><q-input v-model="applicationFields.phone" outlined label="Phone number" type="tel" maxlength="30" :rules="[required, value => /^[+0-9()\s-]{7,30}$/.test(value) || 'Enter a valid phone number']" /><q-input v-model="applicationFields.city" outlined label="City / municipality" maxlength="120" :rules="[required]" /></div><div class="form-row"><q-select v-model="applicationFields.housing" outlined label="Your home" :options="['House', 'Apartment', 'Condominium', 'Other']" /><q-select v-model="applicationFields.experience" outlined label="Pet experience" :options="['First-time adopter', 'Have pets now', 'Had pets before']" /></div><q-input v-model="applicationFields.message" outlined type="textarea" label="What would a happy home look like?" hint="Tell us about your routine, your space, and why this pet feels right." maxlength="2000" counter :rules="[required, value => value.trim().length >= 20 || 'Please tell us a little more (at least 20 characters)']" /><q-checkbox v-model="applicationConsent" color="primary" label="I understand this is a demo application and that caring for a pet is a long-term commitment." /><p v-if="applicationError" class="form-error" role="alert">{{ applicationError }}</p><q-btn class="form-submit" type="submit" unelevated color="primary" text-color="dark" label="Submit application" icon-right="sym_r_arrow_forward" :loading="applicationBusy" :disable="!applicationConsent" /></q-form></template></div></q-dialog>

    <q-dialog v-model="backendDialog"><div class="backend-dialog dialog-surface"><button class="close-dialog" aria-label="Close backend check" @click="backendDialog = false"><PawIcon name="close" /></button><PawIcon name="terminal" class="backend-icon" /><h2>Behind the paws</h2><p>Checking the Laravel API and database.</p><q-spinner v-if="backendBusy" color="primary" size="35px" /><div v-else class="backend-result" :class="{ failed: backendError }"><PawIcon :name="backendError ? 'error' : 'check_circle'" /><span>{{ backendMessage }}</span></div><code>GET /api/hello</code><button class="text-link" :disabled="backendBusy" @click="testBackend">Check again <PawIcon name="refresh" /></button></div></q-dialog>
  </div>
</template>

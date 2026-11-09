<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import PawIcon from './PawIcon.vue'
import { getApiUrl, normalizeApiUrl, saveApiUrl } from '../lib/api'

const open = ref(false)
const address = ref(getApiUrl())
const checking = ref(false)
const message = ref('')
const success = ref(false)

function validate(value: string): true | string {
  try { normalizeApiUrl(value); return true }
  catch { return 'Enter an HTTP or HTTPS address, for example http://192.168.1.5:8000/api' }
}

async function checkConnection() {
  message.value = ''; success.value = false
  if (validate(address.value) !== true) { message.value = String(validate(address.value)); return }
  checking.value = true
  try {
    const { data } = await axios.get(`${normalizeApiUrl(address.value)}/hello`, { timeout: 10000, headers: { Accept: 'application/json' } })
    if (data.database !== 'connected') throw new Error('Unexpected server response')
    success.value = true; message.value = 'Connected. The server and database are ready.'
  } catch { message.value = 'Cannot connect. Check the server address, keep Laravel running, and connect both devices to the same Wi-Fi.' }
  finally { checking.value = false }
}

function save() {
  saveApiUrl(address.value)
  window.location.reload()
}
</script>

<template>
  <button class="nav-item secondary connection-settings" @click="open = true; message = ''"><PawIcon name="settings_ethernet" />Server connection</button>
  <q-dialog v-model="open">
    <div class="backend-dialog dialog-surface connection-dialog">
      <button class="close-dialog" aria-label="Close server connection" @click="open = false"><PawIcon name="close" /></button>
      <PawIcon name="settings_ethernet" class="backend-icon" />
      <h2>Connect your PAWADOPT.</h2>
      <p>Use your computer’s Wi-Fi address while Laravel is running. Your phone and computer need to be on the same network.</p>
      <q-form @submit="save">
        <q-input v-model="address" outlined label="API server address" type="url" :rules="[validate]" autocomplete="off" autocapitalize="off" spellcheck="false" />
        <button class="text-link" type="button" @click="address = 'http://10.0.2.2:8000/api'; message = ''">Use Android emulator address</button>
        <div v-if="message" :class="['backend-result', { failed: !success }]" role="status"><PawIcon :name="success ? 'check_circle' : 'info'" /><span>{{ message }}</span></div>
        <div class="connection-actions"><q-btn outline no-caps color="primary" label="Test connection" :loading="checking" @click="checkConnection" /><q-btn unelevated no-caps color="primary" text-color="dark" label="Save & reconnect" type="submit" :disable="checking" /></div>
      </q-form>
      <p class="profile-disclaimer">Changing servers signs you out. Your saved account data stays on its original server.</p>
    </div>
  </q-dialog>
</template>

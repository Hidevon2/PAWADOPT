<script setup lang="ts">
defineProps<{ name: string; filled?: boolean }>()
</script>
<template>
  <svg v-if="name === 'cat'" class="paw-icon cat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 10 4 3l6 4a13 13 0 0 1 4 0l6-4-1 7c4 7-1 11-7 11S1 17 5 10Z" /><path d="M8 12h.01M16 12h.01M10 16l2 1 2-1M3 14l4 1m-4 3 4-1m14-3-4 1m4 3-4-1" /></svg>
  <span v-else class="material-symbols-rounded paw-icon" :class="{ filled }" aria-hidden="true">{{ name }}</span>
</template>

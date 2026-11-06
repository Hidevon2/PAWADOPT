<script setup lang="ts">
import { ref } from 'vue'
import PawIcon from './PawIcon.vue'
import { ageLabel, type Pet } from '../lib/api'
defineProps<{ pet: Pet; saved: boolean; saving: boolean }>()
defineEmits<{ open: [pet: Pet]; save: [id: number] }>()
const imageFailed = ref(false)
</script>

<template>
  <article class="pet-card">
    <div class="pet-image-wrap">
      <button class="pet-image-button" :aria-label="`Meet ${pet.name}`" @click="$emit('open', pet)">
        <img v-if="!imageFailed" :src="pet.image" :alt="`${pet.name}, ${pet.breed}`" loading="lazy" @error="imageFailed = true" />
        <PawIcon v-else name="pets" class="image-fallback" />
      </button>
      <span class="available-pill"><span />{{ pet.status === 'available' ? 'Ready for a home' : 'Adopted' }}</span>
      <button class="save-button" :class="{ saved }" :disabled="saving" :aria-label="`${saved ? 'Unsave' : 'Save'} ${pet.name}`" :aria-pressed="saved" @click="$emit('save', pet.id)"><PawIcon name="favorite" :filled="saved" /></button>
    </div>
    <div class="pet-card-body">
      <div class="pet-title-row"><button class="pet-name" @click="$emit('open', pet)">{{ pet.name }}</button><span class="gender" :aria-label="pet.gender"><PawIcon :name="pet.gender === 'Male' ? 'male' : 'female'" /></span></div>
      <p class="pet-meta">{{ pet.breed }} <span>·</span> {{ ageLabel(pet.age_months) }}</p>
      <div class="pet-card-bottom"><span><PawIcon name="location_on" />{{ pet.location }}</span><button :aria-label="`View ${pet.name}'s profile`" @click="$emit('open', pet)"><PawIcon name="arrow_outward" /></button></div>
    </div>
  </article>
</template>

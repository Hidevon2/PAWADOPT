import { defineRouter } from '#q-app/wrappers'
import { createRouter, createWebHashHistory } from 'vue-router'

export default defineRouter(() => createRouter({
  history: createWebHashHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: () => import('../pages/PawAdopt.vue') }],
  scrollBehavior: () => ({ top: 0 }),
}))

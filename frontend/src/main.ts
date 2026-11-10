import '@fontsource/ibm-plex-sans/400.css'
import '@fontsource/ibm-plex-sans/500.css'
import '@fontsource/ibm-plex-sans/600.css'
import '@fontsource/ibm-plex-mono/400.css'
import '@fontsource/ibm-plex-mono/500.css'
import './assets/styles/main.css'

import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { useToast } from './composables/useToast'
import { onApiError } from './services/http'
import { useAuthStore } from './stores/auth'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Central reactions to API failures that are not specific to one screen.
const toast = useToast()
onApiError((error, request) => {
  const auth = useAuthStore(pinia)

  if (error.status === 401) {
    // Session restore and login handle their own 401s.
    if (request.path === '/auth/me' || request.path === '/auth/login') return

    if (auth.isAuthenticated) {
      auth.clear()
      toast.info(error.message)
      const current = router.currentRoute.value
      void router.replace({ name: 'login', query: { redirect: current.fullPath } })
    }
    return
  }

  // Validation errors are shown next to the fields by each form.
  if (error.status === 422) return

  if (error.status === 0 || error.status === 419 || error.status === 429 || error.status >= 500) {
    toast.error(error.message)
  }
})

app.mount('#app')

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import { homeFor } from '@/router/guards'
import { isApiError, type ApiError } from '@/services/http'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const form = reactive({ email: '', password: '', remember: false })
const submitting = ref(false)
const error = ref<ApiError | null>(null)

const generalError = computed(() => {
  if (!error.value) return null
  // Credential errors come back as a field error on "email"; show them as a form-level message.
  return error.value.field('email') ?? (error.value.isValidation ? null : error.value.message)
})

async function submit(): Promise<void> {
  if (submitting.value) return
  submitting.value = true
  error.value = null

  try {
    await auth.login({ ...form })
    const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : null
    await router.replace(redirect ?? homeFor(auth))
  } catch (caught) {
    if (!isApiError(caught)) throw caught
    error.value = caught
    form.password = ''
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="flex min-h-dvh items-center justify-center px-4 py-10">
    <div class="w-full max-w-[400px]">
      <div class="mb-8 flex items-center gap-3">
        <span
          class="flex size-9 items-center justify-center rounded-input bg-primary font-mono text-label font-semibold text-primary-text"
          aria-hidden="true"
        >
          AP
        </span>
        <span class="text-card-title font-semibold">AgendaPro</span>
      </div>

      <h1 class="text-page-title">Entrar</h1>
      <p class="mt-1 text-text-secondary">Acesse a agenda e a operação da sua empresa.</p>

      <form class="mt-8 flex flex-col gap-5" novalidate @submit.prevent="submit">
        <div
          v-if="generalError"
          class="rounded-input border border-danger/40 bg-danger/10 px-3 py-2.5 text-body text-danger"
          role="alert"
        >
          {{ generalError }}
        </div>

        <AppInput
          v-model="form.email"
          label="E-mail"
          type="email"
          autocomplete="username"
          placeholder="voce@empresa.com.br"
          required
        />
        <AppInput
          v-model="form.password"
          label="Senha"
          type="password"
          autocomplete="current-password"
          required
          :error="error?.field('password')"
        />

        <label class="flex cursor-pointer items-center gap-2.5 text-body text-text-secondary select-none">
          <input
            v-model="form.remember"
            type="checkbox"
            class="size-4 rounded-control border-border bg-surface accent-primary"
          />
          Manter conectado neste dispositivo
        </label>

        <AppButton type="submit" block :loading="submitting">Entrar</AppButton>
      </form>
    </div>
  </main>
</template>

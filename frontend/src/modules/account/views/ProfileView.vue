<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppInput from '@/components/ui/AppInput.vue'
import { useToast } from '@/composables/useToast'
import SettingsTabs from '@/modules/settings/components/SettingsTabs.vue'
import { accountApi } from '@/modules/settings/api'
import { isApiError, type ApiError } from '@/services/http'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const toast = useToast()

// --- Profile ----------------------------------------------------------------------------
const profile = reactive({ name: '', email: '', password: '' })
const profileError = ref<ApiError | null>(null)
const savingProfile = ref(false)

function resetProfile(): void {
  profile.name = auth.user?.name ?? ''
  profile.email = auth.user?.email ?? ''
  profile.password = ''
}
watch(() => auth.user, resetProfile, { immediate: true })

// The e-mail is the login, so the API asks for the password to change it.
const emailChanged = computed(() => profile.email.trim().toLowerCase() !== (auth.user?.email ?? ''))

const profileDirty = computed(() => profile.name !== auth.user?.name || profile.email !== auth.user?.email)

async function saveProfile(): Promise<void> {
  savingProfile.value = true
  profileError.value = null
  try {
    auth.user = await accountApi.updateProfile({
      name: profile.name.trim(),
      email: profile.email.trim(),
      ...(emailChanged.value ? { current_password: profile.password } : {}),
    })
    toast.success('Perfil atualizado.')
  } catch (caught) {
    if (!isApiError(caught)) throw caught
    profileError.value = caught
  } finally {
    savingProfile.value = false
  }
}

// --- Password ---------------------------------------------------------------------------
const password = reactive({ current: '', next: '', confirmation: '' })
const passwordError = ref<ApiError | null>(null)
const savingPassword = ref(false)

async function savePassword(): Promise<void> {
  savingPassword.value = true
  passwordError.value = null
  try {
    await accountApi.updatePassword({
      current_password: password.current,
      password: password.next,
      password_confirmation: password.confirmation,
    })
    Object.assign(password, { current: '', next: '', confirmation: '' })
    toast.success('Senha alterada. As outras sessões abertas foram encerradas.')
  } catch (caught) {
    if (!isApiError(caught)) throw caught
    passwordError.value = caught
  } finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <header>
      <p class="text-label text-text-muted">Configurações</p>
      <h1 class="mt-1 text-page-title">Meu perfil</h1>
    </header>
    <SettingsTabs />

    <div class="grid max-w-5xl gap-6 lg:grid-cols-2">
      <AppCard title="Dados pessoais" description="Seu nome aparece no histórico dos agendamentos.">
        <form class="flex flex-col gap-4" novalidate @submit.prevent="saveProfile">
          <p v-if="profileError && !profileError.isValidation" class="text-label text-danger" role="alert">
            {{ profileError.message }}
          </p>
          <AppInput
            v-model="profile.name"
            label="Nome"
            required
            autocomplete="name"
            :error="profileError?.field('name')"
          />
          <AppInput
            v-model="profile.email"
            label="E-mail de acesso"
            type="email"
            required
            autocomplete="email"
            :error="profileError?.field('email')"
          />
          <AppInput
            v-if="emailChanged"
            v-model="profile.password"
            label="Senha atual"
            type="password"
            required
            autocomplete="current-password"
            hint="Necessária para alterar o e-mail de acesso."
            :error="profileError?.field('current_password')"
          />
          <div class="flex justify-end gap-2 border-t border-border pt-4">
            <AppButton variant="ghost" :disabled="!profileDirty || savingProfile" @click="resetProfile"
              >Descartar</AppButton
            >
            <AppButton type="submit" :loading="savingProfile" :disabled="!profileDirty">Salvar perfil</AppButton>
          </div>
        </form>
      </AppCard>

      <AppCard
        title="Segurança"
        description="Ao trocar a senha, as sessões abertas em outros dispositivos são encerradas."
      >
        <form class="flex flex-col gap-4" novalidate @submit.prevent="savePassword">
          <p v-if="passwordError && !passwordError.isValidation" class="text-label text-danger" role="alert">
            {{ passwordError.message }}
          </p>
          <AppInput
            v-model="password.current"
            label="Senha atual"
            type="password"
            required
            autocomplete="current-password"
            :error="passwordError?.field('current_password')"
          />
          <AppInput
            v-model="password.next"
            label="Nova senha"
            type="password"
            required
            autocomplete="new-password"
            hint="Mínimo de 8 caracteres, com letras e números."
            :error="passwordError?.field('password')"
          />
          <AppInput
            v-model="password.confirmation"
            label="Confirmar nova senha"
            type="password"
            required
            autocomplete="new-password"
          />
          <div class="flex justify-end border-t border-border pt-4">
            <AppButton type="submit" :loading="savingPassword" :disabled="!password.current || !password.next"
              >Alterar senha</AppButton
            >
          </div>
        </form>
      </AppCard>
    </div>
  </div>
</template>

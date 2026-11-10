<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const loggingOut = ref(false)

async function logout(): Promise<void> {
  loggingOut.value = true
  try {
    await auth.logout()
  } finally {
    loggingOut.value = false
    await router.replace({ name: 'login' })
  }
}
</script>

<template>
  <main class="flex min-h-dvh items-start justify-center px-4 py-16 sm:items-center">
    <div class="w-full max-w-[520px]">
      <p class="text-label text-text-muted">{{ auth.user?.name }}</p>
      <h1 class="mt-1 text-page-title">Selecione a empresa</h1>
      <p class="mt-1 text-text-secondary">Você pode trocar de empresa a qualquer momento pelo cabeçalho.</p>

      <ul v-if="auth.companies.length" class="mt-8 flex flex-col gap-2">
        <li v-for="company in auth.companies" :key="company.id">
          <RouterLink
            :to="{ name: 'company-home', params: { companyId: company.id } }"
            class="flex items-center gap-4 rounded-card border border-border bg-card px-4 py-3.5 transition-colors duration-150 hover:border-text-muted hover:bg-elevated"
          >
            <span
              class="flex size-9 shrink-0 items-center justify-center rounded-input bg-primary-soft text-label font-semibold text-primary-hover"
              aria-hidden="true"
            >
              {{ company.name.slice(0, 1).toUpperCase() }}
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-card-title font-medium">{{ company.name }}</span>
              <span class="block font-mono text-support text-text-muted">{{ company.timezone }}</span>
            </span>
            <AppBadge v-if="company.membership" tone="neutral">{{ company.membership.role_label }}</AppBadge>
          </RouterLink>
        </li>
      </ul>

      <div v-else class="mt-8 rounded-card border border-dashed border-border px-6 py-10 text-center">
        <p class="text-card-title font-medium">Nenhuma empresa vinculada</p>
        <p class="mt-1 text-text-secondary">
          Peça a um administrador da sua empresa para adicionar o seu acesso.
        </p>
      </div>

      <div class="mt-8 flex justify-end">
        <AppButton variant="ghost" size="sm" :loading="loggingOut" @click="logout">Sair</AppButton>
      </div>
    </div>
  </main>
</template>

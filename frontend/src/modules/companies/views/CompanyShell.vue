<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import { useCurrentCompany } from '@/composables/useCurrentCompany'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const { company } = useCurrentCompany()
const loggingOut = ref(false)

const companyOptions = computed(() => auth.companies.map((c) => ({ value: c.id, label: c.name })))

const selectedCompany = computed({
  get: () => company.value?.id ?? '',
  set: (id: string) => {
    if (id && id !== company.value?.id) void router.push({ name: 'company-home', params: { companyId: id } })
  },
})

const initials = computed(() =>
  (auth.user?.name ?? '')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join(''),
)

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
  <AppLayout>
    <template #header-actions>
      <div v-if="companyOptions.length > 1" class="hidden w-56 sm:block">
        <AppSelect v-model="selectedCompany" label="Empresa" hide-label :options="companyOptions" />
      </div>
      <span v-else-if="company" class="hidden truncate text-label text-text-secondary sm:block">{{
        company.name
      }}</span>

      <div class="flex items-center gap-2 border-l border-border pl-3">
        <RouterLink
          :to="{ name: 'profile', params: { companyId: company?.id } }"
          class="flex items-center gap-2 rounded-input p-1 pr-2 transition-colors duration-150 hover:bg-elevated"
          title="Meu perfil"
        >
          <span class="sr-only md:hidden">Meu perfil</span>
          <span
            class="flex size-8 items-center justify-center rounded-full bg-elevated text-support font-semibold text-text-secondary"
            aria-hidden="true"
          >
            {{ initials }}
          </span>
          <span class="hidden min-w-0 leading-tight md:block">
            <span class="block truncate text-label font-medium">{{ auth.user?.name }}</span>
            <span class="block truncate text-support text-text-muted">{{ company?.membership?.role_label }}</span>
          </span>
        </RouterLink>
        <button
          type="button"
          class="ml-1 inline-flex h-8 items-center rounded-input px-2.5 text-label font-medium text-text-secondary transition-colors duration-150 hover:bg-elevated hover:text-text-primary disabled:opacity-50"
          :disabled="loggingOut"
          @click="logout"
        >
          {{ loggingOut ? 'Saindo…' : 'Sair' }}
        </button>
      </div>
    </template>

    <!-- Keyed by company: switching companies remounts the page so no data from the previous one survives. -->
    <RouterView v-if="company" :key="company.id" />
  </AppLayout>
</template>

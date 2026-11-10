<script setup lang="ts">
import { computed } from 'vue'
import { useCurrentCompany } from '@/composables/useCurrentCompany'

/** Section navigation shared by the settings pages. Team is listed only for roles that manage it. */
const { companyId, can } = useCurrentCompany()

const tabs = computed(() => [
  { name: 'company-settings', label: 'Empresa' },
  { name: 'business-hours', label: 'Expediente' },
  ...(can('manage_members') ? [{ name: 'team', label: 'Equipe e permissões' }] : []),
  { name: 'profile', label: 'Meu perfil' },
])
</script>

<template>
  <nav aria-label="Seções de configurações" class="-mx-1 overflow-x-auto overflow-y-hidden border-b border-border">
    <ul class="flex min-w-max gap-1 px-1">
      <li v-for="tab in tabs" :key="tab.name">
        <RouterLink v-slot="{ href, navigate, isExactActive }" :to="{ name: tab.name, params: { companyId } }" custom>
          <a
            :href="href"
            :aria-current="isExactActive ? 'page' : undefined"
            class="relative inline-flex h-10 items-center px-3 text-label font-medium transition-colors duration-150"
            :class="isExactActive ? 'text-text-primary' : 'text-text-secondary hover:text-text-primary'"
            @click="navigate"
          >
            {{ tab.label }}
            <span
              v-if="isExactActive"
              class="absolute inset-x-2 -bottom-px h-0.5 rounded-full bg-primary-hover"
              aria-hidden="true"
            />
          </a>
        </RouterLink>
      </li>
    </ul>
  </nav>
</template>

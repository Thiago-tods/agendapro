<script setup lang="ts">
import { computed } from 'vue'
import { useCurrentCompany } from '@/composables/useCurrentCompany'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const { company } = useCurrentCompany()

const firstName = computed(() => auth.user?.name.split(' ')[0] ?? '')
</script>

<template>
  <div class="flex flex-col gap-8">
    <header>
      <p class="text-label text-text-muted">{{ company?.name }}</p>
      <h1 class="mt-1 text-page-title">Olá, {{ firstName }}</h1>
    </header>

    <!-- Placeholder until the dashboard stage; intentionally no fake metrics. -->
    <section
      class="rounded-card border border-dashed border-border px-6 py-12 text-center"
      aria-labelledby="home-placeholder"
    >
      <h2 id="home-placeholder" class="text-card-title font-medium">O painel ainda não está disponível</h2>
      <p class="mx-auto mt-1 max-w-md text-text-secondary">
        Indicadores e próximos atendimentos aparecerão aqui. Enquanto isso, você pode revisar os dados da empresa em
        Configurações.
      </p>
    </section>
  </div>
</template>

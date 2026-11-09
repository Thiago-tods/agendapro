<script setup lang="ts">
// Temporary showcase of the visual foundation. Not a product screen; remove once real modules exist.
import { ref } from 'vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppCard from '@/components/ui/AppCard.vue'

const colorTokens = [
  { name: 'background', value: '#12111A' },
  { name: 'surface', value: '#1B1925' },
  { name: 'card', value: '#211E2D' },
  { name: 'elevated', value: '#292538' },
  { name: 'border', value: '#353044' },
  { name: 'primary', value: '#8B5CF6' },
  { name: 'primary-hover', value: '#A78BFA' },
  { name: 'primary-soft', value: '#34254D' },
  { name: 'success', value: '#6EE7B7' },
  { name: 'warning', value: '#FCD34D' },
  { name: 'danger', value: '#FDA4AF' },
  { name: 'info', value: '#7DD3FC' },
]

const typeScale = [
  { label: 'Título de página', cls: 'text-page-title font-semibold', size: '28px' },
  { label: 'Título de seção', cls: 'text-section-title font-semibold', size: '20px' },
  { label: 'Título de card', cls: 'text-card-title font-semibold', size: '16px' },
  { label: 'Texto de corpo', cls: 'text-body', size: '14px' },
  { label: 'Rótulo de campo', cls: 'text-label font-medium', size: '13px' },
  { label: 'Texto de apoio', cls: 'text-support text-text-secondary', size: '12px' },
]

const name = ref('')
const email = ref('nome@empresa')
const phone = ref('(11) 98765-4321')
const search = ref('')

const loading = ref(false)
function simulateLoading(): void {
  loading.value = true
  window.setTimeout(() => (loading.value = false), 1600)
}
</script>

<template>
  <div class="flex flex-col gap-10">
    <header class="flex flex-col gap-1">
      <p class="font-mono text-support tracking-wide text-text-muted uppercase">Fundação visual</p>
      <h1 class="text-page-title">Componentes e tokens</h1>
      <p class="max-w-2xl text-text-secondary">
        Página temporária de referência. Mostra os tokens oficiais e os estados dos componentes
        compartilhados usados nas próximas telas.
      </p>
    </header>

    <!-- Tokens -->
    <section aria-labelledby="ds-cores" class="flex flex-col gap-4">
      <h2 id="ds-cores" class="text-section-title">Cores</h2>
      <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <li
          v-for="token in colorTokens"
          :key="token.name"
          class="overflow-hidden rounded-card border border-border bg-card"
        >
          <div class="h-12 border-b border-border" :style="{ backgroundColor: token.value }" />
          <div class="px-3 py-2">
            <p class="text-label font-medium">{{ token.name }}</p>
            <p class="font-mono text-support text-text-muted">{{ token.value }}</p>
          </div>
        </li>
      </ul>
    </section>

    <section aria-labelledby="ds-tipografia" class="grid gap-6 lg:grid-cols-[1fr_320px]">
      <AppCard title="Tipografia" description="IBM Plex Sans para interface, IBM Plex Mono para dados técnicos.">
        <h2 id="ds-tipografia" class="sr-only">Tipografia</h2>
        <dl class="divide-y divide-border">
          <div
            v-for="item in typeScale"
            :key="item.label"
            class="flex items-baseline justify-between gap-4 py-3 first:pt-0 last:pb-0"
          >
            <dt :class="item.cls" class="truncate">{{ item.label }}</dt>
            <dd class="shrink-0 font-mono text-support text-text-muted">{{ item.size }}</dd>
          </div>
        </dl>
      </AppCard>

      <AppCard title="Raios e espaçamento" padding="md">
        <dl class="flex flex-col gap-3 text-label">
          <div class="flex items-center gap-3">
            <span class="size-8 rounded-control border border-primary/60 bg-primary-soft" />
            <dt class="text-text-secondary">Controles</dt>
            <dd class="ml-auto font-mono text-text-muted">4px</dd>
          </div>
          <div class="flex items-center gap-3">
            <span class="size-8 rounded-input border border-primary/60 bg-primary-soft" />
            <dt class="text-text-secondary">Inputs e botões</dt>
            <dd class="ml-auto font-mono text-text-muted">5px</dd>
          </div>
          <div class="flex items-center gap-3">
            <span class="size-8 rounded-card border border-primary/60 bg-primary-soft" />
            <dt class="text-text-secondary">Cards</dt>
            <dd class="ml-auto font-mono text-text-muted">6px</dd>
          </div>
          <div class="flex items-center gap-3">
            <span class="size-8 rounded-modal border border-primary/60 bg-primary-soft" />
            <dt class="text-text-secondary">Modais</dt>
            <dd class="ml-auto font-mono text-text-muted">8px</dd>
          </div>
        </dl>
        <template #footer>
          <p class="font-mono text-support text-text-muted">Base 4px · 4 8 12 16 24 32 40 48</p>
        </template>
      </AppCard>
    </section>

    <!-- Buttons -->
    <section aria-labelledby="ds-botoes" class="flex flex-col gap-4">
      <h2 id="ds-botoes" class="text-section-title">Botões</h2>
      <AppCard padding="lg">
        <div class="flex flex-col gap-6">
          <div class="flex flex-wrap items-center gap-3">
            <AppButton>Novo agendamento</AppButton>
            <AppButton variant="secondary">Exportar</AppButton>
            <AppButton variant="ghost">Cancelar</AppButton>
            <AppButton variant="danger">Excluir</AppButton>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <AppButton size="sm">Pequeno</AppButton>
            <AppButton size="sm" variant="secondary">Filtrar</AppButton>
            <AppButton variant="secondary">
              <template #icon>
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                  <path d="M12 5v14M5 12h14" />
                </svg>
              </template>
              Com ícone
            </AppButton>
          </div>
          <div class="flex flex-wrap items-center gap-3 border-t border-border pt-6">
            <AppButton :loading="loading" @click="simulateLoading">
              {{ loading ? 'Salvando' : 'Simular carregamento' }}
            </AppButton>
            <AppButton loading variant="secondary">Carregando</AppButton>
            <AppButton disabled>Desabilitado</AppButton>
            <AppButton disabled variant="secondary">Desabilitado</AppButton>
          </div>
          <p class="text-support text-text-muted">
            Use Tab para conferir o indicador de foco. Hover e active são aplicados em todas as variantes.
          </p>
        </div>
      </AppCard>
    </section>

    <!-- Inputs -->
    <section aria-labelledby="ds-campos" class="flex flex-col gap-4">
      <h2 id="ds-campos" class="text-section-title">Campos</h2>
      <AppCard padding="lg">
        <form class="grid gap-5 sm:grid-cols-2" novalidate @submit.prevent>
          <AppInput
            v-model="name"
            label="Nome do cliente"
            placeholder="Ex.: Maria Oliveira"
            hint="Como aparecerá na agenda."
            required
            autocomplete="name"
          />
          <AppInput
            v-model="email"
            label="E-mail"
            type="email"
            error="Informe um e-mail válido."
            autocomplete="email"
          />
          <AppInput
            v-model="phone"
            label="Telefone"
            type="tel"
            success="Número verificado."
            autocomplete="tel"
          />
          <AppInput label="Código interno" model-value="CLI-00231" disabled hint="Gerado automaticamente." />
          <div class="sm:col-span-2">
            <AppInput v-model="search" label="Buscar" type="search" placeholder="Buscar por nome ou telefone">
              <template #leading>
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                  <circle cx="11" cy="11" r="7" />
                  <path d="m20 20-3.5-3.5" />
                </svg>
              </template>
            </AppInput>
          </div>
        </form>
      </AppCard>
    </section>

    <!-- Badges & cards -->
    <section aria-labelledby="ds-badges" class="grid gap-6 lg:grid-cols-2">
      <AppCard title="Badges de status" description="A cor sempre acompanha texto; nunca é o único indicador.">
        <h2 id="ds-badges" class="sr-only">Badges</h2>
        <div class="flex flex-wrap gap-2">
          <AppBadge tone="info" dot>Agendado</AppBadge>
          <AppBadge tone="primary" dot>Confirmado</AppBadge>
          <AppBadge tone="warning" dot>Pendente</AppBadge>
          <AppBadge tone="success" dot>Concluído</AppBadge>
          <AppBadge tone="danger" dot>Cancelado</AppBadge>
          <AppBadge tone="neutral" dot>Não compareceu</AppBadge>
        </div>
        <div class="mt-4 flex flex-wrap gap-2 border-t border-border pt-4">
          <AppBadge>Neutro</AppBadge>
          <AppBadge tone="primary">Novo</AppBadge>
          <AppBadge tone="success">Ativo</AppBadge>
          <AppBadge tone="neutral">Inativo</AppBadge>
        </div>
      </AppCard>

      <AppCard title="Card com ações" description="Cabeçalho, conteúdo e rodapé opcionais.">
        <template #actions>
          <AppButton size="sm" variant="ghost">Ver tudo</AppButton>
        </template>
        <p class="text-text-secondary">
          Cards usam borda sutil em vez de sombra. A composição interna muda conforme a função:
          listas, indicadores e formulários não seguem o mesmo molde.
        </p>
        <template #footer>
          <div class="flex items-center justify-between gap-3">
            <span class="text-support text-text-muted">Rodapé para metadados</span>
            <AppButton size="sm" variant="secondary">Ação</AppButton>
          </div>
        </template>
      </AppCard>
    </section>
  </div>
</template>

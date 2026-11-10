<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppEmptyState from '@/components/ui/AppEmptyState.vue'
import AppErrorState from '@/components/ui/AppErrorState.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import AppSkeleton from '@/components/ui/AppSkeleton.vue'
import { useCurrentCompany } from '@/composables/useCurrentCompany'
import { usePagedList } from '@/composables/usePagedList'
import { professionalsApi, type ProfessionalQuery } from '@/modules/professionals/api'
import ProfessionalFormModal from '@/modules/professionals/components/ProfessionalFormModal.vue'
import type { Professional } from '@/types/api'

const router = useRouter()
const { companyId, can } = useCurrentCompany()

const search = ref('')
const status = ref<NonNullable<ProfessionalQuery['status']>>('active')
const formOpen = ref(false)

const list = usePagedList(
  (page, signal) =>
    professionalsApi.list(
      companyId.value!,
      {
        search: search.value.trim() || undefined,
        status: status.value,
        page,
        per_page: 24,
      },
      signal,
    ),
  [search, status],
  { debounceMs: 300 },
)

const statusOptions = [
  { value: 'active', label: 'Ativos' },
  { value: 'inactive', label: 'Inativos' },
  { value: 'all', label: 'Todos' },
]
const filtered = computed(() => search.value.trim() !== '' || status.value !== 'active')

function onSaved(professional: Professional): void {
  void router.push({
    name: 'professional-detail',
    params: { companyId: companyId.value, professionalId: professional.id },
  })
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-page-title">Profissionais</h1>
        <p class="mt-1 text-body text-text-secondary">Equipe que atende, serviços realizados e horários de trabalho.</p>
      </div>
      <AppButton v-if="can('manage_catalog')" @click="formOpen = true">Novo profissional</AppButton>
    </header>

    <div class="flex flex-col gap-3 sm:flex-row">
      <div class="flex-1">
        <AppInput
          v-model="search"
          type="search"
          label="Buscar profissionais"
          hide-label
          placeholder="Buscar por nome"
        />
      </div>
      <div class="sm:w-44">
        <AppSelect v-model="status" label="Situação" hide-label :options="statusOptions" />
      </div>
    </div>

    <AppCard v-if="list.error.value" padding="none">
      <AppErrorState :message="list.error.value" @retry="list.reload" />
    </AppCard>

    <div
      v-else-if="list.loading.value && list.items.value.length === 0"
      class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
      aria-busy="true"
    >
      <AppCard v-for="n in 3" :key="n"><AppSkeleton :lines="3" /></AppCard>
    </div>

    <AppCard v-else-if="list.items.value.length === 0" padding="none">
      <AppEmptyState
        :title="filtered ? 'Nenhum profissional encontrado' : 'Nenhum profissional cadastrado'"
        :description="
          filtered ? 'Ajuste a busca ou o filtro de situação.' : 'Cadastre quem atende para liberar horários na agenda.'
        "
      />
    </AppCard>

    <template v-else>
      <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" :aria-busy="list.loading.value">
        <li v-for="professional in list.items.value" :key="professional.id">
          <RouterLink
            :to="{
              name: 'professional-detail',
              params: { companyId, professionalId: professional.id },
            }"
            class="flex h-full items-start gap-3 rounded-card border border-border bg-card p-4 transition-colors duration-150 hover:border-text-muted"
            :class="{ 'opacity-70': !professional.is_active }"
          >
            <span
              class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-input text-label font-semibold"
              :style="{
                backgroundColor: `color-mix(in srgb, ${professional.color} 22%, transparent)`,
                color: professional.color,
              }"
              aria-hidden="true"
            >
              {{
                professional.name
                  .split(' ')
                  .map((p) => p[0])
                  .slice(0, 2)
                  .join('')
              }}
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-card-title">{{ professional.name }}</span>
              <span class="block truncate text-label text-text-muted">{{
                professional.title ?? 'Sem cargo informado'
              }}</span>
              <span class="mt-2 flex flex-wrap items-center gap-2 text-support text-text-secondary">
                <span
                  >{{ professional.services_count ?? 0 }}
                  {{ professional.services_count === 1 ? 'serviço' : 'serviços' }}</span
                >
                <AppBadge v-if="!professional.is_active">Inativo</AppBadge>
              </span>
            </span>
          </RouterLink>
        </li>
      </ul>
      <AppPagination
        :page="list.meta.value.current_page"
        :last-page="list.meta.value.last_page"
        :per-page="list.meta.value.per_page"
        :total="list.meta.value.total"
        label="Paginação de profissionais"
        @change="list.goTo"
      />
    </template>

    <ProfessionalFormModal v-if="companyId" v-model:open="formOpen" :company-id="companyId" @saved="onSaved" />
  </div>
</template>

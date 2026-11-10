<script setup lang="ts">
import { computed, ref } from 'vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppConfirmDialog from '@/components/ui/AppConfirmDialog.vue'
import AppEmptyState from '@/components/ui/AppEmptyState.vue'
import AppErrorState from '@/components/ui/AppErrorState.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import AppSkeleton from '@/components/ui/AppSkeleton.vue'
import { useCurrentCompany } from '@/composables/useCurrentCompany'
import { usePagedList } from '@/composables/usePagedList'
import { useToast } from '@/composables/useToast'
import { servicesApi, type ServiceQuery } from '@/modules/services/api'
import ServiceFormModal from '@/modules/services/components/ServiceFormModal.vue'
import { formatCurrency } from '@/services/format'
import { formatDuration } from '@/services/forms'
import { isApiError } from '@/services/http'
import type { Service } from '@/types/api'

const toast = useToast()
const { companyId, can } = useCurrentCompany()

const search = ref('')
const status = ref<NonNullable<ServiceQuery['status']>>('active')
const formOpen = ref(false)
const editingId = ref<string | null>(null)
const toggling = ref<Service | null>(null)
const busy = ref(false)

const list = usePagedList(
  (page, signal) =>
    servicesApi.list(
      companyId.value!,
      { search: search.value.trim() || undefined, status: status.value, page },
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
const canManage = computed(() => can('manage_catalog'))

function openForm(id: string | null): void {
  editingId.value = id
  formOpen.value = true
}

async function setActive(service: Service, active: boolean): Promise<void> {
  busy.value = true
  try {
    await servicesApi.update(companyId.value!, service.id, {
      is_active: active,
    })
    toast.success(active ? 'Serviço reativado.' : 'Serviço desativado.')
    toggling.value = null
    await list.reload()
  } catch (caught) {
    toast.error(isApiError(caught) ? caught.message : 'Não foi possível concluir a operação.')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-page-title">Serviços</h1>
        <p class="mt-1 text-body text-text-secondary">Catálogo com preço, duração e quem realiza cada serviço.</p>
      </div>
      <AppButton v-if="canManage" @click="openForm(null)">Novo serviço</AppButton>
    </header>

    <AppCard padding="none">
      <div class="flex flex-col gap-3 border-b border-border p-4 sm:flex-row">
        <div class="flex-1">
          <AppInput
            v-model="search"
            type="search"
            label="Buscar serviços"
            hide-label
            placeholder="Buscar por nome ou categoria"
          />
        </div>
        <div class="sm:w-44">
          <AppSelect v-model="status" label="Situação" hide-label :options="statusOptions" />
        </div>
      </div>

      <AppErrorState v-if="list.error.value" :message="list.error.value" @retry="list.reload" />
      <div
        v-else-if="list.loading.value && list.items.value.length === 0"
        class="flex flex-col gap-5 p-5"
        aria-busy="true"
      >
        <AppSkeleton v-for="n in 4" :key="n" :lines="2" />
      </div>
      <AppEmptyState
        v-else-if="list.items.value.length === 0"
        :title="filtered ? 'Nenhum serviço encontrado' : 'Nenhum serviço cadastrado'"
        :description="
          filtered
            ? 'Ajuste a busca ou o filtro de situação.'
            : 'Cadastre os serviços oferecidos para começar a agendar.'
        "
      >
        <AppButton v-if="!filtered && canManage" size="sm" @click="openForm(null)">Cadastrar serviço</AppButton>
      </AppEmptyState>

      <template v-else>
        <ul class="divide-y divide-border" :aria-busy="list.loading.value">
          <li
            v-for="service in list.items.value"
            :key="service.id"
            class="flex flex-wrap items-center gap-x-5 gap-y-2 px-5 py-4"
            :class="{ 'opacity-70': !service.is_active }"
          >
            <span
              class="h-9 w-1 shrink-0 rounded-full"
              :style="{ backgroundColor: service.color }"
              aria-hidden="true"
            />
            <div class="min-w-0 flex-1 basis-48">
              <p class="flex flex-wrap items-center gap-2">
                <span class="truncate font-medium">{{ service.name }}</span>
                <AppBadge v-if="service.category">{{ service.category }}</AppBadge>
                <AppBadge v-if="!service.is_active">Inativo</AppBadge>
              </p>
              <p v-if="service.description" class="truncate text-label text-text-muted">
                {{ service.description }}
              </p>
            </div>
            <div class="ml-auto flex items-center gap-4">
              <dl class="flex items-center gap-4 text-label">
                <div class="w-28">
                  <dt class="sr-only">Duração</dt>
                  <dd class="font-mono text-text-secondary">
                    {{ formatDuration(service.duration_minutes) }}
                    <span v-if="service.buffer_minutes" class="text-text-muted">+{{ service.buffer_minutes }} min</span>
                  </dd>
                </div>
                <div class="w-14">
                  <dt class="sr-only">Profissionais</dt>
                  <dd class="text-text-secondary">{{ service.professionals_count ?? 0 }} prof.</dd>
                </div>
                <div class="w-24 text-right">
                  <dt class="sr-only">Preço</dt>
                  <dd class="font-mono text-text-primary">
                    {{ formatCurrency(service.price_cents) }}
                  </dd>
                </div>
              </dl>
              <div v-if="canManage" class="flex w-44 justify-end gap-1">
                <AppButton
                  variant="ghost"
                  size="sm"
                  :aria-label="`Editar ${service.name}`"
                  @click="openForm(service.id)"
                  >Editar</AppButton
                >
                <AppButton
                  v-if="service.is_active"
                  variant="ghost"
                  size="sm"
                  :aria-label="`Desativar ${service.name}`"
                  @click="toggling = service"
                >
                  Desativar
                </AppButton>
                <AppButton
                  v-else
                  variant="ghost"
                  size="sm"
                  :aria-label="`Reativar ${service.name}`"
                  :disabled="busy"
                  @click="setActive(service, true)"
                >
                  Reativar
                </AppButton>
              </div>
            </div>
          </li>
        </ul>
        <div class="border-t border-border px-5 py-3">
          <AppPagination
            :page="list.meta.value.current_page"
            :last-page="list.meta.value.last_page"
            :per-page="list.meta.value.per_page"
            :total="list.meta.value.total"
            label="Paginação de serviços"
            @change="list.goTo"
          />
        </div>
      </template>
    </AppCard>

    <ServiceFormModal
      v-if="companyId"
      v-model:open="formOpen"
      :company-id="companyId"
      :service-id="editingId"
      @saved="list.reload"
    />
    <AppConfirmDialog
      :open="toggling !== null"
      title="Desativar serviço"
      :message="`${toggling?.name ?? ''} deixará de aparecer em novos agendamentos. Agendamentos existentes não são alterados.`"
      confirm-label="Desativar"
      :loading="busy"
      @update:open="(v) => !v && (toggling = null)"
      @confirm="toggling && setActive(toggling, false)"
    />
  </div>
</template>

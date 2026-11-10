<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppConfirmDialog from '@/components/ui/AppConfirmDialog.vue'
import AppErrorState from '@/components/ui/AppErrorState.vue'
import AppSkeleton from '@/components/ui/AppSkeleton.vue'
import { useCurrentCompany } from '@/composables/useCurrentCompany'
import { useToast } from '@/composables/useToast'
import { professionalsApi } from '@/modules/professionals/api'
import ProfessionalFormModal from '@/modules/professionals/components/ProfessionalFormModal.vue'
import { servicesApi } from '@/modules/services/api'
import { settingsApi } from '@/modules/settings/api'
import WeeklyHoursEditor from '@/modules/settings/components/WeeklyHoursEditor.vue'
import { firstError, formatDuration, formatPhone } from '@/services/forms'
import { isApiError, type ApiError } from '@/services/http'
import type { Professional, Service, WeeklyPeriod } from '@/types/api'

const route = useRoute()
const toast = useToast()
const { companyId, can } = useCurrentCompany()

const professionalId = computed(() => String(route.params.professionalId))
const canManage = computed(() => can('manage_catalog'))
const canManageHours = computed(() => can('manage_availability'))

const professional = ref<Professional | null>(null)
const catalog = ref<Service[]>([])
const selectedServices = ref<string[]>([])
const periods = ref<WeeklyPeriod[]>([])
const usesCompanyHours = ref(false)

const loading = ref(true)
const loadError = ref<string | null>(null)
const editing = ref(false)
const confirmStatus = ref(false)
const busy = ref<'services' | 'hours' | 'status' | null>(null)
const hoursError = ref<ApiError | null>(null)

async function load(): Promise<void> {
  loading.value = true
  loadError.value = null
  try {
    const [p, services, hours] = await Promise.all([
      professionalsApi.get(companyId.value!, professionalId.value),
      servicesApi.list(companyId.value!, { status: 'all', per_page: 100 }),
      professionalsApi.workingHours(companyId.value!, professionalId.value),
    ])
    professional.value = p
    catalog.value = services.data
    selectedServices.value = (p.services ?? []).map((s) => s.id)
    usesCompanyHours.value = hours.uses_company_hours
    // Without own hours, start from the company's so the editor shows what actually applies.
    periods.value = hours.uses_company_hours ? (await settingsApi.businessHours(companyId.value!)).periods : hours.periods
  } catch (caught) {
    loadError.value = isApiError(caught) ? caught.message : 'Não foi possível carregar o profissional.'
  } finally {
    loading.value = false
  }
}

watch(professionalId, load, { immediate: true })

const servicesDirty = computed(() => {
  const saved = new Set((professional.value?.services ?? []).map((s) => s.id))
  return saved.size !== selectedServices.value.length || selectedServices.value.some((id) => !saved.has(id))
})

async function saveServices(): Promise<void> {
  if (!professional.value) return
  busy.value = 'services'
  try {
    professional.value = await professionalsApi.update(companyId.value!, professional.value.id, {
      service_ids: selectedServices.value,
    })
    selectedServices.value = (professional.value.services ?? []).map((s) => s.id)
    toast.success('Serviços atualizados.')
  } catch (caught) {
    toast.error(isApiError(caught) ? (firstError(caught) ?? caught.message) : 'Não foi possível salvar.')
  } finally {
    busy.value = null
  }
}

async function saveHours(next: WeeklyPeriod[] = periods.value): Promise<void> {
  if (!professional.value) return
  busy.value = 'hours'
  hoursError.value = null
  try {
    const saved = await professionalsApi.saveWorkingHours(companyId.value!, professional.value.id, next)
    usesCompanyHours.value = saved.uses_company_hours
    periods.value = saved.uses_company_hours
      ? (await settingsApi.businessHours(companyId.value!)).periods
      : saved.periods
    toast.success(
      saved.uses_company_hours ? 'O profissional passa a seguir o expediente da empresa.' : 'Horários salvos.',
    )
  } catch (caught) {
    if (!isApiError(caught)) throw caught
    hoursError.value = caught
  } finally {
    busy.value = null
  }
}

async function toggleActive(): Promise<void> {
  if (!professional.value) return
  busy.value = 'status'
  try {
    const active = !professional.value.is_active
    const updated = await professionalsApi.update(companyId.value!, professional.value.id, { is_active: active })
    professional.value = { ...professional.value, ...updated }
    toast.success(active ? 'Profissional reativado.' : 'Profissional desativado.')
    confirmStatus.value = false
  } catch (caught) {
    toast.error(isApiError(caught) ? caught.message : 'Não foi possível concluir a operação.')
  } finally {
    busy.value = null
  }
}

function onSaved(updated: Professional): void {
  professional.value = { ...professional.value, ...updated }
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <RouterLink
      :to="{ name: 'professionals', params: { companyId } }"
      class="w-fit text-label text-text-secondary hover:text-text-primary"
    >
      ← Profissionais
    </RouterLink>

    <AppErrorState v-if="loadError" :message="loadError" @retry="load" />

    <div v-else-if="loading && !professional" class="flex flex-col gap-4" aria-busy="true">
      <AppSkeleton :lines="2" />
      <AppSkeleton :lines="6" />
    </div>

    <template v-else-if="professional">
      <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-3">
          <span
            class="mt-2 size-3 shrink-0 rounded-full"
            :style="{ backgroundColor: professional.color }"
            aria-hidden="true"
          />
          <div>
            <h1 class="flex flex-wrap items-center gap-3 text-page-title">
              {{ professional.name }}
              <AppBadge v-if="!professional.is_active">Inativo</AppBadge>
            </h1>
            <p class="mt-1 text-body text-text-secondary">
              {{ professional.title ?? 'Sem cargo informado' }}
            </p>
          </div>
        </div>
        <div v-if="canManage" class="flex gap-2">
          <AppButton variant="secondary" @click="editing = true">Editar dados</AppButton>
          <AppButton v-if="professional.is_active" variant="danger" @click="confirmStatus = true">Desativar</AppButton>
          <AppButton v-else variant="secondary" :loading="busy === 'status'" @click="toggleActive">Reativar</AppButton>
        </div>
      </header>

      <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
        <div class="flex flex-col gap-6">
          <AppCard title="Contato">
            <dl class="grid gap-4 text-body">
              <div>
                <dt class="text-label text-text-muted">Telefone</dt>
                <dd class="font-mono">
                  {{ formatPhone(professional.phone) || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-label text-text-muted">E-mail</dt>
                <dd class="break-all">{{ professional.email ?? '—' }}</dd>
              </div>
              <div v-if="professional.bio">
                <dt class="text-label text-text-muted">Descrição</dt>
                <dd class="whitespace-pre-line text-text-secondary">
                  {{ professional.bio }}
                </dd>
              </div>
            </dl>
          </AppCard>

          <AppCard title="Serviços realizados" description="Só aparecem no agendamento os serviços marcados.">
            <p v-if="catalog.length === 0" class="text-label text-text-muted">Nenhum serviço cadastrado.</p>
            <fieldset v-else :disabled="!canManage" class="flex flex-col gap-1">
              <legend class="sr-only">Serviços realizados</legend>
              <label
                v-for="service in catalog"
                :key="service.id"
                class="flex items-center gap-3 rounded-input px-2 py-1.5 hover:bg-elevated/50"
                :class="{ 'opacity-60': !service.is_active }"
              >
                <input
                  v-model="selectedServices"
                  type="checkbox"
                  :value="service.id"
                  class="size-4 accent-(--color-primary)"
                />
                <span class="size-2 rounded-full" :style="{ backgroundColor: service.color }" aria-hidden="true" />
                <span class="flex-1 truncate text-body">{{ service.name }}</span>
                <span class="font-mono text-support text-text-muted">{{
                  formatDuration(service.duration_minutes)
                }}</span>
                <span v-if="!service.is_active" class="text-support text-text-muted">inativo</span>
              </label>
            </fieldset>
            <div v-if="canManage" class="mt-4 flex justify-end">
              <AppButton size="sm" :disabled="!servicesDirty" :loading="busy === 'services'" @click="saveServices"
                >Salvar serviços</AppButton
              >
            </div>
          </AppCard>
        </div>

        <AppCard
          title="Horários de trabalho"
          description="Cruzados com o expediente da empresa para liberar horários na agenda."
        >
          <p
            v-if="usesCompanyHours"
            class="mb-3 rounded-input border border-border bg-surface px-3 py-2.5 text-label text-text-secondary"
          >
            Sem horário próprio: segue o expediente da empresa, mostrado abaixo.
            <template v-if="canManageHours">Ajuste e salve para definir um horário específico.</template>
          </p>
          <WeeklyHoursEditor v-model="periods" :disabled="!canManageHours" :error="firstError(hoursError)" />
          <div v-if="canManageHours" class="mt-4 flex flex-wrap justify-end gap-2 border-t border-border pt-4">
            <AppButton
              v-if="!usesCompanyHours"
              variant="ghost"
              size="sm"
              :disabled="busy === 'hours'"
              @click="saveHours([])"
            >
              Usar expediente da empresa
            </AppButton>
            <AppButton size="sm" :loading="busy === 'hours'" @click="saveHours()">Salvar horários</AppButton>
          </div>
        </AppCard>
      </div>

      <ProfessionalFormModal
        v-model:open="editing"
        :company-id="companyId!"
        :professional="professional"
        @saved="onSaved"
      />
      <AppConfirmDialog
        v-model:open="confirmStatus"
        title="Desativar profissional"
        :message="`${professional.name} deixará de receber novos agendamentos. Os agendamentos existentes e o histórico são mantidos.`"
        confirm-label="Desativar"
        :loading="busy === 'status'"
        @confirm="toggleActive"
      />
    </template>
  </div>
</template>

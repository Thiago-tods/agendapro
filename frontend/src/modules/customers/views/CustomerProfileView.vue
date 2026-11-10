<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppConfirmDialog from '@/components/ui/AppConfirmDialog.vue'
import AppEmptyState from '@/components/ui/AppEmptyState.vue'
import AppErrorState from '@/components/ui/AppErrorState.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import AppSkeleton from '@/components/ui/AppSkeleton.vue'
import { useCurrentCompany } from '@/composables/useCurrentCompany'
import { usePagedList } from '@/composables/usePagedList'
import { useToast } from '@/composables/useToast'
import { STATUS_TONE } from '@/modules/appointments/status'
import { customersApi } from '@/modules/customers/api'
import CustomerFormModal from '@/modules/customers/components/CustomerFormModal.vue'
import { formatCurrency, formatLongDate, formatShortDate, todayIn } from '@/services/format'
import { formatPhone } from '@/services/forms'
import { isApiError } from '@/services/http'
import type { Customer } from '@/types/api'

const route = useRoute()
const toast = useToast()
const { companyId, company, can } = useCurrentCompany()

const customerId = computed(() => String(route.params.customerId))
const customer = ref<Customer | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const editing = ref(false)
const confirmArchive = ref(false)
const archiving = ref(false)

const timezone = computed(() => company.value?.timezone ?? 'America/Sao_Paulo')

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    customer.value = await customersApi.get(companyId.value!, customerId.value)
  } catch (caught) {
    error.value = isApiError(caught) ? caught.message : 'Não foi possível carregar o cliente.'
  } finally {
    loading.value = false
  }
}

watch(customerId, load, { immediate: true })

const history = usePagedList((page) => customersApi.history(companyId.value!, customerId.value, page), [customerId])

function localDate(iso: string | null | undefined): string {
  return iso ? formatShortDate(todayIn(timezone.value, new Date(iso))) : '—'
}

function onSaved(updated: Customer): void {
  customer.value = { ...customer.value, ...updated }
}

async function toggleArchive(): Promise<void> {
  if (!customer.value || archiving.value) return
  archiving.value = true
  try {
    const wasArchived = customer.value.is_archived
    const updated = wasArchived
      ? await customersApi.restore(companyId.value!, customer.value.id)
      : await customersApi.archive(companyId.value!, customer.value.id)
    onSaved(updated)
    toast.success(wasArchived ? 'Cliente reativado.' : 'Cliente arquivado.')
    confirmArchive.value = false
  } catch (caught) {
    toast.error(isApiError(caught) ? caught.message : 'Não foi possível concluir a operação.')
  } finally {
    archiving.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <RouterLink
      :to="{ name: 'customers', params: { companyId } }"
      class="w-fit text-label text-text-secondary hover:text-text-primary"
    >
      ← Clientes
    </RouterLink>

    <AppErrorState v-if="error" :message="error" @retry="load" />

    <div v-else-if="loading && !customer" class="flex flex-col gap-4" aria-busy="true">
      <AppSkeleton :lines="2" />
      <AppSkeleton :lines="4" />
    </div>

    <template v-else-if="customer">
      <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 class="flex flex-wrap items-center gap-3 text-page-title">
            {{ customer.name }}
            <AppBadge v-if="customer.is_archived">Arquivado</AppBadge>
          </h1>
          <p class="mt-1 text-label text-text-muted">Cliente desde {{ localDate(customer.created_at) }}</p>
        </div>
        <div v-if="can('manage_customers')" class="flex gap-2">
          <AppButton variant="secondary" @click="editing = true">Editar</AppButton>
          <AppButton v-if="customer.is_archived" variant="secondary" :loading="archiving" @click="toggleArchive"
            >Reativar</AppButton
          >
          <AppButton v-else variant="danger" @click="confirmArchive = true">Arquivar</AppButton>
        </div>
      </header>

      <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
        <div class="flex flex-col gap-6">
          <AppCard title="Contato">
            <dl class="grid gap-4 text-body">
              <div>
                <dt class="text-label text-text-muted">Telefone</dt>
                <dd class="font-mono">
                  {{ formatPhone(customer.phone) || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-label text-text-muted">E-mail</dt>
                <dd class="break-all">{{ customer.email ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-label text-text-muted">Data de nascimento</dt>
                <dd>
                  {{
                    customer.birth_date
                      ? formatShortDate(customer.birth_date) + ' ' + customer.birth_date.slice(0, 4)
                      : '—'
                  }}
                </dd>
              </div>
              <div v-if="customer.notes">
                <dt class="text-label text-text-muted">Observações</dt>
                <dd class="whitespace-pre-line text-text-secondary">
                  {{ customer.notes }}
                </dd>
              </div>
            </dl>
          </AppCard>

          <AppCard title="Frequência">
            <dl class="grid grid-cols-2 gap-4">
              <div>
                <dt class="text-label text-text-muted">Atendimentos concluídos</dt>
                <dd class="font-mono text-section-title">
                  {{ customer.visits_count ?? 0 }}
                </dd>
              </div>
              <div>
                <dt class="text-label text-text-muted">Última visita</dt>
                <dd class="text-body">
                  {{ localDate(customer.last_visit_at) }}
                </dd>
              </div>
              <div class="col-span-2">
                <dt class="text-label text-text-muted">Próximo horário</dt>
                <dd class="text-body first-letter:uppercase">
                  {{
                    customer.next_appointment_at
                      ? formatLongDate(customer.next_appointment_at, timezone)
                      : 'Nenhum agendamento futuro'
                  }}
                </dd>
              </div>
            </dl>
          </AppCard>
        </div>

        <AppCard title="Histórico de atendimentos" padding="none">
          <AppErrorState v-if="history.error.value" :message="history.error.value" @retry="history.reload" />
          <div
            v-else-if="history.loading.value && history.items.value.length === 0"
            class="flex flex-col gap-4 p-5"
            aria-busy="true"
          >
            <AppSkeleton v-for="n in 3" :key="n" :lines="2" />
          </div>
          <AppEmptyState
            v-else-if="history.items.value.length === 0"
            title="Sem atendimentos"
            description="Os agendamentos deste cliente aparecerão aqui."
          />
          <template v-else>
            <ul class="divide-y divide-border">
              <li
                v-for="appointment in history.items.value"
                :key="appointment.id"
                class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3"
              >
                <span class="w-24 shrink-0 font-mono text-label text-text-secondary">
                  {{ formatShortDate(appointment.local.date) }}
                  {{ appointment.local.date.slice(0, 4) }}
                  <span class="block text-text-muted">{{ appointment.local.start }}</span>
                </span>
                <span class="min-w-0 flex-1">
                  <span class="block truncate font-medium">{{ appointment.service.name }}</span>
                  <span class="block truncate text-label text-text-muted">
                    {{ appointment.professional?.name }}
                    <template v-if="can('view_financials')">
                      ·
                      {{ formatCurrency(appointment.service.price_cents) }}</template
                    >
                  </span>
                  <span v-if="appointment.cancellation_reason" class="block text-support text-text-muted"
                    >Motivo: {{ appointment.cancellation_reason }}</span
                  >
                </span>
                <AppBadge :tone="STATUS_TONE[appointment.status]" dot>{{ appointment.status_label }}</AppBadge>
              </li>
            </ul>
            <div class="border-t border-border px-5 py-3">
              <AppPagination
                :page="history.meta.value.current_page"
                :last-page="history.meta.value.last_page"
                :per-page="history.meta.value.per_page"
                :total="history.meta.value.total"
                label="Paginação do histórico"
                @change="history.goTo"
              />
            </div>
          </template>
        </AppCard>
      </div>

      <CustomerFormModal v-model:open="editing" :company-id="companyId!" :customer="customer" @saved="onSaved" />
      <AppConfirmDialog
        v-model:open="confirmArchive"
        title="Arquivar cliente"
        :message="`${customer.name} deixará de aparecer na busca do agendamento. O histórico é mantido e você pode reativar depois.`"
        confirm-label="Arquivar"
        :loading="archiving"
        @confirm="toggleArchive"
      />
    </template>
  </div>
</template>

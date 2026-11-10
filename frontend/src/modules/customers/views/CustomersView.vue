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
import { customersApi, type CustomerStatusFilter } from '@/modules/customers/api'
import CustomerFormModal from '@/modules/customers/components/CustomerFormModal.vue'
import { formatShortDate, todayIn } from '@/services/format'
import { formatPhone } from '@/services/forms'
import type { Customer } from '@/types/api'

const router = useRouter()
const { companyId, company, can } = useCurrentCompany()

const search = ref('')
const status = ref<CustomerStatusFilter>('active')
const formOpen = ref(false)

const list = usePagedList(
  (page, signal) =>
    customersApi.list(
      companyId.value!,
      { search: search.value.trim() || undefined, status: status.value, page },
      signal,
    ),
  [search, status],
  { debounceMs: 300 },
)

const statusOptions = [
  { value: 'active', label: 'Ativos' },
  { value: 'archived', label: 'Arquivados' },
  { value: 'all', label: 'Todos' },
]

const filtered = computed(() => search.value.trim() !== '' || status.value !== 'active')

/** Instant → local date in the company timezone, shown as "13 nov". */
function localDate(iso: string | null | undefined): string {
  return iso ? formatShortDate(todayIn(company.value?.timezone ?? 'America/Sao_Paulo', new Date(iso))) : '—'
}

function openProfile(customer: Customer): void {
  void router.push({
    name: 'customer-profile',
    params: { companyId: companyId.value, customerId: customer.id },
  })
}

function onSaved(customer: Customer): void {
  openProfile(customer)
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-page-title">Clientes</h1>
        <p class="mt-1 text-body text-text-secondary">Contatos, histórico de visitas e próximos horários.</p>
      </div>
      <AppButton v-if="can('manage_customers')" @click="formOpen = true">Novo cliente</AppButton>
    </header>

    <AppCard padding="none">
      <div class="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-end">
        <div class="flex-1">
          <AppInput
            v-model="search"
            type="search"
            label="Buscar clientes"
            hide-label
            placeholder="Buscar por nome, telefone ou e-mail"
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
        <AppSkeleton v-for="n in 5" :key="n" :lines="2" />
      </div>

      <AppEmptyState
        v-else-if="list.items.value.length === 0"
        :title="filtered ? 'Nenhum cliente encontrado' : 'Nenhum cliente cadastrado'"
        :description="
          filtered
            ? 'Ajuste a busca ou o filtro de situação.'
            : 'Os clientes cadastrados aqui ficam disponíveis no agendamento.'
        "
      >
        <AppButton v-if="!filtered && can('manage_customers')" size="sm" @click="formOpen = true"
          >Cadastrar cliente</AppButton
        >
      </AppEmptyState>

      <template v-else>
        <!-- Desktop: table. Mobile: one card per customer. -->
        <table class="hidden w-full text-left md:table" :aria-busy="list.loading.value">
          <thead class="border-b border-border text-label text-text-muted">
            <tr>
              <th scope="col" class="px-5 py-3 font-medium">Cliente</th>
              <th scope="col" class="px-5 py-3 font-medium">Contato</th>
              <th scope="col" class="px-5 py-3 text-right font-medium">Visitas</th>
              <th scope="col" class="px-5 py-3 font-medium">Última visita</th>
              <th scope="col" class="px-5 py-3 font-medium">Próximo horário</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <tr
              v-for="customer in list.items.value"
              :key="customer.id"
              class="transition-colors duration-150 hover:bg-elevated/50"
            >
              <td class="px-5 py-3">
                <RouterLink
                  :to="{
                    name: 'customer-profile',
                    params: { companyId, customerId: customer.id },
                  }"
                  class="font-medium text-text-primary hover:text-primary-hover focus-visible:underline"
                >
                  {{ customer.name }}
                </RouterLink>
                <AppBadge v-if="customer.is_archived" class="ml-2">Arquivado</AppBadge>
              </td>
              <td class="px-5 py-3 text-label text-text-secondary">
                <span class="block font-mono">{{ formatPhone(customer.phone) || '—' }}</span>
                <span v-if="customer.email" class="block truncate text-text-muted">{{ customer.email }}</span>
              </td>
              <td class="px-5 py-3 text-right font-mono text-label">
                {{ customer.visits_count ?? 0 }}
              </td>
              <td class="px-5 py-3 text-label text-text-secondary">
                {{ localDate(customer.last_visit_at) }}
              </td>
              <td class="px-5 py-3 text-label text-text-secondary">
                {{ localDate(customer.next_appointment_at) }}
              </td>
            </tr>
          </tbody>
        </table>

        <ul class="divide-y divide-border md:hidden">
          <li v-for="customer in list.items.value" :key="customer.id">
            <RouterLink
              :to="{
                name: 'customer-profile',
                params: { companyId, customerId: customer.id },
              }"
              class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-elevated/50"
            >
              <span class="min-w-0">
                <span class="block truncate font-medium">{{ customer.name }}</span>
                <span class="block font-mono text-label text-text-muted">{{
                  formatPhone(customer.phone) || customer.email || '—'
                }}</span>
              </span>
              <span class="shrink-0 text-right text-support text-text-muted">
                <span class="block">{{ customer.visits_count ?? 0 }} visitas</span>
                <AppBadge v-if="customer.is_archived">Arquivado</AppBadge>
              </span>
            </RouterLink>
          </li>
        </ul>

        <div class="border-t border-border px-5 py-3">
          <AppPagination
            :page="list.meta.value.current_page"
            :last-page="list.meta.value.last_page"
            :per-page="list.meta.value.per_page"
            :total="list.meta.value.total"
            label="Paginação de clientes"
            @change="list.goTo"
          />
        </div>
      </template>
    </AppCard>

    <CustomerFormModal v-if="companyId" v-model:open="formOpen" :company-id="companyId" @saved="onSaved" />
  </div>
</template>

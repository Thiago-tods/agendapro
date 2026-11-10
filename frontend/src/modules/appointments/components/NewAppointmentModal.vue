<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import { useCurrentCompany } from '@/composables/useCurrentCompany'
import { useToast } from '@/composables/useToast'
import { bookingApi } from '@/modules/appointments/api'
import { formatCurrency, todayIn } from '@/services/format'
import { isApiError, type ApiError } from '@/services/http'
import type { CustomerSummary, ProfessionalSummary, ServiceSummary } from '@/types/api'

/**
 * Quick booking. Only the shape is checked here; hours, blocks, conflicts and record status
 * are validated by the API in the booking transaction, and its messages are shown as-is.
 */
const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{
  /** Prefill from a calendar slot. The professional applies once a service they perform is chosen. */
  initial?: { date: string; time: string; professionalId?: string } | null
}>()
const emit = defineEmits<{ created: [] }>()

const { company } = useCurrentCompany()
const toast = useToast()

const form = reactive({
  serviceId: '',
  professionalId: '',
  customerId: '',
  date: '',
  time: '',
  notes: '',
})
const services = ref<ServiceSummary[]>([])
const professionals = ref<ProfessionalSummary[]>([])
const customers = ref<CustomerSummary[]>([])
const customerSearch = ref('')
const loadingOptions = ref(false)
const submitting = ref(false)
const error = ref<ApiError | null>(null)
const optionsError = ref<string | null>(null)

const timezone = computed(() => company.value?.timezone ?? 'America/Sao_Paulo')
const selectedService = computed(() => services.value.find((s) => s.id === form.serviceId))

const serviceOptions = computed(() => [
  { value: '', label: 'Selecione o serviço' },
  ...services.value.map((s) => ({
    value: s.id,
    label: `${s.name} · ${s.duration_minutes} min · ${formatCurrency(s.price_cents)}`,
  })),
])
const professionalOptions = computed(() => [
  {
    value: '',
    label: form.serviceId
      ? professionals.value.length
        ? 'Selecione o profissional'
        : 'Nenhum profissional realiza este serviço'
      : 'Escolha o serviço primeiro',
  },
  ...professionals.value.map((p) => ({ value: p.id, label: p.name })),
])
const customerOptions = computed(() => [
  {
    value: '',
    label: customers.value.length ? 'Selecione o cliente' : 'Nenhum cliente encontrado',
  },
  ...customers.value.map((c) => ({
    value: c.id,
    label: c.phone ? `${c.name} · ${c.phone}` : c.name,
  })),
])

const canSubmit = computed(
  () => !!(form.serviceId && form.professionalId && form.customerId && form.date && form.time) && !submitting.value,
)

/** Conflict (409) and other non-field errors are shown above the actions. */
const generalError = computed(() => (error.value && !error.value.isValidation ? error.value.message : null))

function resetForm(): void {
  Object.assign(form, {
    serviceId: '',
    professionalId: '',
    customerId: '',
    date: props.initial?.date ?? todayIn(timezone.value),
    time: props.initial?.time ?? '',
    notes: '',
  })
  customerSearch.value = ''
  professionals.value = []
  error.value = null
}

async function loadInitialOptions(): Promise<void> {
  if (!company.value) return
  loadingOptions.value = true
  optionsError.value = null
  try {
    ;[services.value, customers.value] = await Promise.all([
      bookingApi.activeServices(company.value.id),
      bookingApi.searchCustomers(company.value.id, ''),
    ])
  } catch (caught) {
    optionsError.value = isApiError(caught) ? caught.message : 'Não foi possível carregar os dados do formulário.'
  } finally {
    loadingOptions.value = false
  }
}

watch(open, (value) => {
  if (value) {
    resetForm()
    void loadInitialOptions()
  }
})

watch(
  () => form.serviceId,
  async (serviceId) => {
    form.professionalId = ''
    professionals.value = []
    if (!serviceId || !company.value) return
    professionals.value = await bookingApi.professionalsFor(company.value.id, serviceId).catch(() => [])
    const preferred = professionals.value.find((p) => p.id === props.initial?.professionalId)
    if (preferred) form.professionalId = preferred.id
    else if (professionals.value.length === 1) form.professionalId = professionals.value[0]?.id ?? ''
  },
)

let searchTimer: number | undefined
let searchController: AbortController | null = null
watch(customerSearch, (term) => {
  window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(async () => {
    if (!company.value) return
    searchController?.abort()
    searchController = new AbortController()
    try {
      customers.value = await bookingApi.searchCustomers(company.value.id, term.trim(), searchController.signal)
      if (!customers.value.some((c) => c.id === form.customerId)) form.customerId = customers.value[0]?.id ?? ''
    } catch {
      /* aborted or failed: keep the previous list */
    }
  }, 300)
})

async function submit(): Promise<void> {
  if (!company.value || !canSubmit.value) return
  submitting.value = true
  error.value = null

  try {
    await bookingApi.create(company.value.id, {
      service_id: form.serviceId,
      professional_id: form.professionalId,
      customer_id: form.customerId,
      starts_at: `${form.date}T${form.time}`,
      notes: form.notes.trim() || undefined,
    })
    toast.success('Agendamento criado.')
    open.value = false
    emit('created')
  } catch (caught) {
    if (!isApiError(caught)) throw caught
    error.value = caught
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppModal v-model:open="open" title="Novo agendamento" :description="`Horários no fuso ${timezone}.`">
    <div
      v-if="optionsError"
      class="rounded-input border border-danger/40 bg-danger/10 px-3 py-2.5 text-body text-danger"
      role="alert"
    >
      {{ optionsError }}
      <AppButton class="mt-2" size="sm" variant="secondary" @click="loadInitialOptions">Tentar novamente</AppButton>
    </div>

    <form v-else id="new-appointment" class="flex flex-col gap-5" novalidate @submit.prevent="submit">
      <AppSelect
        v-model="form.serviceId"
        label="Serviço"
        :options="serviceOptions"
        :disabled="loadingOptions"
        :error="error?.field('service_id')"
        :hint="
          selectedService && selectedService.buffer_minutes
            ? `Inclui ${selectedService.buffer_minutes} min de intervalo após o atendimento.`
            : undefined
        "
      />

      <AppSelect
        v-model="form.professionalId"
        label="Profissional"
        :options="professionalOptions"
        :disabled="!form.serviceId || !professionals.length"
        :error="error?.field('professional_id')"
      />

      <div class="flex flex-col gap-2">
        <AppInput
          v-model="customerSearch"
          label="Cliente"
          type="search"
          placeholder="Buscar por nome, e-mail ou telefone"
        />
        <AppSelect
          v-model="form.customerId"
          label="Cliente selecionado"
          hide-label
          :options="customerOptions"
          :disabled="loadingOptions || !customers.length"
          :error="error?.field('customer_id')"
        />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <AppInput v-model="form.date" label="Data" type="date" :min="todayIn(timezone)" required />
        <AppInput v-model="form.time" label="Horário" type="time" :step="300" required />
      </div>
      <p v-if="error?.field('starts_at')" class="-mt-3 text-support text-danger" role="alert">
        {{ error.field('starts_at') }}
      </p>

      <AppInput v-model="form.notes" label="Observações" placeholder="Opcional" :error="error?.field('notes')" />

      <p
        v-if="generalError"
        class="rounded-input border border-danger/40 bg-danger/10 px-3 py-2.5 text-body text-danger"
        role="alert"
      >
        {{ generalError }}
      </p>
    </form>

    <template #footer>
      <AppButton variant="ghost" @click="open = false">Cancelar</AppButton>
      <AppButton type="submit" form="new-appointment" :loading="submitting" :disabled="!canSubmit">Agendar</AppButton>
    </template>
  </AppModal>
</template>

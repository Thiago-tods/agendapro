<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import AppSkeleton from '@/components/ui/AppSkeleton.vue'
import { useCurrentCompany } from '@/composables/useCurrentCompany'
import { useToast } from '@/composables/useToast'
import { bookingApi } from '@/modules/appointments/api'
import { formatDate } from '@/modules/appointments/calendar/dates'
import { STATUS_TONE } from '@/modules/appointments/status'
import { formatCurrency, formatRelative } from '@/services/format'
import { isApiError, type ApiError } from '@/services/http'
import type { Appointment, AppointmentStatus, ProfessionalSummary } from '@/types/api'

/**
 * Appointment details and operations. Which buttons appear follows the status lifecycle
 * only to avoid offering obviously impossible actions; the API validates every transition
 * and its error messages are shown as-is.
 */
const props = defineProps<{ appointmentId: string | null }>()
const emit = defineEmits<{ close: []; changed: [appointment: Appointment] }>()

const { company, can } = useCurrentCompany()
const toast = useToast()

const open = computed({
  get: () => props.appointmentId !== null,
  set: (value) => {
    if (!value) emit('close')
  },
})

const appointment = ref<Appointment | null>(null)
const loading = ref(false)
const loadError = ref<string | null>(null)
const busy = ref<string | null>(null)
const error = ref<ApiError | null>(null)
const mode = ref<'view' | 'cancel' | 'reschedule'>('view')

const cancelReason = ref('')
const reschedule = ref({ date: '', time: '', professionalId: '', reason: '' })
const professionals = ref<ProfessionalSummary[]>([])

async function load(id: string): Promise<void> {
  if (!company.value) return
  loading.value = true
  loadError.value = null
  error.value = null
  mode.value = 'view'
  try {
    appointment.value = await bookingApi.get(company.value.id, id)
  } catch (caught) {
    loadError.value = isApiError(caught) ? caught.message : 'Não foi possível carregar o agendamento.'
  } finally {
    loading.value = false
  }
}

watch(
  () => props.appointmentId,
  (id) => {
    appointment.value = null
    if (id) void load(id)
  },
  { immediate: true },
)

const status = computed<AppointmentStatus | null>(() => appointment.value?.status ?? null)
const canManage = computed(() => can('manage_appointments'))
/** Actions come from the API (state + time rules live there); the role decides visibility. */
const allowed = computed(() => new Set(canManage.value ? (appointment.value?.available_actions ?? []) : []))

const actions = computed(() => {
  const s = status.value
  const list: {
    key: 'confirm' | 'check-in' | 'complete' | 'no-show'
    label: string
    variant: 'primary' | 'secondary'
  }[] = []
  if (allowed.value.has('confirm')) list.push({ key: 'confirm', label: 'Confirmar', variant: 'primary' })
  if (allowed.value.has('check_in'))
    list.push({ key: 'check-in', label: 'Fazer check-in', variant: s === 'confirmed' ? 'primary' : 'secondary' })
  if (allowed.value.has('complete')) list.push({ key: 'complete', label: 'Concluir atendimento', variant: 'primary' })
  if (allowed.value.has('no_show')) list.push({ key: 'no-show', label: 'Não compareceu', variant: 'secondary' })
  return list
})
const canReschedule = computed(() => allowed.value.has('reschedule'))
const canCancel = computed(() => allowed.value.has('cancel'))

const SUCCESS: Record<string, string> = {
  confirm: 'Agendamento confirmado.',
  'check-in': 'Check-in realizado.',
  complete: 'Atendimento concluído.',
  'no-show': 'Não comparecimento registrado.',
  cancel: 'Agendamento cancelado.',
  reschedule: 'Agendamento remarcado.',
}

async function run(key: string, operation: () => Promise<Appointment>): Promise<void> {
  busy.value = key
  error.value = null
  try {
    appointment.value = await operation()
    mode.value = 'view'
    toast.success(SUCCESS[key] ?? 'Agendamento atualizado.')
    emit('changed', appointment.value)
  } catch (caught) {
    if (!isApiError(caught)) throw caught
    error.value = caught
    // The appointment may have changed elsewhere (e.g. 409/422 on a stale status): refresh it.
    if (caught.status === 409 || caught.status === 422) {
      const id = appointment.value?.id
      if (id && company.value)
        appointment.value = await bookingApi.get(company.value.id, id).catch(() => appointment.value)
    }
  } finally {
    busy.value = null
  }
}

function transition(key: 'confirm' | 'check-in' | 'complete' | 'no-show'): void {
  const a = appointment.value
  if (!a || !company.value) return
  const companyId = company.value.id
  void run(key, () => bookingApi.transition(companyId, a.id, key))
}

function submitCancel(): void {
  const a = appointment.value
  if (!a || !company.value) return
  const companyId = company.value.id
  void run('cancel', () => bookingApi.transition(companyId, a.id, 'cancel', cancelReason.value.trim()))
}

async function startReschedule(): Promise<void> {
  const a = appointment.value
  if (!a || !company.value) return
  reschedule.value = {
    date: a.local.date,
    time: a.local.start,
    professionalId: a.professional?.id ?? '',
    reason: '',
  }
  error.value = null
  mode.value = 'reschedule'
  professionals.value = await bookingApi.professionalsFor(company.value.id, a.service.id).catch(() => [])
}

function submitReschedule(): void {
  const a = appointment.value
  if (!a || !company.value) return
  const companyId = company.value.id
  const form = reschedule.value
  void run('reschedule', () =>
    bookingApi.reschedule(companyId, a.id, {
      starts_at: `${form.date}T${form.time}`,
      professional_id: form.professionalId || undefined,
      reason: form.reason.trim() || undefined,
    }),
  )
}

const professionalOptions = computed(() => professionals.value.map((p) => ({ value: p.id, label: p.name })))

const generalError = computed(() => {
  const e = error.value
  if (!e) return null
  if (e.isValidation) {
    // Field errors not rendered next to an input (e.g. "status") are shown here.
    const shown =
      mode.value === 'cancel'
        ? ['reason']
        : mode.value === 'reschedule'
          ? ['starts_at', 'professional_id', 'reason']
          : []
    const other = Object.entries(e.fieldErrors).find(([field]) => !shown.includes(field))
    return other?.[1][0] ?? null
  }
  return e.message
})

function dateLabel(a: Appointment): string {
  return formatDate(a.local.date, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  })
}
</script>

<template>
  <AppModal
    v-model:open="open"
    placement="side"
    title="Agendamento"
    :description="appointment ? dateLabel(appointment) : undefined"
  >
    <div v-if="loading && !appointment" class="flex flex-col gap-4">
      <AppSkeleton v-for="i in 4" :key="i" :lines="2" />
    </div>

    <div v-else-if="loadError" class="flex flex-col items-start gap-3" role="alert">
      <p class="text-danger">{{ loadError }}</p>
      <AppButton size="sm" variant="secondary" @click="appointmentId && load(appointmentId)"
        >Tentar novamente</AppButton
      >
    </div>

    <div v-else-if="appointment" class="flex flex-col gap-6">
      <section class="flex flex-col gap-3">
        <div class="flex items-center justify-between gap-3">
          <p class="font-mono text-section-title">{{ appointment.local.start }}–{{ appointment.local.end }}</p>
          <AppBadge :tone="STATUS_TONE[appointment.status]" dot>{{ appointment.status_label }}</AppBadge>
        </div>
        <dl class="grid grid-cols-[110px_minmax(0,1fr)] gap-x-3 gap-y-2 text-body">
          <dt class="text-text-muted">Cliente</dt>
          <dd class="min-w-0">
            <p class="truncate font-medium">{{ appointment.customer?.name }}</p>
            <p v-if="appointment.customer?.phone" class="font-mono text-label text-text-secondary">
              {{ appointment.customer.phone }}
            </p>
          </dd>
          <dt class="text-text-muted">Serviço</dt>
          <dd>
            {{ appointment.service.name }}
            <span class="text-text-secondary"
              >· {{ appointment.service.duration_minutes }} min ·
              {{ formatCurrency(appointment.service.price_cents) }}</span
            >
          </dd>
          <dt class="text-text-muted">Profissional</dt>
          <dd class="flex items-center gap-2">
            <span
              class="size-2 rounded-full"
              :style="{ backgroundColor: appointment.professional?.color }"
              aria-hidden="true"
            />
            {{ appointment.professional?.name }}
          </dd>
          <template v-if="appointment.notes">
            <dt class="text-text-muted">Observações</dt>
            <dd class="whitespace-pre-line">{{ appointment.notes }}</dd>
          </template>
          <template v-if="appointment.cancellation_reason">
            <dt class="text-text-muted">Motivo</dt>
            <dd>{{ appointment.cancellation_reason }}</dd>
          </template>
        </dl>
      </section>

      <p
        v-if="generalError"
        class="rounded-input border border-danger/40 bg-danger/10 px-3 py-2.5 text-body text-danger"
        role="alert"
      >
        {{ generalError }}
      </p>

      <!-- Actions -->
      <section
        v-if="mode === 'view' && (actions.length || canReschedule)"
        class="flex flex-wrap gap-2"
        aria-label="Ações"
      >
        <AppButton
          v-for="action in actions"
          :key="action.key"
          :variant="action.variant"
          size="sm"
          :loading="busy === action.key"
          :disabled="busy !== null"
          @click="transition(action.key)"
        >
          {{ action.label }}
        </AppButton>
        <AppButton v-if="canReschedule" variant="secondary" size="sm" :disabled="busy !== null" @click="startReschedule"
          >Remarcar</AppButton
        >
        <AppButton
          v-if="canCancel"
          variant="danger"
          size="sm"
          :disabled="busy !== null"
          @click="mode = 'cancel'; error = null"
          >Cancelar</AppButton
        >
      </section>

      <!-- Cancel -->
      <form
        v-if="mode === 'cancel'"
        class="flex flex-col gap-3 rounded-card border border-border p-4"
        novalidate
        @submit.prevent="submitCancel"
      >
        <p class="font-medium">Cancelar agendamento</p>
        <AppInput
          v-model="cancelReason"
          label="Motivo"
          required
          placeholder="Ex.: cliente pediu para desmarcar"
          :error="error?.field('reason')"
        />
        <div class="flex justify-end gap-2">
          <AppButton variant="ghost" size="sm" @click="mode = 'view'">Voltar</AppButton>
          <AppButton
            type="submit"
            variant="danger"
            size="sm"
            :loading="busy === 'cancel'"
            :disabled="cancelReason.trim().length < 3"
          >
            Confirmar cancelamento
          </AppButton>
        </div>
      </form>

      <!-- Reschedule -->
      <form
        v-if="mode === 'reschedule'"
        class="flex flex-col gap-3 rounded-card border border-border p-4"
        novalidate
        @submit.prevent="submitReschedule"
      >
        <p class="font-medium">Remarcar</p>
        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model="reschedule.date" label="Data" type="date" required />
          <AppInput v-model="reschedule.time" label="Horário" type="time" :step="300" required />
        </div>
        <p v-if="error?.field('starts_at')" class="text-support text-danger" role="alert">
          {{ error.field('starts_at') }}
        </p>
        <AppSelect
          v-if="professionalOptions.length"
          v-model="reschedule.professionalId"
          label="Profissional"
          :options="professionalOptions"
          :error="error?.field('professional_id')"
        />
        <AppInput v-model="reschedule.reason" label="Motivo" placeholder="Opcional" />
        <div class="flex justify-end gap-2">
          <AppButton
            variant="ghost"
            size="sm"
            @click="mode = 'view'; error = null"
            >Voltar</AppButton
          >
          <AppButton
            type="submit"
            size="sm"
            :loading="busy === 'reschedule'"
            :disabled="!reschedule.date || !reschedule.time"
          >
            Salvar novo horário
          </AppButton>
        </div>
      </form>

      <!-- History -->
      <section v-if="appointment.events?.length" aria-labelledby="appt-history">
        <h3 id="appt-history" class="text-label font-medium text-text-secondary">Histórico</h3>
        <ol class="mt-2 flex flex-col gap-2.5 border-l border-border pl-4">
          <li v-for="event in appointment.events" :key="event.id" class="relative">
            <span class="absolute top-1.5 -left-[19px] size-1.5 rounded-full bg-text-muted" aria-hidden="true" />
            <p class="text-body">{{ event.label }}</p>
            <p class="text-support text-text-muted">
              {{ formatRelative(event.created_at) }}<template v-if="event.actor"> · {{ event.actor.name }}</template>
              <template v-if="event.reason"> · “{{ event.reason }}”</template>
            </p>
          </li>
        </ol>
      </section>
    </div>
  </AppModal>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CalendarMonthGrid from '@/components/calendar/CalendarMonthGrid.vue'
import CalendarTimeGrid, { type CalendarColumn } from '@/components/calendar/CalendarTimeGrid.vue'
import CalendarToolbar from '@/components/calendar/CalendarToolbar.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import { useCurrentCompany } from '@/composables/useCurrentCompany'
import { bookingApi, type CalendarAvailability } from '@/modules/appointments/api'
import {
  formatDate,
  isValidDate,
  minutesLabel,
  nowIn,
  rangeFor,
  shift,
  titleFor,
  toMinutes,
  weekDates,
  type CalendarView,
} from '@/modules/appointments/calendar/dates'
import { clipToDate, gridBounds, type Range } from '@/modules/appointments/calendar/layout'
import AppointmentDrawer from '@/modules/appointments/components/AppointmentDrawer.vue'
import NewAppointmentModal from '@/modules/appointments/components/NewAppointmentModal.vue'
import { isApiError } from '@/services/http'
import type { Appointment, AppointmentStatus, ProfessionalSummary, ServiceSummary } from '@/types/api'

const route = useRoute()
const router = useRouter()
const { company, can } = useCurrentCompany()

const timezone = computed(() => company.value?.timezone ?? 'America/Sao_Paulo')
const now = ref(nowIn(timezone.value))
let clock: number | undefined
onMounted(() => {
  clock = window.setInterval(() => (now.value = nowIn(timezone.value)), 60_000)
})
onBeforeUnmount(() => window.clearInterval(clock))

// --- URL state (view, date and filters are shareable and survive reloads) ----------------
const isSmallScreen = typeof window !== 'undefined' && window.matchMedia?.('(max-width: 767px)').matches

const view = computed<CalendarView>({
  get: () => {
    const value = route.query.view
    if (value === 'day' || value === 'week' || value === 'month') return value
    return isSmallScreen ? 'day' : 'week'
  },
  set: (value) => update({ view: value }),
})
const date = computed(() => (isValidDate(route.query.date) ? route.query.date : now.value.date))
const professionalId = computed({
  get: () => (typeof route.query.profissional === 'string' ? route.query.profissional : ''),
  set: (value: string) => update({ profissional: value || undefined }),
})
const serviceId = computed({
  get: () => (typeof route.query.servico === 'string' ? route.query.servico : ''),
  set: (value: string) => update({ servico: value || undefined }),
})
const showCancelled = computed({
  get: () => route.query.cancelados === '1',
  set: (value: boolean) => update({ cancelados: value ? '1' : undefined }),
})

function update(patch: Record<string, string | undefined>): void {
  const query = { ...route.query, ...patch }
  Object.keys(query).forEach((key) => query[key] === undefined && delete query[key])
  void router.replace({ query })
}

// --- Data ----------------------------------------------------------------------------------
const professionals = ref<ProfessionalSummary[]>([])
const services = ref<ServiceSummary[]>([])
const appointments = ref<Appointment[]>([])
const availability = ref<CalendarAvailability | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
let controller: AbortController | null = null

const VISIBLE_STATUSES: AppointmentStatus[] = ['scheduled', 'confirmed', 'checked_in', 'completed', 'no_show']

async function loadCatalog(): Promise<void> {
  if (!company.value) return
  const id = company.value.id
  ;[professionals.value, services.value] = await Promise.all([
    bookingApi.activeProfessionals(id).catch(() => []),
    bookingApi.activeServices(id).catch(() => []),
  ])
}

async function load(): Promise<void> {
  if (!company.value) return
  controller?.abort()
  controller = new AbortController()
  const signal = controller.signal
  const { from, to } = rangeFor(view.value, date.value)
  loading.value = true
  error.value = null

  try {
    const [list, slots] = await Promise.all([
      bookingApi.list(
        company.value.id,
        {
          from,
          to,
          professional_id: professionalId.value || undefined,
          service_id: serviceId.value || undefined,
          status: showCancelled.value ? undefined : VISIBLE_STATUSES,
        },
        signal,
      ),
      bookingApi.availability(
        company.value.id,
        {
          from,
          to,
          professional_ids: professionalId.value ? [professionalId.value] : undefined,
        },
        signal,
      ),
    ])
    appointments.value = list
    availability.value = slots
  } catch (caught) {
    if (caught instanceof DOMException && caught.name === 'AbortError') return
    error.value = isApiError(caught) ? caught.message : 'Não foi possível carregar a agenda.'
  } finally {
    if (!signal.aborted) loading.value = false
  }
}

watch(
  () => company.value?.id,
  () => void loadCatalog(),
  { immediate: true },
)
watch([() => company.value?.id, view, date, professionalId, serviceId, showCancelled], () => void load(), {
  immediate: true,
})
onBeforeUnmount(() => controller?.abort())

// --- Columns ---------------------------------------------------------------------------------
function dayInfo(day: string) {
  return availability.value?.days.find((d) => d.date === day)
}

function blocksFor(day: string, professional: string | null) {
  return (availability.value?.blocked ?? [])
    .filter((b) => b.professional_id === null || (professional !== null && b.professional_id === professional))
    .map((b) => {
      const range = clipToDate(b.local_start, b.local_end, day)
      return range ? { start: range[0], end: range[1], reason: b.reason } : null
    })
    .filter((b): b is { start: number; end: number; reason: string | null } => b !== null)
}

const visibleProfessionals = computed(() =>
  professionalId.value ? professionals.value.filter((p) => p.id === professionalId.value) : professionals.value,
)

const columns = computed<CalendarColumn[]>(() => {
  if (view.value === 'day') {
    const day = date.value
    return visibleProfessionals.value.map((p) => ({
      key: p.id,
      date: day,
      label: p.name,
      color: p.color,
      professionalId: p.id,
      appointments: appointments.value.filter((a) => a.professional?.id === p.id && a.local.date === day),
      open: dayInfo(day)?.professionals[p.id] ?? [],
      blocked: blocksFor(day, p.id),
      isToday: day === now.value.date,
    }))
  }

  return weekDates(date.value).map((day) => ({
    key: day,
    date: day,
    label: formatDate(day, { weekday: 'short' }),
    sublabel: formatDate(day, { day: 'numeric', month: 'short' }),
    professionalId: professionalId.value || undefined,
    appointments: appointments.value.filter((a) => a.local.date === day),
    // With one professional selected show their hours; otherwise the company's.
    open: (professionalId.value ? dayInfo(day)?.professionals[professionalId.value] : dayInfo(day)?.company) ?? [],
    // Professional-specific blocks only make sense when the week shows one professional.
    blocked: blocksFor(day, professionalId.value || null),
    isToday: day === now.value.date,
  }))
})

const bounds = computed<Range>(() => {
  const ranges: Range[] = []
  for (const column of columns.value) {
    ranges.push(...column.open)
    column.appointments.forEach((a) =>
      ranges.push([toMinutes(a.local.start), toMinutes(a.local.end) + a.service.buffer_minutes]),
    )
  }
  return gridBounds(ranges)
})

const closedDates = computed(
  () => new Set((availability.value?.days ?? []).filter((d) => !d.company.length).map((d) => d.date)),
)

const title = computed(() => titleFor(view.value, date.value))
const isToday = computed(
  () =>
    rangeFor(view.value, date.value).from <= now.value.date && now.value.date <= rangeFor(view.value, date.value).to,
)
const professionalOptions = computed(() => [
  { value: '', label: 'Todos os profissionais' },
  ...professionals.value.map((p) => ({ value: p.id, label: p.name })),
])
const serviceOptions = computed(() => [
  { value: '', label: 'Todos os serviços' },
  ...services.value.map((s) => ({ value: s.id, label: s.name })),
])

// --- Interactions ------------------------------------------------------------------------
const selectedId = ref<string | null>(null)
const bookingOpen = ref(false)
const bookingInitial = ref<{
  date: string
  time: string
  professionalId?: string
} | null>(null)

function openBooking(slot?: { date: string; minute: number; professionalId?: string }): void {
  bookingInitial.value = slot
    ? {
        date: slot.date,
        time: minutesLabel(slot.minute),
        professionalId: slot.professionalId,
      }
    : { date: date.value, time: '' }
  bookingOpen.value = true
}
</script>

<template>
  <div class="flex flex-col gap-5">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-page-title">Agenda</h1>
      <AppButton v-if="can('manage_appointments')" @click="openBooking()">
        <template #icon>
          <svg
            class="size-4"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            aria-hidden="true"
          >
            <path d="M12 5v14M5 12h14" />
          </svg>
        </template>
        Novo agendamento
      </AppButton>
    </header>

    <CalendarToolbar
      v-model:view="view"
      :title="title"
      :loading="loading"
      :is-today="isToday"
      @previous="update({ date: shift(view, date, -1) })"
      @next="update({ date: shift(view, date, 1) })"
      @today="update({ date: undefined })"
    />

    <div class="flex flex-wrap items-end gap-3">
      <div class="w-full sm:w-56">
        <AppSelect v-model="professionalId" label="Profissional" :options="professionalOptions" />
      </div>
      <div class="w-full sm:w-56">
        <AppSelect v-model="serviceId" label="Serviço" :options="serviceOptions" />
      </div>
      <label class="flex h-10 cursor-pointer items-center gap-2 text-label text-text-secondary select-none">
        <input v-model="showCancelled" type="checkbox" class="size-4 accent-primary" />
        Mostrar cancelados
      </label>
      <p class="ml-auto hidden items-center gap-4 text-support text-text-muted lg:flex" aria-hidden="true">
        <span class="flex items-center gap-1.5"
          ><span
            class="size-3 rounded-[2px] bg-[repeating-linear-gradient(135deg,var(--color-elevated)_0_3px,transparent_3px_6px)] ring-1 ring-border"
          />Fora do expediente</span
        >
        <span class="flex items-center gap-1.5"
          ><span class="size-3 rounded-[2px] bg-danger/20 ring-1 ring-danger/40" />Bloqueado</span
        >
        <span class="flex items-center gap-1.5"
          ><span
            class="h-3 w-4 rounded-[2px] bg-[repeating-linear-gradient(135deg,var(--color-border)_0_2px,transparent_2px_5px)]"
          />Intervalo</span
        >
      </p>
    </div>

    <div
      v-if="error"
      class="flex flex-col items-start gap-3 rounded-card border border-danger/30 bg-danger/5 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
      role="alert"
    >
      <div>
        <p class="font-medium text-danger">Não foi possível carregar a agenda</p>
        <p class="text-label text-text-secondary">{{ error }}</p>
      </div>
      <AppButton variant="secondary" size="sm" :loading="loading" @click="load">Tentar novamente</AppButton>
    </div>

    <template v-else-if="availability">
      <p v-if="!appointments.length && !loading" class="text-label text-text-muted">
        Nenhum agendamento neste período.
      </p>
      <p v-else-if="view === 'week' && !professionalId && professionals.length > 1" class="text-label text-text-muted">
        Horários simultâneos aparecem lado a lado. Filtre por profissional para ver mais detalhes.
      </p>

      <CalendarMonthGrid
        v-if="view === 'month'"
        :date="date"
        :today="now.date"
        :appointments="appointments"
        :closed-dates="closedDates"
        @open="selectedId = $event"
        @day="update({ view: 'day', date: $event })"
      />

      <p
        v-else-if="view === 'day' && !columns.length"
        class="rounded-card border border-dashed border-border px-6 py-10 text-center text-text-secondary"
      >
        Nenhum profissional ativo para exibir.
      </p>

      <CalendarTimeGrid
        v-else
        :columns="columns"
        :bounds="bounds"
        :now-minute="now.minute"
        :can-create="can('manage_appointments')"
        :show-professional="view === 'week' && !professionalId"
        :min-column-width="view === 'day' ? 168 : professionalId ? 112 : 150"
        @open="selectedId = $event"
        @create="openBooking"
      />
    </template>

    <div
      v-else-if="loading"
      class="h-96 animate-pulse rounded-card border border-border bg-card motion-reduce:animate-none"
      aria-label="Carregando agenda"
    />

    <AppointmentDrawer :appointment-id="selectedId" @close="selectedId = null" @changed="load" />
    <NewAppointmentModal
      v-if="can('manage_appointments')"
      v-model:open="bookingOpen"
      :initial="bookingInitial"
      @created="load"
    />
  </div>
</template>

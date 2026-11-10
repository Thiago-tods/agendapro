<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppSkeleton from '@/components/ui/AppSkeleton.vue'
import { useCurrentCompany } from '@/composables/useCurrentCompany'
import NewAppointmentModal from '@/modules/appointments/components/NewAppointmentModal.vue'
import { STATUS_TONE } from '@/modules/appointments/status'
import { dashboardApi } from '@/modules/dashboard/api'
import WeeklyTrendChart from '@/modules/dashboard/components/WeeklyTrendChart.vue'
import type { Dashboard } from '@/modules/dashboard/types'
import {
  compare,
  formatCurrency,
  formatLongDate,
  formatNumber,
  formatPercent,
  formatRelative,
  formatShortDate,
  todayIn,
} from '@/services/format'
import { isApiError } from '@/services/http'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const { company, can } = useCurrentCompany()

const data = ref<Dashboard | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const bookingOpen = ref(false)
let controller: AbortController | null = null

async function load(): Promise<void> {
  if (!company.value) return
  controller?.abort()
  controller = new AbortController()
  loading.value = true
  error.value = null

  try {
    data.value = await dashboardApi.get(company.value.id, controller.signal)
  } catch (caught) {
    if (caught instanceof DOMException && caught.name === 'AbortError') return
    error.value = isApiError(caught) ? caught.message : 'Não foi possível carregar o painel.'
  } finally {
    loading.value = false
  }
}

watch(() => company.value?.id, load, { immediate: true })
onBeforeUnmount(() => controller?.abort())

const firstName = computed(() => auth.user?.name.split(' ')[0] ?? '')
const timezone = computed(() => company.value?.timezone ?? 'America/Sao_Paulo')
const todayLabel = computed(() => formatLongDate(new Date().toISOString(), timezone.value))
const today = computed(() => todayIn(timezone.value))

const current = computed(() => data.value?.period.current)
const previous = computed(() => data.value?.period.previous)
const showFinancials = computed(() => can('view_financials') && current.value?.revenue_cents !== null)

const revenueChange = computed(() =>
  current.value && previous.value ? compare(current.value.revenue_cents ?? 0, previous.value.revenue_cents ?? 0) : null,
)
const bookedChange = computed(() =>
  current.value && previous.value ? compare(current.value.booked, previous.value.booked) : null,
)
const attendanceDelta = computed(() => {
  const now = current.value?.attendance_rate
  const before = previous.value?.attendance_rate
  return now == null || before == null ? null : Math.round((now - before) * 100)
})

function changeLabel(change: ReturnType<typeof compare> | null): string {
  if (!change) return ''
  if (change.ratio === null) return 'sem base anterior'
  const sign = change.ratio > 0 ? '+' : ''
  return `${sign}${formatPercent(change.ratio)} vs. 30 dias anteriores`
}

function changeClass(direction: 'up' | 'down' | 'flat' | undefined): string {
  return direction === 'up' ? 'text-success' : direction === 'down' ? 'text-danger' : 'text-text-muted'
}

function dayLabel(date: string): string {
  return date === today.value ? 'Hoje' : formatShortDate(date)
}

const hasHistory = computed(() => (data.value?.weekly_trend ?? []).some((w) => w.booked > 0))
</script>

<template>
  <div class="flex flex-col gap-8">
    <!-- 1. Welcome + primary action -->
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-label text-text-muted first-letter:uppercase">{{ todayLabel }}</p>
        <h1 class="mt-1 text-page-title">Olá, {{ firstName }}</h1>
      </div>
      <AppButton v-if="can('manage_appointments')" @click="bookingOpen = true">
        <template #icon>
          <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <path d="M12 5v14M5 12h14" />
          </svg>
        </template>
        Novo agendamento
      </AppButton>
    </header>

    <!-- Error state -->
    <div
      v-if="error && !data"
      class="flex flex-col items-start gap-3 rounded-card border border-danger/30 bg-danger/5 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
      role="alert"
    >
      <div>
        <p class="font-medium text-danger">Não foi possível carregar o painel</p>
        <p class="text-label text-text-secondary">{{ error }}</p>
      </div>
      <AppButton variant="secondary" size="sm" :loading="loading" @click="load">Tentar novamente</AppButton>
    </div>

    <template v-else>
      <!-- 2. Operational indicators: one strip, three readings -->
      <section aria-labelledby="dash-today" class="rounded-card border border-border bg-card">
        <h2 id="dash-today" class="sr-only">Hoje</h2>
        <dl class="grid divide-y divide-border sm:grid-cols-3 sm:divide-x sm:divide-y-0">
          <div class="px-5 py-4">
            <dt class="text-label text-text-secondary">Agendamentos hoje</dt>
            <dd class="mt-1">
              <AppSkeleton v-if="loading && !data" :lines="2" />
              <template v-else-if="data">
                <span class="font-mono text-[28px] leading-9 font-medium">{{ formatNumber(data.today.total) }}</span>
                <p class="text-support text-text-muted">
                  {{ data.today.remaining }} a atender<template v-if="data.today.in_progress"> · {{ data.today.in_progress }} em atendimento</template>
                </p>
              </template>
            </dd>
          </div>
          <div class="px-5 py-4">
            <dt class="text-label text-text-secondary">Aguardando confirmação</dt>
            <dd class="mt-1">
              <AppSkeleton v-if="loading && !data" :lines="2" />
              <template v-else-if="data">
                <span
                  class="font-mono text-[28px] leading-9 font-medium"
                  :class="{ 'text-warning': data.today.awaiting_confirmation > 0 }"
                >
                  {{ formatNumber(data.today.awaiting_confirmation) }}
                </span>
                <p class="text-support text-text-muted">próximos agendamentos não confirmados</p>
              </template>
            </dd>
          </div>
          <div class="px-5 py-4">
            <dt class="text-label text-text-secondary">Concluídos hoje</dt>
            <dd class="mt-1">
              <AppSkeleton v-if="loading && !data" :lines="2" />
              <template v-else-if="data">
                <span class="font-mono text-[28px] leading-9 font-medium">{{ formatNumber(data.today.completed) }}</span>
                <span class="font-mono text-body text-text-muted"> / {{ data.today.total }}</span>
                <p class="text-support text-text-muted">
                  {{ data.today.no_show }} não compareceram · {{ data.today.cancelled }} cancelados
                </p>
              </template>
            </dd>
          </div>
        </dl>
      </section>

      <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <!-- 4. Upcoming appointments -->
        <section aria-labelledby="dash-upcoming" class="rounded-card border border-border bg-card">
          <header class="flex items-center justify-between border-b border-border px-5 py-4">
            <h2 id="dash-upcoming" class="text-card-title">Próximos agendamentos</h2>
          </header>
          <div v-if="loading && !data" class="flex flex-col gap-4 p-5"><AppSkeleton v-for="i in 4" :key="i" :lines="2" /></div>
          <p v-else-if="!data?.upcoming.length" class="px-5 py-10 text-center text-text-secondary">
            Nenhum agendamento futuro.
            <template v-if="can('manage_appointments')"> Use “Novo agendamento” para marcar o próximo atendimento.</template>
          </p>
          <ul v-else class="divide-y divide-border">
            <li
              v-for="item in data.upcoming"
              :key="item.id"
              class="grid grid-cols-[64px_minmax(0,1fr)] gap-x-4 gap-y-1 px-5 py-3 sm:grid-cols-[72px_minmax(0,1fr)_auto] sm:items-center"
            >
              <div class="row-span-2 sm:row-span-1">
                <p class="font-mono text-body font-medium">{{ item.local.start }}</p>
                <p class="text-support text-text-muted">{{ dayLabel(item.local.date) }}</p>
              </div>
              <div class="min-w-0">
                <p class="truncate font-medium">{{ item.customer?.name }}</p>
                <p class="flex min-w-0 items-center gap-1.5 text-label text-text-secondary">
                  <span class="truncate">{{ item.service.name }}</span>
                  <template v-if="item.professional">
                    <span aria-hidden="true">·</span>
                    <span class="size-2 shrink-0 rounded-full" :style="{ backgroundColor: item.professional.color }" aria-hidden="true" />
                    <span class="truncate">{{ item.professional.name }}</span>
                  </template>
                </p>
              </div>
              <AppBadge :tone="STATUS_TONE[item.status]" dot class="justify-self-start sm:justify-self-end">
                {{ item.status_label }}
              </AppBadge>
            </li>
          </ul>
        </section>

        <!-- 3. Managerial indicators (last 30 days) -->
        <section aria-labelledby="dash-period" class="flex flex-col rounded-card border border-border bg-card">
          <header class="border-b border-border px-5 py-4">
            <h2 id="dash-period" class="text-card-title">Últimos 30 dias</h2>
            <p v-if="data" class="text-support text-text-muted">
              {{ formatShortDate(data.period.from) }} a {{ formatShortDate(data.period.to) }}
            </p>
          </header>
          <div v-if="loading && !data" class="flex flex-col gap-5 p-5"><AppSkeleton v-for="i in 3" :key="i" :lines="2" /></div>
          <div v-else-if="current" class="flex flex-col gap-5 p-5">
            <div v-if="showFinancials">
              <p class="text-label text-text-secondary">Receita realizada</p>
              <p class="mt-0.5 font-mono text-section-title font-medium">{{ formatCurrency(current.revenue_cents ?? 0) }}</p>
              <p class="text-support" :class="changeClass(revenueChange?.direction)">{{ changeLabel(revenueChange) }}</p>
            </div>

            <div>
              <div class="flex items-baseline justify-between gap-3">
                <p class="text-label text-text-secondary">Taxa de comparecimento</p>
                <p class="font-mono text-card-title font-medium">
                  {{ current.attendance_rate === null ? '—' : formatPercent(current.attendance_rate) }}
                </p>
              </div>
              <div
                class="mt-2 h-1.5 overflow-hidden rounded-full bg-elevated"
                role="meter"
                aria-label="Taxa de comparecimento"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-valuenow="current.attendance_rate === null ? undefined : Math.round(current.attendance_rate * 100)"
              >
                <div class="h-full rounded-full bg-success" :style="{ width: `${(current.attendance_rate ?? 0) * 100}%` }" />
              </div>
              <p class="mt-1.5 text-support text-text-muted">
                <template v-if="current.attendance_rate === null">Sem atendimentos finalizados no período.</template>
                <template v-else>
                  {{ current.completed }} concluídos · {{ current.no_show }} faltas
                  <span v-if="attendanceDelta !== null" :class="changeClass(attendanceDelta > 0 ? 'up' : attendanceDelta < 0 ? 'down' : 'flat')">
                    ({{ attendanceDelta > 0 ? '+' : '' }}{{ attendanceDelta }} p.p.)
                  </span>
                </template>
              </p>
            </div>

            <div class="flex items-baseline justify-between gap-3">
              <div>
                <p class="text-label text-text-secondary">Agendamentos</p>
                <p class="text-support" :class="changeClass(bookedChange?.direction)">{{ changeLabel(bookedChange) }}</p>
              </div>
              <p class="font-mono text-card-title font-medium">{{ formatNumber(current.booked) }}</p>
            </div>

            <div class="border-t border-border pt-5">
              <WeeklyTrendChart v-if="hasHistory && data" :weeks="data.weekly_trend" />
              <p v-else class="text-label text-text-muted">A tendência semanal aparece após os primeiros atendimentos.</p>
            </div>
          </div>
        </section>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <!-- 5. Recent activity -->
        <section aria-labelledby="dash-activity" class="rounded-card border border-border bg-card">
          <header class="border-b border-border px-5 py-4">
            <h2 id="dash-activity" class="text-card-title">Atividades recentes</h2>
          </header>
          <div v-if="loading && !data" class="flex flex-col gap-4 p-5"><AppSkeleton v-for="i in 4" :key="i" :lines="2" /></div>
          <p v-else-if="!data?.recent_activity.length" class="px-5 py-10 text-center text-text-secondary">
            As alterações em agendamentos aparecerão aqui.
          </p>
          <ol v-else class="flex flex-col gap-4 px-5 py-4">
            <li v-for="event in data.recent_activity" :key="event.id" class="flex gap-3">
              <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-text-muted" aria-hidden="true" />
              <div class="min-w-0 flex-1">
                <p class="text-body">
                  <span class="font-medium">{{ event.label }}</span>
                  <span class="text-text-secondary"> · {{ event.appointment.customer }}, {{ event.appointment.service }}</span>
                </p>
                <p class="text-support text-text-muted">
                  {{ formatRelative(event.created_at) }}<template v-if="event.actor"> · por {{ event.actor }}</template>
                  <template v-if="event.reason"> · “{{ event.reason }}”</template>
                </p>
              </div>
            </li>
          </ol>
        </section>

        <!-- 6. Complementary performance -->
        <section aria-labelledby="dash-performance" class="rounded-card border border-border bg-card">
          <header class="border-b border-border px-5 py-4">
            <h2 id="dash-performance" class="text-card-title">Desempenho nos últimos 30 dias</h2>
          </header>
          <div v-if="loading && !data" class="flex flex-col gap-4 p-5"><AppSkeleton v-for="i in 4" :key="i" /></div>
          <p
            v-else-if="!data?.top_services.length && !data?.top_professionals.length"
            class="px-5 py-10 text-center text-text-secondary"
          >
            Ainda não há atendimentos concluídos no período.
          </p>
          <div v-else class="grid gap-6 px-5 py-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
            <div>
              <h3 class="text-label font-medium text-text-secondary">Serviços mais realizados</h3>
              <ol class="mt-2 flex flex-col gap-2">
                <li v-for="service in data.top_services" :key="service.id" class="flex items-center gap-2 text-body">
                  <span class="size-2 shrink-0 rounded-full" :style="{ backgroundColor: service.color }" aria-hidden="true" />
                  <span class="min-w-0 flex-1 truncate">{{ service.name }}</span>
                  <span class="font-mono text-label text-text-secondary">{{ service.completed }}</span>
                  <span v-if="service.revenue_cents !== null" class="w-24 text-right font-mono text-label text-text-muted">
                    {{ formatCurrency(service.revenue_cents) }}
                  </span>
                </li>
              </ol>
            </div>
            <div>
              <h3 class="text-label font-medium text-text-secondary">Profissionais</h3>
              <ol class="mt-2 flex flex-col gap-2">
                <li v-for="pro in data.top_professionals" :key="pro.id" class="flex items-center gap-2 text-body">
                  <span class="size-2 shrink-0 rounded-full" :style="{ backgroundColor: pro.color }" aria-hidden="true" />
                  <span class="min-w-0 flex-1 truncate">{{ pro.name }}</span>
                  <span class="font-mono text-label text-text-secondary">{{ pro.completed }}</span>
                  <span class="w-12 text-right font-mono text-label text-text-muted" :title="`${pro.no_show} faltas`">
                    {{ formatPercent(pro.attendance_rate) }}
                  </span>
                </li>
              </ol>
              <p class="mt-2 text-support text-text-muted">Concluídos · comparecimento</p>
            </div>
          </div>
        </section>
      </div>

      <p v-if="error && data" class="text-support text-danger" role="status">
        Falha ao atualizar: {{ error }}
      </p>
    </template>

    <NewAppointmentModal v-if="can('manage_appointments')" v-model:open="bookingOpen" @created="load" />
  </div>
</template>

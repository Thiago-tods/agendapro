<script setup lang="ts">
import { computed } from 'vue'
import AppointmentEvent from '@/components/calendar/AppointmentEvent.vue'
import { minutesLabel, toMinutes } from '@/modules/appointments/calendar/dates'
import { fitsIn, layoutColumn, type Range } from '@/modules/appointments/calendar/layout'
import type { Appointment } from '@/types/api'

export interface CalendarColumn {
  key: string
  date: string
  label: string
  sublabel?: string
  color?: string
  /** Professional the column belongs to (day view), used to prefill new bookings. */
  professionalId?: string
  appointments: Appointment[]
  /** Open hours resolved by the backend; everything else in the grid is unavailable. */
  open: Range[]
  blocked: { start: number; end: number; reason: string | null }[]
  isToday?: boolean
}

const props = defineProps<{
  columns: CalendarColumn[]
  bounds: Range
  /** Current minute when one of the columns is today. */
  nowMinute?: number
  canCreate: boolean
  showProfessional: boolean
  /** Minimum width per column; columns scroll horizontally inside the grid when needed. */
  minColumnWidth?: number
}>()

const emit = defineEmits<{
  open: [id: string]
  create: [slot: { date: string; minute: number; professionalId?: string }]
}>()

/** Vertical scale: 30 minutes = 36px keeps short services readable. */
const PX_PER_MINUTE = 1.2
const SLOT_MINUTES = 30

const totalMinutes = computed(() => props.bounds[1] - props.bounds[0])
const height = computed(() => totalMinutes.value * PX_PER_MINUTE)
const y = (minute: number) => (minute - props.bounds[0]) * PX_PER_MINUTE

const hours = computed(() => {
  const list: number[] = []
  for (let m = Math.ceil(props.bounds[0] / 60) * 60; m < props.bounds[1]; m += 60) list.push(m)
  return list
})

function closedRanges(open: Range[]): Range[] {
  const result: Range[] = []
  let cursor = props.bounds[0]
  for (const [s, e] of [...open].sort((a, b) => a[0] - b[0])) {
    if (s > cursor) result.push([cursor, Math.min(s, props.bounds[1])])
    cursor = Math.max(cursor, e)
  }
  if (cursor < props.bounds[1]) result.push([cursor, props.bounds[1]])
  return result.filter(([s, e]) => e > s && s < props.bounds[1])
}

const rendered = computed(() =>
  props.columns.map((column) => {
    const items = column.appointments.map((a) => {
      const start = toMinutes(a.local.start)
      const end = toMinutes(a.local.end)
      return {
        appointment: a,
        start,
        end,
        occupied: end + a.service.buffer_minutes,
      }
    })
    const layout = layoutColumn(
      items.map((i) => ({
        id: i.appointment.id,
        start: i.start,
        end: i.occupied,
      })),
    )

    const blocked = column.blocked
      .map((b) => ({
        ...b,
        start: Math.max(b.start, props.bounds[0]),
        end: Math.min(b.end, props.bounds[1]),
      }))
      .filter((b) => b.end > b.start)

    // Free half-hour slots inside open hours, outside blocks: entry points for new bookings.
    const slots: number[] = []
    if (props.canCreate) {
      for (let m = props.bounds[0]; m < props.bounds[1]; m += SLOT_MINUTES) {
        const insideOpen = fitsIn(column.open, m, m + SLOT_MINUTES)
        const insideBlock = blocked.some((b) => m < b.end && m + SLOT_MINUTES > b.start)
        if (insideOpen && !insideBlock) slots.push(m)
      }
    }

    return {
      column,
      closed: closedRanges(column.open),
      blocked,
      slots,
      events: items.map((i) => {
        const place = layout.get(i.appointment.id) ?? { lane: 0, lanes: 1 }
        return {
          appointment: i.appointment,
          top: y(i.start),
          height: (i.end - i.start) * PX_PER_MINUTE,
          bufferHeight: (i.occupied - i.end) * PX_PER_MINUTE,
          left: place.lane / place.lanes,
          width: 1 / place.lanes,
        }
      }),
    }
  }),
)

function slotLabel(column: CalendarColumn, minute: number): string {
  return `Novo agendamento às ${minutesLabel(minute)}, ${column.label}${column.sublabel ? ` ${column.sublabel}` : ''}`
}
</script>

<template>
  <div class="overflow-x-auto rounded-card border border-border bg-card">
    <div
      class="grid"
      :style="{
        gridTemplateColumns: `56px repeat(${columns.length}, minmax(${minColumnWidth ?? 0}px, 1fr))`,
        minWidth: minColumnWidth ? `${56 + columns.length * minColumnWidth}px` : undefined,
      }"
    >
      <!-- Header row -->
      <div class="sticky left-0 z-20 border-b border-border bg-card" />
      <div
        v-for="item in rendered"
        :key="`h-${item.column.key}`"
        class="flex min-w-0 items-center gap-2 border-b border-l border-border px-3 py-2.5"
        :class="{ 'bg-primary-soft/40': item.column.isToday }"
      >
        <span
          v-if="item.column.color"
          class="size-2 shrink-0 rounded-full"
          :style="{ backgroundColor: item.column.color }"
          aria-hidden="true"
        />
        <span class="min-w-0">
          <span class="block truncate text-label font-medium first-letter:uppercase">{{ item.column.label }}</span>
          <span v-if="item.column.sublabel" class="block truncate text-support text-text-muted">{{
            item.column.sublabel
          }}</span>
        </span>
      </div>

      <!-- Time axis -->
      <div class="sticky left-0 z-20 bg-card" :style="{ height: `${height}px` }">
        <span
          v-for="hour in hours"
          :key="hour"
          class="absolute right-2 font-mono text-[11px] leading-none text-text-muted"
          :style="{
            top: `${hour === bounds[0] ? y(hour) + 2 : y(hour) - 5}px`,
          }"
          aria-hidden="true"
        >
          {{ minutesLabel(hour) }}
        </span>
      </div>

      <!-- Columns -->
      <div
        v-for="item in rendered"
        :key="item.column.key"
        class="relative border-l border-border"
        :style="{ height: `${height}px` }"
        role="group"
        :aria-label="`${item.column.label}${item.column.sublabel ? ` ${item.column.sublabel}` : ''}: ${item.events.length} agendamentos`"
      >
        <!-- Hour lines -->
        <div
          v-for="hour in hours"
          :key="hour"
          class="pointer-events-none absolute inset-x-0 border-t border-border/60"
          :style="{ top: `${y(hour)}px` }"
          aria-hidden="true"
        />

        <!-- Unavailable time (outside open hours) -->
        <div
          v-for="([s, e], i) in item.closed"
          :key="`c-${i}`"
          class="pointer-events-none absolute inset-x-0 bg-[repeating-linear-gradient(135deg,var(--color-surface)_0_6px,transparent_6px_12px)] bg-background/60"
          :style="{ top: `${y(s)}px`, height: `${(e - s) * PX_PER_MINUTE}px` }"
          aria-hidden="true"
        />

        <!-- Blocked periods -->
        <div
          v-for="(b, i) in item.blocked"
          :key="`b-${i}`"
          class="absolute inset-x-0 flex items-start overflow-hidden border-y border-danger/30 bg-danger/10 px-2 py-1"
          :style="{
            top: `${y(b.start)}px`,
            height: `${(b.end - b.start) * PX_PER_MINUTE}px`,
          }"
          :title="`Bloqueado${b.reason ? `: ${b.reason}` : ''} (${minutesLabel(b.start)}–${minutesLabel(b.end)})`"
        >
          <span class="truncate text-support text-danger">
            Bloqueado<template v-if="b.reason"> · {{ b.reason }}</template>
          </span>
        </div>

        <!-- Free slots (keyboard and pointer entry points for booking) -->
        <button
          v-for="minute in item.slots"
          :key="`s-${minute}`"
          type="button"
          class="absolute inset-x-0 rounded-control transition-colors duration-150 hover:bg-primary-soft/40 focus-visible:bg-primary-soft/60 focus-visible:outline-offset-[-2px]"
          :style="{
            top: `${y(minute)}px`,
            height: `${SLOT_MINUTES * PX_PER_MINUTE}px`,
          }"
          :aria-label="slotLabel(item.column, minute)"
          @click="
            emit('create', {
              date: item.column.date,
              minute,
              professionalId: item.column.professionalId,
            })
          "
        />

        <AppointmentEvent
          v-for="event in item.events"
          :key="event.appointment.id"
          v-bind="event"
          :show-professional="showProfessional"
          @open="emit('open', $event)"
        />

        <!-- Current time -->
        <div
          v-if="item.column.isToday && nowMinute !== undefined && nowMinute >= bounds[0] && nowMinute <= bounds[1]"
          class="pointer-events-none absolute inset-x-0 z-10 border-t-2 border-danger"
          :style="{ top: `${y(nowMinute)}px` }"
          aria-hidden="true"
        >
          <span class="absolute -top-[5px] -left-[5px] size-2 rounded-full bg-danger" />
        </div>
      </div>
    </div>
  </div>
</template>

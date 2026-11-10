<script setup lang="ts">
import { computed } from 'vue'
import { formatDate, isSameMonth, monthMatrix } from '@/modules/appointments/calendar/dates'
import type { Appointment } from '@/types/api'

/**
 * Month overview. Each day lists a few appointments in time order and a "+N" link that
 * opens the day view, so dense days stay readable.
 */
const props = defineProps<{
  date: string
  today: string
  appointments: Appointment[]
  /** Dates (YYYY-MM-DD) on which the company is closed. */
  closedDates: Set<string>
}>()

const emit = defineEmits<{ open: [id: string]; day: [date: string] }>()

const VISIBLE_PER_DAY = 3
const WEEKDAYS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']

const byDate = computed(() => {
  const map = new Map<string, Appointment[]>()
  for (const appointment of props.appointments) {
    const list = map.get(appointment.local.date) ?? []
    list.push(appointment)
    map.set(appointment.local.date, list)
  }
  map.forEach((list) => list.sort((a, b) => a.local.start.localeCompare(b.local.start)))
  return map
})

const weeks = computed(() => monthMatrix(props.date))
</script>

<template>
  <div class="overflow-hidden rounded-card border border-border bg-card">
    <div class="grid grid-cols-7 border-b border-border" aria-hidden="true">
      <span v-for="day in WEEKDAYS" :key="day" class="px-2 py-2 text-center text-support font-medium text-text-muted">{{
        day
      }}</span>
    </div>
    <div class="grid grid-cols-7">
      <div
        v-for="day in weeks.flat()"
        :key="day"
        class="flex min-h-[92px] min-w-0 flex-col gap-1 border-b border-l border-border p-1.5 first:border-l-0 sm:min-h-[116px] [&:nth-child(7n+1)]:border-l-0"
        :class="[
          isSameMonth(day, date) ? '' : 'bg-background/40 text-text-muted',
          closedDates.has(day)
            ? 'bg-[repeating-linear-gradient(135deg,var(--color-surface)_0_6px,transparent_6px_12px)]'
            : '',
        ]"
      >
        <button
          type="button"
          class="self-start rounded-control px-1.5 py-0.5 font-mono text-label transition-colors duration-150 hover:bg-elevated"
          :class="day === today ? 'bg-primary font-medium text-primary-text hover:bg-primary-hover' : ''"
          :aria-label="`Ver ${formatDate(day, { weekday: 'long', day: 'numeric', month: 'long' })}: ${byDate.get(day)?.length ?? 0} agendamentos`"
          @click="emit('day', day)"
        >
          {{ Number(day.slice(8)) }}
        </button>

        <ul class="flex min-w-0 flex-col gap-0.5">
          <li v-for="item in (byDate.get(day) ?? []).slice(0, VISIBLE_PER_DAY)" :key="item.id" class="min-w-0">
            <button
              type="button"
              class="flex w-full min-w-0 items-center gap-1 rounded-control px-1 py-0.5 text-left text-support hover:bg-elevated"
              :class="{
                'line-through opacity-60': item.status === 'cancelled',
                'opacity-70': item.status === 'completed' || item.status === 'no_show',
              }"
              :title="`${item.local.start} · ${item.customer?.name} · ${item.service.name} · ${item.status_label}`"
              :aria-label="`${item.local.start}, ${item.customer?.name}, ${item.service.name}, ${item.status_label}`"
              @click="emit('open', item.id)"
            >
              <span
                class="size-1.5 shrink-0 rounded-full"
                :style="{ backgroundColor: item.professional?.color }"
                aria-hidden="true"
              />
              <span class="hidden font-mono text-text-muted sm:inline">{{ item.local.start }}</span>
              <span class="truncate">{{ item.customer?.name }}</span>
            </button>
          </li>
        </ul>
        <button
          v-if="(byDate.get(day)?.length ?? 0) > VISIBLE_PER_DAY"
          type="button"
          class="self-start rounded-control px-1 text-support font-medium text-primary-hover hover:underline"
          @click="emit('day', day)"
        >
          +{{ (byDate.get(day)?.length ?? 0) - VISIBLE_PER_DAY }} mais
        </button>
      </div>
    </div>
  </div>
</template>

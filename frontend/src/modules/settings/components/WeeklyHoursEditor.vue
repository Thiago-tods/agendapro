<script setup lang="ts">
import { computed } from 'vue'
import { WEEKDAYS } from '@/services/forms'
import type { WeeklyPeriod } from '@/types/api'

/**
 * Edits weekly periods (several per day, e.g. morning and afternoon). Overlaps and invalid
 * ranges are reported by the API; this only shapes the data.
 */
const periods = defineModel<WeeklyPeriod[]>({ required: true })
defineProps<{ disabled?: boolean; error?: string }>()

// Monday first, as in the agenda.
const ORDER = [1, 2, 3, 4, 5, 6, 0]

const byDay = computed(() =>
  ORDER.map((weekday) => ({
    weekday,
    label: WEEKDAYS[weekday],
    items: periods.value
      .map((period, index) => ({ period, index }))
      .filter(({ period }) => period.weekday === weekday)
      .sort((a, b) => a.period.start_time.localeCompare(b.period.start_time)),
  })),
)

function toggleDay(weekday: number, open: boolean): void {
  periods.value = open
    ? [...periods.value, { weekday, start_time: '09:00', end_time: '18:00' }]
    : periods.value.filter((p) => p.weekday !== weekday)
}

function addPeriod(weekday: number): void {
  const items = byDay.value.find((d) => d.weekday === weekday)?.items ?? []
  const last = items[items.length - 1]?.period
  const start = last?.end_time && last.end_time < '22:00' ? last.end_time : '13:00'
  const end = `${String(Math.min(Number(start.slice(0, 2)) + 4, 23)).padStart(2, '0')}:${start.slice(3)}`
  periods.value = [...periods.value, { weekday, start_time: start, end_time: end }]
}

function update(index: number, field: 'start_time' | 'end_time', value: string): void {
  periods.value = periods.value.map((p, i) => (i === index ? { ...p, [field]: value } : p))
}

function remove(index: number): void {
  periods.value = periods.value.filter((_, i) => i !== index)
}

const timeInput =
  'h-9 w-[104px] rounded-input border border-border bg-surface px-2 font-mono text-label text-text-primary focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/35 focus-visible:outline-none disabled:opacity-50'
</script>

<template>
  <div>
    <p v-if="error" class="mb-3 text-label text-danger" role="alert">
      {{ error }}
    </p>
    <ul class="@container divide-y divide-border">
      <li v-for="day in byDay" :key="day.weekday" class="flex flex-col gap-2 py-3 @lg:flex-row @lg:items-start @lg:gap-4">
        <label class="flex h-9 w-32 shrink-0 items-center gap-2 text-body font-medium">
          <input
            type="checkbox"
            class="size-4 accent-(--color-primary)"
            :checked="day.items.length > 0"
            :disabled="disabled"
            @change="toggleDay(day.weekday, ($event.target as HTMLInputElement).checked)"
          />
          {{ day.label }}
        </label>

        <p v-if="day.items.length === 0" class="flex h-9 items-center text-label text-text-muted">Fechado</p>

        <div v-else class="flex flex-1 flex-col gap-2">
          <div v-for="{ period, index } in day.items" :key="index" class="flex items-center gap-2">
            <input
              type="time"
              :class="timeInput"
              :value="period.start_time"
              :disabled="disabled"
              :aria-label="`${day.label}: início`"
              @change="update(index, 'start_time', ($event.target as HTMLInputElement).value)"
            />
            <span class="text-text-muted" aria-hidden="true">–</span>
            <input
              type="time"
              :class="timeInput"
              :value="period.end_time"
              :disabled="disabled"
              :aria-label="`${day.label}: fim`"
              @change="update(index, 'end_time', ($event.target as HTMLInputElement).value)"
            />
            <button
              v-if="!disabled"
              type="button"
              class="inline-flex size-8 items-center justify-center rounded-input text-text-muted hover:bg-elevated hover:text-danger"
              :aria-label="`Remover período ${period.start_time}–${period.end_time} de ${day.label}`"
              @click="remove(index)"
            >
              <svg
                class="size-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                aria-hidden="true"
              >
                <path d="M6 6l12 12M18 6 6 18" />
              </svg>
            </button>
          </div>
          <button
            v-if="!disabled"
            type="button"
            class="w-fit text-label font-medium text-primary-hover hover:underline"
            @click="addPeriod(day.weekday)"
          >
            + Adicionar intervalo
          </button>
        </div>
      </li>
    </ul>
  </div>
</template>

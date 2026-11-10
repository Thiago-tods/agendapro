<script setup lang="ts">
import { computed } from 'vue'
import { formatNumber, formatShortDate } from '@/services/format'
import type { WeeklyPoint } from '../types'

const props = defineProps<{ weeks: WeeklyPoint[] }>()

const max = computed(() => Math.max(1, ...props.weeks.map((w) => w.booked)))

const summary = computed(() =>
  props.weeks
    .map((w) => `Semana de ${formatShortDate(w.week_start)}: ${w.booked} agendados, ${w.completed} concluídos`)
    .join('; '),
)
</script>

<template>
  <figure class="flex flex-col gap-3">
    <figcaption class="flex items-center justify-between gap-3">
      <span class="text-label font-medium text-text-secondary">Atendimentos por semana</span>
      <span class="flex items-center gap-3 text-support text-text-muted">
        <span class="flex items-center gap-1.5"><span class="size-2 rounded-[2px] bg-elevated ring-1 ring-border" />Agendados</span>
        <span class="flex items-center gap-1.5"><span class="size-2 rounded-[2px] bg-primary" />Concluídos</span>
      </span>
    </figcaption>

    <!-- Bars are a visual summary; the full data is available to screen readers below. -->
    <div class="flex h-28 items-end gap-1.5" aria-hidden="true">
      <div
        v-for="week in weeks"
        :key="week.week_start"
        class="group relative flex h-full flex-1 flex-col justify-end"
        :title="`${formatShortDate(week.week_start)}: ${formatNumber(week.booked)} agendados, ${formatNumber(week.completed)} concluídos`"
      >
        <div class="relative w-full rounded-t-[3px] bg-elevated" :style="{ height: `${(week.booked / max) * 100}%` }">
          <div
            class="absolute inset-x-0 bottom-0 rounded-t-[3px] bg-primary"
            :style="{ height: week.booked ? `${(week.completed / week.booked) * 100}%` : '0%' }"
          />
        </div>
      </div>
    </div>
    <div class="flex gap-1.5 font-mono text-[11px] text-text-muted" aria-hidden="true">
      <span v-for="(week, i) in weeks" :key="week.week_start" class="flex-1 text-center">
        {{ i % 2 === weeks.length % 2 ? '' : formatShortDate(week.week_start) }}
      </span>
    </div>
    <p class="sr-only">{{ summary }}</p>
  </figure>
</template>

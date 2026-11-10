<script setup lang="ts">
import type { CalendarView } from '@/modules/appointments/calendar/dates'

const view = defineModel<CalendarView>('view', { required: true })

defineProps<{ title: string; loading?: boolean; isToday: boolean }>()
defineEmits<{ previous: []; next: []; today: [] }>()

const views: { value: CalendarView; label: string }[] = [
  { value: 'day', label: 'Dia' },
  { value: 'week', label: 'Semana' },
  { value: 'month', label: 'Mês' },
]

const navButton =
  'inline-flex size-9 items-center justify-center rounded-input border border-border text-text-secondary transition-colors duration-150 hover:bg-elevated hover:text-text-primary'
</script>

<template>
  <div class="flex flex-wrap items-center gap-3">
    <div class="flex items-center gap-1.5">
      <button type="button" :class="navButton" aria-label="Período anterior" @click="$emit('previous')">
        <svg
          class="size-4"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          aria-hidden="true"
        >
          <path d="m15 18-6-6 6-6" />
        </svg>
      </button>
      <button type="button" :class="navButton" aria-label="Próximo período" @click="$emit('next')">
        <svg
          class="size-4"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          aria-hidden="true"
        >
          <path d="m9 18 6-6-6-6" />
        </svg>
      </button>
      <button
        type="button"
        class="h-9 rounded-input border border-border px-3 text-label font-medium text-text-secondary transition-colors duration-150 hover:bg-elevated hover:text-text-primary disabled:cursor-default disabled:opacity-50 disabled:hover:bg-transparent"
        :disabled="isToday"
        @click="$emit('today')"
      >
        Hoje
      </button>
    </div>

    <h2
      class="order-first w-full min-w-0 truncate text-section-title first-letter:uppercase sm:order-none sm:w-auto sm:flex-1"
      aria-live="polite"
    >
      {{ title }}
      <span
        v-if="loading"
        class="ml-2 inline-block size-3 animate-spin rounded-full border-2 border-text-muted border-r-transparent align-middle motion-reduce:animate-none"
        aria-label="Carregando"
      />
    </h2>

    <div class="inline-flex rounded-input border border-border p-0.5" role="radiogroup" aria-label="Visualização">
      <button
        v-for="option in views"
        :key="option.value"
        type="button"
        role="radio"
        :aria-checked="view === option.value"
        class="h-8 rounded-control px-3 text-label font-medium transition-colors duration-150"
        :class="
          view === option.value ? 'bg-primary-soft text-text-primary' : 'text-text-secondary hover:text-text-primary'
        "
        @click="view = option.value"
      >
        {{ option.label }}
      </button>
    </div>
  </div>
</template>

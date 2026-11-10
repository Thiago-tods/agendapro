<script setup lang="ts">
import { computed } from 'vue'
import { formatNumber } from '@/services/format'

const props = defineProps<{
  page: number
  lastPage: number
  total: number
  perPage: number
  label?: string
}>()
const emit = defineEmits<{ change: [page: number] }>()

const range = computed(() => {
  const first = props.total === 0 ? 0 : (props.page - 1) * props.perPage + 1
  return { first, last: Math.min(props.page * props.perPage, props.total) }
})

const button =
  'inline-flex h-8 items-center gap-1 rounded-input border border-border px-3 text-label font-medium text-text-secondary transition-colors duration-150 hover:bg-elevated hover:text-text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent'
</script>

<template>
  <nav class="flex flex-wrap items-center justify-between gap-3" :aria-label="label ?? 'Paginação'">
    <p class="text-label text-text-muted" aria-live="polite">
      <span class="font-mono">{{ formatNumber(range.first) }}–{{ formatNumber(range.last) }}</span>
      de <span class="font-mono">{{ formatNumber(total) }}</span>
    </p>
    <div v-if="lastPage > 1" class="flex items-center gap-2">
      <button type="button" :class="button" :disabled="page <= 1" @click="emit('change', page - 1)">Anterior</button>
      <span class="font-mono text-label text-text-secondary">{{ page }}/{{ lastPage }}</span>
      <button type="button" :class="button" :disabled="page >= lastPage" @click="emit('change', page + 1)">
        Próxima
      </button>
    </div>
  </nav>
</template>

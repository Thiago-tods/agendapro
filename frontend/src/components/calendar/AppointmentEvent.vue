<script setup lang="ts">
import { computed } from 'vue'
import type { Appointment } from '@/types/api'

/**
 * One appointment block in the time grid. Height is proportional to the service duration;
 * the buffer is drawn as a hatched tail. Content adapts to the available height, and the full
 * description is always available to assistive technology and as a tooltip.
 */
const props = defineProps<{
  appointment: Appointment
  top: number
  height: number
  bufferHeight: number
  /** Horizontal placement inside the column, as fractions. */
  left: number
  width: number
  showProfessional: boolean
}>()

defineEmits<{ open: [id: string] }>()

const color = computed(() => props.appointment.professional?.color ?? 'var(--color-primary)')

/** Narrow blocks (side-by-side overlaps) switch to a minimal layout to stay legible. */
const narrow = computed(() => props.width < 0.5)
const tier = computed(() =>
  narrow.value ? 'narrow' : props.height >= 54 ? 'full' : props.height >= 32 ? 'compact' : 'tiny',
)
const firstName = computed(
  () => props.appointment.customer?.name.replace(/^(Sra?\.|Srta\.|Dr\.|Dra\.)\s+/, '').split(' ')[0] ?? '',
)

/** Shape cues for the status, so it never depends on color alone (the full label is in aria-label). */
const STATUS_GLYPH: Record<Appointment['status'], string> = {
  scheduled: '○',
  confirmed: '●',
  checked_in: '▶',
  completed: '✓',
  cancelled: '✕',
  no_show: '!',
}

const description = computed(() => {
  const a = props.appointment
  return [
    `${a.local.start}–${a.local.end}`,
    a.customer?.name,
    a.service.name,
    props.showProfessional ? a.professional?.name : null,
    a.status_label,
  ]
    .filter(Boolean)
    .join(' · ')
})

const statusClass = computed(() => {
  switch (props.appointment.status) {
    case 'completed':
      return 'opacity-70'
    case 'no_show':
      return 'border-dashed opacity-70'
    case 'cancelled':
      return 'opacity-50 line-through'
    default:
      return ''
  }
})

/** Short status markers so the state never depends on color alone. */
const STATUS_MARK: Record<Appointment['status'], string> = {
  scheduled: 'A confirmar',
  confirmed: 'Confirmado',
  checked_in: 'Em atendimento',
  completed: 'Concluído',
  cancelled: 'Cancelado',
  no_show: 'Faltou',
}
</script>

<template>
  <div
    class="absolute px-0.5"
    :style="{
      top: `${top}px`,
      height: `${height + bufferHeight}px`,
      left: `${left * 100}%`,
      width: `${width * 100}%`,
    }"
  >
    <button
      type="button"
      class="relative flex w-full flex-col overflow-hidden rounded-control border border-l-[3px] px-1.5 text-left text-support leading-tight text-text-primary transition-[filter] duration-150 hover:brightness-125 focus-visible:z-10"
      :class="[statusClass, tier === 'tiny' ? 'justify-center' : 'py-1']"
      :style="{
        height: `${Math.max(height, 18)}px`,
        borderColor: `color-mix(in srgb, ${color} 45%, transparent)`,
        borderLeftColor: color,
        backgroundColor: `color-mix(in srgb, ${color} 20%, var(--color-card))`,
      }"
      :title="description"
      :aria-label="description"
      @click="$emit('open', appointment.id)"
    >
      <template v-if="tier === 'narrow'">
        <span class="flex items-center gap-0.5 font-mono text-[11px] text-text-secondary">
          <span aria-hidden="true">{{ STATUS_GLYPH[appointment.status] }}</span
          >{{ appointment.local.start }}
        </span>
        <span v-if="height >= 32" class="truncate font-medium">{{ firstName }}</span>
      </template>
      <template v-else-if="tier === 'full'">
        <span class="flex items-center justify-between gap-1 font-mono text-[11px] text-text-secondary">
          <span>{{ appointment.local.start }}–{{ appointment.local.end }}</span>
          <span class="truncate font-sans">{{ STATUS_MARK[appointment.status] }}</span>
        </span>
        <span class="truncate font-medium">{{ appointment.customer?.name }}</span>
        <span class="truncate text-text-secondary">
          {{ appointment.service.name
          }}<template v-if="showProfessional && appointment.professional">
            · {{ appointment.professional.name }}</template
          >
        </span>
      </template>
      <template v-else-if="tier === 'compact'">
        <span class="truncate font-medium">{{ appointment.customer?.name }}</span>
        <span class="truncate font-mono text-[11px] text-text-secondary">
          {{ appointment.local.start }} · {{ STATUS_MARK[appointment.status] }}
        </span>
      </template>
      <span v-else class="truncate">
        <span class="font-mono text-[11px] text-text-secondary">{{ appointment.local.start }}</span>
        {{ appointment.customer?.name }}
      </span>
    </button>
    <div
      v-if="bufferHeight > 0"
      class="mx-0.5 rounded-b-control"
      :style="{
        height: `${bufferHeight}px`,
        backgroundImage: `repeating-linear-gradient(135deg, color-mix(in srgb, ${color} 22%, transparent) 0 3px, transparent 3px 7px)`,
      }"
      aria-hidden="true"
    />
  </div>
</template>

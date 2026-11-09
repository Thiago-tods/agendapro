<script setup lang="ts">
type Tone = 'neutral' | 'primary' | 'success' | 'warning' | 'danger' | 'info'

withDefaults(defineProps<{ tone?: Tone; dot?: boolean }>(), { tone: 'neutral', dot: false })

const toneClasses: Record<Tone, string> = {
  neutral: 'bg-elevated text-text-secondary border-border',
  primary: 'bg-primary-soft text-primary-hover border-primary/30',
  success: 'bg-success/10 text-success border-success/25',
  warning: 'bg-warning/10 text-warning border-warning/25',
  danger: 'bg-danger/10 text-danger border-danger/25',
  info: 'bg-info/10 text-info border-info/25',
}
</script>

<template>
  <span
    class="inline-flex h-6 items-center gap-1.5 rounded-control border px-2 text-support font-medium whitespace-nowrap"
    :class="toneClasses[tone]"
  >
    <span v-if="dot" class="size-1.5 rounded-full bg-current" aria-hidden="true" />
    <slot />
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger'
type Size = 'sm' | 'md'

const props = withDefaults(
  defineProps<{
    variant?: Variant
    size?: Size
    type?: 'button' | 'submit' | 'reset'
    disabled?: boolean
    loading?: boolean
    block?: boolean
  }>(),
  { variant: 'primary', size: 'md', type: 'button', disabled: false, loading: false, block: false },
)

const variantClasses: Record<Variant, string> = {
  primary:
    'bg-primary text-primary-text hover:bg-primary-hover active:bg-primary disabled:hover:bg-primary',
  secondary:
    'bg-elevated text-text-primary border border-border hover:border-text-muted hover:bg-[#2f2a40] active:bg-elevated',
  ghost:
    'bg-transparent text-text-secondary hover:bg-elevated hover:text-text-primary active:bg-surface',
  danger:
    'bg-transparent text-danger border border-danger/40 hover:bg-danger/10 hover:border-danger/70 active:bg-danger/15',
}

const sizeClasses: Record<Size, string> = {
  sm: 'h-8 px-3 gap-1.5 text-label',
  md: 'h-10 px-4 gap-2 text-body',
}

const isInactive = computed(() => props.disabled || props.loading)
</script>

<template>
  <button
    :type="type"
    :disabled="isInactive"
    :aria-busy="loading || undefined"
    class="relative inline-flex select-none items-center justify-center rounded-input font-medium whitespace-nowrap transition-colors duration-150 ease-standard disabled:cursor-not-allowed disabled:opacity-50"
    :class="[variantClasses[variant], sizeClasses[size], { 'w-full': block }]"
  >
    <span
      v-if="loading"
      class="absolute inset-0 flex items-center justify-center"
      aria-hidden="true"
    >
      <span class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" />
    </span>
    <span class="inline-flex items-center gap-[inherit]" :class="{ invisible: loading }">
      <slot name="icon" />
      <slot />
    </span>
    <span v-if="loading" class="sr-only">Carregando</span>
  </button>
</template>

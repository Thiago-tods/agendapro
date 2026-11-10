<script setup lang="ts">
import { computed, useId } from 'vue'

export interface SelectOption {
  value: string
  label: string
}

const model = defineModel<string>({ required: true })

const props = withDefaults(
  defineProps<{
    label: string
    options: SelectOption[]
    hint?: string
    error?: string
    disabled?: boolean
    hideLabel?: boolean
  }>(),
  { disabled: false, hideLabel: false },
)

const id = useId()
const messageId = `${id}-message`
const message = computed(() => props.error ?? props.hint)
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label :for="id" class="text-label font-medium text-text-secondary" :class="{ 'sr-only': hideLabel }">
      {{ label }}
    </label>
    <div class="relative">
      <select
        :id="id"
        v-model="model"
        :disabled="disabled"
        :aria-invalid="error ? true : undefined"
        :aria-describedby="message ? messageId : undefined"
        class="h-10 w-full appearance-none rounded-input border bg-surface pr-9 pl-3 text-body text-text-primary transition-colors duration-150 ease-standard hover:border-text-muted focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/35 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:border-border"
        :class="error ? 'border-danger/70' : 'border-border'"
      >
        <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>
      <svg
        class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-text-muted"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        aria-hidden="true"
      >
        <path d="m6 9 6 6 6-6" />
      </svg>
    </div>
    <p
      v-if="message"
      :id="messageId"
      class="text-support"
      :class="error ? 'text-danger' : 'text-text-muted'"
      :role="error ? 'alert' : undefined"
    >
      {{ message }}
    </p>
  </div>
</template>

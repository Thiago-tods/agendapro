<script setup lang="ts">
import { computed, useId } from 'vue'

const model = defineModel<string>({ default: '' })

const props = withDefaults(
  defineProps<{
    label: string
    type?: 'text' | 'email' | 'password' | 'tel' | 'search' | 'number' | 'url'
    placeholder?: string
    hint?: string
    error?: string
    success?: string
    disabled?: boolean
    required?: boolean
    autocomplete?: string
    hideLabel?: boolean
  }>(),
  { type: 'text', disabled: false, required: false, hideLabel: false },
)

const id = useId()
const messageId = `${id}-message`

const message = computed(() => props.error ?? props.success ?? props.hint)
const state = computed(() => (props.error ? 'error' : props.success ? 'success' : 'default'))
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label
      :for="id"
      class="text-label font-medium text-text-secondary"
      :class="{ 'sr-only': hideLabel }"
    >
      {{ label }}
      <span v-if="required" class="text-danger" aria-hidden="true">*</span>
    </label>

    <div class="relative">
      <span
        v-if="$slots.leading"
        class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-text-muted"
      >
        <slot name="leading" />
      </span>
      <input
        :id="id"
        v-model="model"
        :type="type"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        :autocomplete="autocomplete"
        :aria-invalid="state === 'error' || undefined"
        :aria-describedby="message ? messageId : undefined"
        class="h-10 w-full rounded-input border bg-surface px-3 text-body text-text-primary transition-colors duration-150 ease-standard placeholder:text-text-muted hover:border-text-muted focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/35 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:border-border"
        :class="[
          {
            'border-border': state === 'default',
            'border-danger/70 hover:border-danger': state === 'error',
            'border-success/60 hover:border-success': state === 'success',
          },
          { 'pl-9': $slots.leading },
        ]"
      />
    </div>

    <p
      v-if="message"
      :id="messageId"
      class="text-support"
      :class="{
        'text-text-muted': state === 'default',
        'text-danger': state === 'error',
        'text-success': state === 'success',
      }"
      :role="state === 'error' ? 'alert' : undefined"
    >
      {{ message }}
    </p>
  </div>
</template>

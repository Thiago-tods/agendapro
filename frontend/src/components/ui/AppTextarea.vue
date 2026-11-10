<script setup lang="ts">
import { computed, useId } from 'vue'

const model = defineModel<string>({ default: '' })
const props = withDefaults(
  defineProps<{
    label: string
    hint?: string
    error?: string
    disabled?: boolean
    rows?: number
    maxlength?: number
  }>(),
  { disabled: false, rows: 3 },
)

const id = useId()
const messageId = `${id}-message`
const message = computed(() => props.error ?? props.hint)
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label :for="id" class="text-label font-medium text-text-secondary">{{ label }}</label>
    <textarea
      :id="id"
      v-model="model"
      :rows="rows"
      :maxlength="maxlength"
      :disabled="disabled"
      :aria-invalid="error ? true : undefined"
      :aria-describedby="message ? messageId : undefined"
      class="w-full resize-y rounded-input border bg-surface px-3 py-2 text-body text-text-primary transition-colors duration-150 placeholder:text-text-muted hover:border-text-muted focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/35 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
      :class="error ? 'border-danger/70' : 'border-border'"
    />
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

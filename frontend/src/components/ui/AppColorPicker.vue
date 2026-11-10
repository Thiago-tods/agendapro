<script setup lang="ts">
/**
 * Picks from a fixed palette so calendar colors stay consistent instead of arbitrary.
 * A color outside the palette (saved earlier) is kept as an extra swatch.
 */
import { computed } from 'vue'
import { COLOR_PALETTE } from '@/services/forms'

const model = defineModel<string>({ required: true })
defineProps<{ label: string; error?: string }>()

const swatches = computed(() =>
  COLOR_PALETTE.some((c) => c.value === model.value) || !model.value
    ? COLOR_PALETTE
    : [{ value: model.value, name: 'Cor atual' }, ...COLOR_PALETTE],
)
</script>

<template>
  <fieldset>
    <legend class="mb-1.5 text-label font-medium text-text-secondary">
      {{ label }}
    </legend>
    <div class="flex flex-wrap gap-2" role="radiogroup" :aria-label="label">
      <button
        v-for="swatch in swatches"
        :key="swatch.value"
        type="button"
        role="radio"
        :aria-checked="model === swatch.value"
        :aria-label="swatch.name"
        :title="swatch.name"
        class="size-7 rounded-control border-2 transition-transform duration-150 focus-visible:ring-2 focus-visible:ring-primary/60 focus-visible:outline-none motion-reduce:transition-none"
        :class="model === swatch.value ? 'scale-110 border-text-primary' : 'border-transparent hover:scale-105'"
        :style="{ backgroundColor: swatch.value }"
        @click="model = swatch.value"
      />
    </div>
    <p v-if="error" class="mt-1.5 text-support text-danger" role="alert">
      {{ error }}
    </p>
  </fieldset>
</template>

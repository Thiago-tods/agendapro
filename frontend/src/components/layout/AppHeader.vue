<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

defineProps<{ navigationExpanded: boolean }>()
defineEmits<{ 'toggle-navigation': [] }>()

const route = useRoute()
const title = computed(() => (typeof route.meta.title === 'string' ? route.meta.title : ''))
</script>

<template>
  <header
    class="sticky top-0 z-20 flex h-(--header-height) items-center gap-3 border-b border-border bg-background px-4 sm:px-6 lg:px-8"
  >
    <button
      type="button"
      class="inline-flex size-9 items-center justify-center rounded-input text-text-secondary transition-colors duration-150 hover:bg-elevated hover:text-text-primary active:bg-surface"
      aria-controls="navegacao-principal"
      :aria-expanded="navigationExpanded"
      aria-label="Alternar navegação"
      @click="$emit('toggle-navigation')"
    >
      <svg
        class="size-5"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.75"
        stroke-linecap="round"
        aria-hidden="true"
      >
        <path d="M4 6h16M4 12h16M4 18h10" />
      </svg>
    </button>

    <p class="min-w-0 truncate text-card-title font-medium">{{ title }}</p>

    <div class="ml-auto flex items-center gap-2">
      <slot name="actions" />
    </div>
  </header>
</template>

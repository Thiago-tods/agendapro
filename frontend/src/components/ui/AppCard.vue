<script setup lang="ts">
withDefaults(
  defineProps<{
    title?: string
    description?: string
    as?: 'section' | 'article' | 'div'
    padding?: 'none' | 'md' | 'lg'
  }>(),
  { as: 'section', padding: 'md' },
)
</script>

<template>
  <component
    :is="as"
    class="rounded-card border border-border bg-card"
  >
    <header
      v-if="title || $slots.header || $slots.actions"
      class="flex items-start justify-between gap-4 border-b border-border px-5 py-4"
    >
      <slot name="header">
        <div class="min-w-0">
          <h3 class="text-card-title">{{ title }}</h3>
          <p v-if="description" class="mt-0.5 text-label text-text-muted">{{ description }}</p>
        </div>
      </slot>
      <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
        <slot name="actions" />
      </div>
    </header>
    <div :class="{ 'p-5': padding === 'md', 'p-6': padding === 'lg' }">
      <slot />
    </div>
    <footer v-if="$slots.footer" class="border-t border-border px-5 py-3">
      <slot name="footer" />
    </footer>
  </component>
</template>

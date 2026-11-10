<script setup lang="ts">
defineProps<{ title: string; description?: string }>()
</script>

<template>
  <div class="flex flex-col items-center gap-2 px-6 py-12 text-center">
    <p class="text-card-title">{{ title }}</p>
    <p v-if="description" class="max-w-sm text-label text-text-muted">
      {{ description }}
    </p>
    <div v-if="$slots.default" class="mt-3"><slot /></div>
  </div>
</template>

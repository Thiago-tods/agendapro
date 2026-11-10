<script setup lang="ts">
withDefaults(defineProps<{ lines?: number }>(), { lines: 1 })
</script>

<template>
  <div class="flex flex-col gap-2" aria-hidden="true">
    <span
      v-for="line in lines"
      :key="line"
      class="block h-3 animate-pulse rounded-control bg-elevated motion-reduce:animate-none"
      :style="{ width: line === lines && lines > 1 ? '60%' : '100%' }"
    />
  </div>
</template>

<script setup lang="ts">
import AppButton from '@/components/ui/AppButton.vue'

defineProps<{ message: string }>()
defineEmits<{ retry: [] }>()
</script>

<template>
  <div role="alert" class="flex flex-col items-center gap-3 px-6 py-12 text-center">
    <p class="text-body text-danger">{{ message }}</p>
    <AppButton variant="secondary" size="sm" @click="$emit('retry')">Tentar novamente</AppButton>
  </div>
</template>

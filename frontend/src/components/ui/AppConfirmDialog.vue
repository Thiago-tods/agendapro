<script setup lang="ts">
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'

/** Confirmation before destructive or hard-to-undo actions. */
const open = defineModel<boolean>('open', { required: true })
withDefaults(
  defineProps<{
    title: string
    message: string
    confirmLabel: string
    tone?: 'primary' | 'danger'
    loading?: boolean
  }>(),
  { tone: 'danger', loading: false },
)
defineEmits<{ confirm: [] }>()
</script>

<template>
  <AppModal v-model:open="open" :title="title">
    <p class="text-body text-text-secondary">{{ message }}</p>
    <template #footer>
      <AppButton variant="ghost" :disabled="loading" @click="open = false">Voltar</AppButton>
      <AppButton :variant="tone" :loading="loading" @click="$emit('confirm')">{{ confirmLabel }}</AppButton>
    </template>
  </AppModal>
</template>

<script setup lang="ts">
import { useToast, type ToastTone } from '@/composables/useToast'

const { toasts, dismiss } = useToast()

const toneClasses: Record<ToastTone, string> = {
  success: 'border-success/30 text-success',
  danger: 'border-danger/40 text-danger',
  info: 'border-info/30 text-info',
}

const toneLabel: Record<ToastTone, string> = {
  success: 'Sucesso',
  danger: 'Erro',
  info: 'Aviso',
}
</script>

<template>
  <div
    class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-end gap-2 sm:inset-x-auto sm:right-6 sm:bottom-6"
    aria-live="polite"
    role="status"
  >
    <TransitionGroup
      enter-from-class="opacity-0 translate-y-2"
      leave-to-class="opacity-0"
      enter-active-class="transition duration-200 ease-standard"
      leave-active-class="transition duration-150"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto flex w-full items-start gap-3 rounded-card border bg-elevated px-4 py-3 sm:w-[360px]"
        :class="toneClasses[toast.tone]"
      >
        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-current" aria-hidden="true" />
        <p class="min-w-0 flex-1 text-body text-text-primary">
          <span class="sr-only">{{ toneLabel[toast.tone] }}: </span>{{ toast.message }}
        </p>
        <button
          type="button"
          class="-mr-1 inline-flex size-6 shrink-0 items-center justify-center rounded-control text-text-muted hover:bg-surface hover:text-text-primary"
          aria-label="Fechar notificação"
          @click="dismiss(toast.id)"
        >
          <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <path d="M6 6l12 12M18 6 6 18" />
          </svg>
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

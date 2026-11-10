<script setup lang="ts">
import { onBeforeUnmount, ref, useId, watch } from 'vue'

/**
 * Accessible dialog on top of the native <dialog> element: focus is trapped and restored by
 * the browser, Esc closes it, and the page behind becomes inert. Full screen on small viewports.
 */
const open = defineModel<boolean>('open', { required: true })

defineProps<{ title: string; description?: string }>()

const dialog = ref<HTMLDialogElement | null>(null)
const titleId = useId()

watch(
  open,
  (value) => {
    const el = dialog.value
    if (!el) return
    if (value && !el.open) el.showModal()
    if (!value && el.open) el.close()
  },
  { flush: 'post' },
)

function onClose(): void {
  open.value = false
}

function onBackdropClick(event: MouseEvent): void {
  if (event.target === dialog.value) open.value = false
}

onBeforeUnmount(() => dialog.value?.open && dialog.value.close())
</script>

<template>
  <dialog
    ref="dialog"
    :aria-labelledby="titleId"
    class="m-0 h-dvh max-h-none w-full max-w-none border-border bg-card p-0 text-text-primary backdrop:bg-black/60 sm:m-auto sm:h-auto sm:max-h-[calc(100dvh-48px)] sm:w-[560px] sm:rounded-modal sm:border"
    @close="onClose"
    @click="onBackdropClick"
  >
    <div v-if="open" class="flex h-full flex-col">
      <header class="flex items-start justify-between gap-4 border-b border-border px-6 py-4">
        <div>
          <h2 :id="titleId" class="text-section-title">{{ title }}</h2>
          <p v-if="description" class="mt-0.5 text-label text-text-muted">{{ description }}</p>
        </div>
        <button
          type="button"
          class="-mr-2 inline-flex size-8 items-center justify-center rounded-input text-text-muted hover:bg-elevated hover:text-text-primary"
          aria-label="Fechar"
          @click="open = false"
        >
          <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <path d="M6 6l12 12M18 6 6 18" />
          </svg>
        </button>
      </header>
      <div class="flex-1 overflow-y-auto px-6 py-5">
        <slot />
      </div>
      <footer v-if="$slots.footer" class="flex justify-end gap-2 border-t border-border px-6 py-4">
        <slot name="footer" />
      </footer>
    </div>
  </dialog>
</template>

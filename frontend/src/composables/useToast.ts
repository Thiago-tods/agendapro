import { readonly, ref } from 'vue'

export type ToastTone = 'success' | 'danger' | 'info'

export interface Toast {
  id: number
  tone: ToastTone
  message: string
}

const toasts = ref<Toast[]>([])
let nextId = 1

const DURATION_MS: Record<ToastTone, number> = { success: 4000, info: 5000, danger: 7000 }

function dismiss(id: number): void {
  toasts.value = toasts.value.filter((toast) => toast.id !== id)
}

function push(tone: ToastTone, message: string): void {
  // Avoid stacking the same message (e.g. several requests failing at once).
  if (toasts.value.some((toast) => toast.message === message)) return

  const id = nextId++
  toasts.value = [...toasts.value.slice(-3), { id, tone, message }]
  window.setTimeout(() => dismiss(id), DURATION_MS[tone])
}

/** App-wide feedback after operations. Rendered once by AppToast in App.vue. */
export function useToast() {
  return {
    toasts: readonly(toasts),
    dismiss,
    success: (message: string) => push('success', message),
    error: (message: string) => push('danger', message),
    info: (message: string) => push('info', message),
  }
}

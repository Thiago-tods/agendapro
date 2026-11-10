import { onBeforeUnmount, ref, shallowRef, watch, type Ref, type WatchSource } from 'vue'
import { isApiError } from '@/services/http'
import type { Paginated } from '@/types/api'

/**
 * Loads one page of a paginated endpoint and reloads when the filters change (debounced for
 * typing). Older requests are aborted, so a slow response cannot overwrite a newer result.
 */
export function usePagedList<T>(
  fetcher: (page: number, signal: AbortSignal) => Promise<Paginated<T>>,
  filters: WatchSource[],
  options: { debounceMs?: number } = {},
) {
  const items = shallowRef<T[]>([]) as Ref<T[]>
  const meta = ref({ current_page: 1, last_page: 1, per_page: 25, total: 0 })
  const page = ref(1)
  const loading = ref(true)
  const error = ref<string | null>(null)

  let controller: AbortController | null = null
  let timer: ReturnType<typeof setTimeout> | undefined

  async function load(): Promise<void> {
    controller?.abort()
    const current = new AbortController()
    controller = current
    loading.value = true
    error.value = null

    try {
      const result = await fetcher(page.value, current.signal)
      if (current.signal.aborted) return
      // A page past the end (e.g. after archiving the last row) falls back to the last one.
      if (result.data.length === 0 && page.value > 1 && result.meta.last_page < page.value) {
        page.value = result.meta.last_page
        return load()
      }
      items.value = result.data
      meta.value = result.meta
    } catch (caught) {
      if (current.signal.aborted) return
      error.value = isApiError(caught) ? caught.message : 'Não foi possível carregar os dados.'
    } finally {
      if (controller === current) loading.value = false
    }
  }

  watch(filters, () => {
    page.value = 1
    clearTimeout(timer)
    timer = setTimeout(() => void load(), options.debounceMs ?? 0)
  })

  function goTo(target: number): void {
    page.value = target
    void load()
  }

  onBeforeUnmount(() => {
    clearTimeout(timer)
    controller?.abort()
  })

  void load()

  return { items, meta, page, loading, error, reload: load, goTo }
}

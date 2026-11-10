import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { Company, Permission } from '@/types/api'

const LAST_COMPANY_KEY = 'agendapro:last-company'

/**
 * The company in context is always the one in the URL (/empresas/:companyId/...).
 * The backend re-checks membership on every request, so the URL cannot grant access.
 */
export function useCurrentCompany() {
  const route = useRoute()
  const auth = useAuthStore()

  const companyId = computed(() => {
    const value = route.params.companyId
    return typeof value === 'string' ? value : null
  })

  const company = computed<Company | null>(() => (companyId.value ? (auth.companyById(companyId.value) ?? null) : null))

  /**
   * Whether the backend reported this capability for the current user. Only for showing or
   * hiding UI; the API remains the authority and answers 403 on its own.
   */
  function can(permission: Permission): boolean {
    return company.value?.membership?.permissions[permission] ?? false
  }

  return { companyId, company, can }
}

/** Remembers the last company used, to open it directly next time. A preference, not a credential. */
export function rememberCompany(id: string): void {
  try {
    localStorage.setItem(LAST_COMPANY_KEY, id)
  } catch {
    /* storage unavailable: preference is simply not kept */
  }
}

export function lastCompanyId(): string | null {
  try {
    return localStorage.getItem(LAST_COMPANY_KEY)
  } catch {
    return null
  }
}

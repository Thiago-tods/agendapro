import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi, type LoginCredentials } from '@/modules/auth/api'
import { isApiError, refreshCsrfCookie } from '@/services/http'
import type { AuthUser, Company, SessionPayload } from '@/types/api'

type SessionStatus = 'unknown' | 'authenticated' | 'guest'

/**
 * Who is signed in and which companies they belong to. Holds no credentials: the session
 * lives in the HttpOnly cookie managed by the browser and the backend.
 */
export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const companies = ref<Company[]>([])
  const status = ref<SessionStatus>('unknown')

  const isAuthenticated = computed(() => status.value === 'authenticated')

  let restoring: Promise<void> | null = null

  function apply(session: SessionPayload): void {
    user.value = session.user
    companies.value = session.companies
    status.value = 'authenticated'
  }

  /** Clears local state only (used when the server says the session is gone). */
  function clear(): void {
    user.value = null
    companies.value = []
    status.value = 'guest'
  }

  /** Restores the session from the cookie once per page load. Concurrent callers share the request. */
  function restore(): Promise<void> {
    if (status.value !== 'unknown') return Promise.resolve()

    restoring ??= authApi
      .me()
      .then(apply)
      .catch((error: unknown) => {
        if (isApiError(error) && error.status === 401) {
          clear()
          return
        }
        // Network/server failure: stay "guest" so the login page can show the error on retry.
        clear()
        throw error
      })
      .finally(() => {
        restoring = null
      })

    return restoring
  }

  async function login(credentials: LoginCredentials): Promise<void> {
    await refreshCsrfCookie()
    apply(await authApi.login(credentials))
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout()
    } finally {
      clear()
      // The backend rotated the CSRF token; fetch a fresh one for the next login.
      await refreshCsrfCookie().catch(() => undefined)
    }
  }

  function companyById(id: string): Company | undefined {
    return companies.value.find((company) => company.id === id)
  }

  /** Keeps the cached company in sync after it is edited. */
  function replaceCompany(updated: Company): void {
    companies.value = companies.value.map((company) => (company.id === updated.id ? updated : company))
  }

  return { user, companies, status, isAuthenticated, restore, login, logout, clear, companyById, replaceCompany }
})

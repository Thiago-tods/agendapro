import type { NavigationGuardWithThis, RouteLocationRaw } from 'vue-router'
import { lastCompanyId, rememberCompany } from '@/composables/useCurrentCompany'
import { useAuthStore } from '@/stores/auth'

/** Where a signed-in user lands when no specific page was requested. */
export function homeFor(auth: ReturnType<typeof useAuthStore>): RouteLocationRaw {
  const companies = auth.companies
  const last = lastCompanyId()
  const preferred = companies.find((c) => c.id === last) ?? (companies.length === 1 ? companies[0] : undefined)

  return preferred ? { name: 'company-home', params: { companyId: preferred.id } } : { name: 'company-select' }
}

/**
 * Session and company-context guard. Restores the session from the cookie on the first
 * navigation, then routes visitors and members to the right place.
 */
export const authGuard: NavigationGuardWithThis<undefined> = async (to) => {
  const auth = useAuthStore()

  try {
    await auth.restore()
  } catch {
    // Server unreachable: treat as signed out; the login page reports errors on submit.
  }

  if (to.name === 'root') {
    return auth.isAuthenticated ? homeFor(auth) : { name: 'login' }
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return homeFor(auth)
  }

  const companyId = to.params.companyId
  if (typeof companyId === 'string') {
    const company = auth.companyById(companyId)

    // Not a member (or unknown id): back to the picker. The API would answer 404 anyway.
    if (!company) return { name: 'company-select' }

    rememberCompany(companyId)

    if (to.meta.permission && !company.membership?.permissions[to.meta.permission]) {
      return { name: 'company-home', params: { companyId } }
    }
  }

  return true
}

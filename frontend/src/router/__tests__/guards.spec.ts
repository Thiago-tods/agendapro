import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { authApi } from '@/modules/auth/api'
import { authGuard } from '@/router/guards'
import { routes } from '@/router/routes'
import { ApiError } from '@/services/http'
import type { Company, SessionPayload } from '@/types/api'

function company(id: string, manageCompany: boolean): Company {
  return {
    id,
    name: `Empresa ${id}`,
    slug: id,
    timezone: 'America/Sao_Paulo',
    currency: 'BRL',
    locale: 'pt_BR',
    membership: {
      role: manageCompany ? 'owner' : 'professional',
      role_label: manageCompany ? 'Proprietário' : 'Profissional',
      permissions: {
        manage_company: manageCompany,
        manage_members: manageCompany,
        manage_customers: manageCompany,
        manage_catalog: manageCompany,
        manage_availability: manageCompany,
        manage_appointments: manageCompany,
        delete_records: manageCompany,
        view_financials: manageCompany,
      },
    },
  }
}

function makeRouter(): Router {
  const router = createRouter({ history: createMemoryHistory(), routes })
  router.beforeEach(authGuard)
  return router
}

describe('auth guard', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  function signedIn(companies: Company[]): void {
    const session: SessionPayload = { user: { id: 'u', name: 'Ana', email: 'a@b.c' }, companies }
    vi.spyOn(authApi, 'me').mockResolvedValue(session)
  }

  it('sends visitors to the login page, keeping the requested path', async () => {
    vi.spyOn(authApi, 'me').mockRejectedValue(new ApiError(401, 'Não autenticado.'))
    const router = makeRouter()

    await router.push('/empresas/c1/configuracoes/empresa')

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/empresas/c1/configuracoes/empresa')
  })

  it('opens the only company directly from the root', async () => {
    signedIn([company('c1', true)])
    const router = makeRouter()

    await router.push('/')

    expect(router.currentRoute.value.name).toBe('company-home')
    expect(router.currentRoute.value.params.companyId).toBe('c1')
  })

  it('shows the company picker when the user has several companies and no preference', async () => {
    signedIn([company('c1', true), company('c2', false)])
    const router = makeRouter()

    await router.push('/')

    expect(router.currentRoute.value.name).toBe('company-select')
  })

  it('reopens the last used company', async () => {
    signedIn([company('c1', true), company('c2', false)])
    localStorage.setItem('agendapro:last-company', 'c2')
    const router = makeRouter()

    await router.push('/')

    expect(router.currentRoute.value.params.companyId).toBe('c2')
  })

  it('refuses a company the user does not belong to', async () => {
    signedIn([company('c1', true)])
    const router = makeRouter()

    await router.push('/empresas/outra-empresa')

    expect(router.currentRoute.value.name).toBe('company-select')
  })

  it('keeps signed-in users away from the login page', async () => {
    signedIn([company('c1', true)])
    const router = makeRouter()

    await router.push('/entrar')

    expect(router.currentRoute.value.name).toBe('company-home')
  })
})

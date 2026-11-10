import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authApi } from '@/modules/auth/api'
import { ApiError } from '@/services/http'
import * as httpModule from '@/services/http'
import { useAuthStore } from '@/stores/auth'
import type { SessionPayload } from '@/types/api'

const session: SessionPayload = {
  user: { id: 'u1', name: 'Ana Souza', email: 'ana@studio.test' },
  companies: [
    {
      id: 'c1',
      name: 'Studio',
      slug: 'studio',
      timezone: 'America/Sao_Paulo',
      currency: 'BRL',
      locale: 'pt_BR',
      membership: {
        role: 'owner',
        role_label: 'Proprietário',
        permissions: {
          manage_company: true,
          manage_members: true,
          manage_customers: true,
          manage_catalog: true,
          manage_availability: true,
          manage_appointments: true,
          delete_records: true,
        },
      },
    },
  ],
}

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.spyOn(httpModule, 'refreshCsrfCookie').mockResolvedValue()
  })

  it('restores an existing session once, sharing concurrent calls', async () => {
    const me = vi.spyOn(authApi, 'me').mockResolvedValue(session)
    const auth = useAuthStore()

    await Promise.all([auth.restore(), auth.restore()])
    await auth.restore()

    expect(me).toHaveBeenCalledTimes(1)
    expect(auth.isAuthenticated).toBe(true)
    expect(auth.companyById('c1')?.name).toBe('Studio')
  })

  it('becomes guest when the session cookie is not valid', async () => {
    vi.spyOn(authApi, 'me').mockRejectedValue(new ApiError(401, 'Não autenticado.'))
    const auth = useAuthStore()

    await auth.restore()

    expect(auth.status).toBe('guest')
    expect(auth.user).toBeNull()
  })

  it('logs in after refreshing the CSRF cookie', async () => {
    const login = vi.spyOn(authApi, 'login').mockResolvedValue(session)
    const auth = useAuthStore()

    await auth.login({ email: 'ana@studio.test', password: 'x', remember: false })

    expect(httpModule.refreshCsrfCookie).toHaveBeenCalled()
    expect(login).toHaveBeenCalled()
    expect(auth.user?.email).toBe('ana@studio.test')
  })

  it('clears local state on logout even if the request fails', async () => {
    vi.spyOn(authApi, 'login').mockResolvedValue(session)
    vi.spyOn(authApi, 'logout').mockRejectedValue(new ApiError(0, 'offline'))
    const auth = useAuthStore()
    await auth.login({ email: 'a', password: 'b', remember: false })

    await expect(auth.logout()).rejects.toBeInstanceOf(ApiError)

    expect(auth.isAuthenticated).toBe(false)
    expect(auth.companies).toEqual([])
  })

  it('keeps no credentials in web storage', async () => {
    vi.spyOn(authApi, 'login').mockResolvedValue(session)
    const auth = useAuthStore()

    await auth.login({ email: 'ana@studio.test', password: 'segredo', remember: true })

    const stored = JSON.stringify({ ...localStorage }) + JSON.stringify({ ...sessionStorage })
    expect(stored).not.toContain('segredo')
    expect(stored.toLowerCase()).not.toContain('token')
  })
})

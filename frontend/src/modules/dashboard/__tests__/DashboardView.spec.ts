import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia, type Pinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { dashboardApi } from '@/modules/dashboard/api'
import type { Dashboard } from '@/modules/dashboard/types'
import DashboardView from '@/modules/dashboard/views/DashboardView.vue'
import { ApiError } from '@/services/http'
import { useAuthStore } from '@/stores/auth'
import type { Company } from '@/types/api'

function company(financial: boolean): Company {
  return {
    id: 'c1',
    name: 'Studio',
    slug: 'studio',
    timezone: 'America/Sao_Paulo',
    currency: 'BRL',
    locale: 'pt_BR',
    membership: {
      role: financial ? 'owner' : 'receptionist',
      role_label: financial ? 'Proprietário' : 'Recepcionista',
      permissions: {
        manage_company: financial,
        manage_members: financial,
        manage_customers: true,
        manage_catalog: financial,
        manage_availability: financial,
        manage_appointments: true,
        delete_records: financial,
        view_financials: financial,
      },
    },
  }
}

function dashboard(overrides: Partial<Dashboard> = {}, revenue: number | null = 250000): Dashboard {
  const metrics = (completed: number) => ({
    booked: completed + 2,
    completed,
    no_show: 1,
    cancelled: 0,
    attendance_rate: completed / (completed + 1),
    revenue_cents: revenue,
  })
  return {
    timezone: 'America/Sao_Paulo',
    generated_at: '2026-11-09T12:00:00Z',
    today: { total: 4, remaining: 2, in_progress: 1, completed: 1, no_show: 0, cancelled: 0, awaiting_confirmation: 3 },
    period: { days: 30, from: '2026-10-11', to: '2026-11-09', current: metrics(9), previous: metrics(6) },
    weekly_trend: [{ week_start: '2026-11-02', booked: 5, completed: 4, revenue_cents: revenue }],
    upcoming: [],
    recent_activity: [],
    top_services: [],
    top_professionals: [],
    ...overrides,
  }
}

async function mountAs(financial: boolean): Promise<{ wrapper: ReturnType<typeof mount>; router: Router; pinia: Pinia }> {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.user = { id: 'u1', name: 'Ana Souza', email: 'a@b.c' }
  auth.companies = [company(financial)]
  auth.status = 'authenticated'

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/empresas/:companyId', component: DashboardView }],
  })
  await router.push('/empresas/c1')
  await router.isReady()

  const wrapper = mount(DashboardView, { global: { plugins: [pinia, router], stubs: { NewAppointmentModal: true } } })
  await flushPromises()
  return { wrapper, router, pinia }
}

describe('DashboardView', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('shows real figures and revenue for roles allowed to see financials', async () => {
    vi.spyOn(dashboardApi, 'get').mockResolvedValue(dashboard())
    const { wrapper } = await mountAs(true)

    expect(wrapper.text()).toContain('Olá, Ana')
    expect(wrapper.text()).toContain('Receita realizada')
    expect(wrapper.text().replace(/\s/g, ' ')).toContain('R$ 2.500,00')
    expect(wrapper.text()).toContain('Novo agendamento')
  })

  it('hides financial indicators when the backend withholds them', async () => {
    vi.spyOn(dashboardApi, 'get').mockResolvedValue(dashboard({}, null))
    const { wrapper } = await mountAs(false)

    expect(wrapper.text()).not.toContain('Receita')
    expect(wrapper.text()).not.toContain('R$')
    expect(wrapper.text()).toContain('Taxa de comparecimento')
  })

  it('shows empty states instead of placeholder data', async () => {
    vi.spyOn(dashboardApi, 'get').mockResolvedValue(
      dashboard({ weekly_trend: [{ week_start: '2026-11-02', booked: 0, completed: 0, revenue_cents: 0 }] }),
    )
    const { wrapper } = await mountAs(true)

    expect(wrapper.text()).toContain('Nenhum agendamento futuro.')
    expect(wrapper.text()).toContain('As alterações em agendamentos aparecerão aqui.')
    expect(wrapper.text()).toContain('Ainda não há atendimentos concluídos no período.')
    expect(wrapper.text()).toContain('A tendência semanal aparece após os primeiros atendimentos.')
  })

  it('shows an error state with retry when the API fails', async () => {
    const get = vi
      .spyOn(dashboardApi, 'get')
      .mockRejectedValueOnce(new ApiError(500, 'Ocorreu um erro inesperado. Tente novamente em instantes.'))
      .mockResolvedValueOnce(dashboard())
    const { wrapper } = await mountAs(true)

    expect(wrapper.find('[role="alert"]').text()).toContain('Não foi possível carregar o painel')

    await wrapper.findAll('button').find((b) => b.text().includes('Tentar novamente'))!.trigger('click')
    await flushPromises()

    expect(get).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Agendamentos hoje')
  })
})

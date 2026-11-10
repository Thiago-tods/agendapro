import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { customersApi } from '@/modules/customers/api'
import CustomerProfileView from '@/modules/customers/views/CustomerProfileView.vue'
import CustomersView from '@/modules/customers/views/CustomersView.vue'
import { ApiError } from '@/services/http'
import { useAuthStore } from '@/stores/auth'
import type { Company, Customer, Paginated } from '@/types/api'

function company(manage: boolean): Company {
  return {
    id: 'c1',
    name: 'Studio',
    slug: 'studio',
    timezone: 'America/Sao_Paulo',
    currency: 'BRL',
    locale: 'pt_BR',
    membership: {
      role: manage ? 'receptionist' : 'professional',
      role_label: '',
      permissions: {
        manage_company: false,
        manage_members: false,
        manage_customers: manage,
        manage_catalog: false,
        manage_availability: false,
        manage_appointments: manage,
        delete_records: false,
        view_financials: false,
      },
    },
  }
}

function customer(overrides: Partial<Customer> = {}): Customer {
  return {
    id: 'cu1',
    name: 'Maria Oliveira',
    email: 'maria@x.com',
    phone: '11987654321',
    birth_date: null,
    notes: null,
    is_archived: false,
    archived_at: null,
    visits_count: 3,
    last_visit_at: '2026-11-02T13:00:00+00:00',
    next_appointment_at: null,
    created_at: '2026-01-01T12:00:00+00:00',
    ...overrides,
  }
}

function page(data: Customer[], meta: Partial<Paginated<Customer>['meta']> = {}): Paginated<Customer> {
  return { data, meta: { current_page: 1, last_page: 1, per_page: 25, total: data.length, ...meta } }
}

let wrapper: VueWrapper | null = null

async function mountAt(path: string, component: object, manage = true) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.user = { id: 'u1', name: 'Carla', email: 'c@x.y' }
  auth.companies = [company(manage)]
  auth.status = 'authenticated'

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/empresas/:companyId/clientes', name: 'customers', component: CustomersView },
      { path: '/empresas/:companyId/clientes/:customerId', name: 'customer-profile', component: CustomerProfileView },
    ],
  })
  await router.push(path)
  await router.isReady()
  wrapper = mount(component, { attachTo: document.body, global: { plugins: [pinia, router] } })
  await flushPromises()
  return { wrapper, router }
}

const button = (w: VueWrapper, text: string) => w.findAll('button').find((b) => b.text().trim() === text)

beforeAll(() => {
  HTMLDialogElement.prototype.showModal ??= function (this: HTMLDialogElement) {
    this.setAttribute('open', '')
  }
  HTMLDialogElement.prototype.close ??= function (this: HTMLDialogElement) {
    this.removeAttribute('open')
  }
})

beforeEach(() => {
  vi.spyOn(customersApi, 'list').mockResolvedValue(page([customer()]))
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  vi.useRealTimers()
  vi.restoreAllMocks()
})

describe('CustomersView', () => {
  it('lists customers with visit data and links to the profile', async () => {
    const { wrapper } = await mountAt('/empresas/c1/clientes', CustomersView)

    expect(customersApi.list).toHaveBeenCalledWith('c1', { search: undefined, status: 'active', page: 1 }, expect.anything())
    const link = wrapper.find('a[href="/empresas/c1/clientes/cu1"]')
    expect(link.text()).toBe('Maria Oliveira')
    expect(wrapper.text()).toContain('(11) 98765-4321')
    expect(wrapper.text()).toContain('2 nov')
  })

  it('debounces the search and resets to the first page', async () => {
    vi.useFakeTimers()
    const { wrapper } = await mountAt('/empresas/c1/clientes', CustomersView)
    vi.mocked(customersApi.list).mockClear()

    await wrapper.find('input[type="search"]').setValue('mar')
    await wrapper.find('input[type="search"]').setValue('mari')
    expect(customersApi.list).not.toHaveBeenCalled()

    vi.advanceTimersByTime(300)
    await flushPromises()
    expect(customersApi.list).toHaveBeenCalledTimes(1)
    expect(customersApi.list).toHaveBeenCalledWith('c1', { search: 'mari', status: 'active', page: 1 }, expect.anything())
  })

  it('paginates', async () => {
    vi.mocked(customersApi.list).mockResolvedValue(page([customer()], { last_page: 3, total: 60 }))
    const { wrapper } = await mountAt('/empresas/c1/clientes', CustomersView)

    await button(wrapper, 'Próxima')!.trigger('click')
    await flushPromises()
    expect(customersApi.list).toHaveBeenLastCalledWith('c1', expect.objectContaining({ page: 2 }), expect.anything())
  })

  it('shows distinct empty states for no data and no matches', async () => {
    vi.mocked(customersApi.list).mockResolvedValue(page([]))
    const { wrapper } = await mountAt('/empresas/c1/clientes', CustomersView)
    expect(wrapper.text()).toContain('Nenhum cliente cadastrado')

    vi.useFakeTimers()
    await wrapper.find('input[type="search"]').setValue('zzz')
    vi.advanceTimersByTime(300)
    await flushPromises()
    expect(wrapper.text()).toContain('Nenhum cliente encontrado')
  })

  it('shows the API error with retry', async () => {
    vi.mocked(customersApi.list).mockRejectedValueOnce(new ApiError(500, 'Ocorreu um erro inesperado.'))
    const { wrapper } = await mountAt('/empresas/c1/clientes', CustomersView)

    expect(wrapper.find('[role="alert"]').text()).toContain('Ocorreu um erro inesperado.')
    await button(wrapper, 'Tentar novamente')!.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Maria Oliveira')
  })

  it('shows validation errors from the API in the create form', async () => {
    vi.spyOn(customersApi, 'create').mockRejectedValue(
      new ApiError(422, 'Verifique os dados.', { name: ['O campo nome é obrigatório.'] }),
    )
    const { wrapper } = await mountAt('/empresas/c1/clientes', CustomersView)

    await button(wrapper, 'Novo cliente')!.trigger('click')
    await flushPromises()
    await wrapper.find('form#customer-form').trigger('submit')
    await flushPromises()

    expect(customersApi.create).toHaveBeenCalledWith('c1', { name: '', email: null, phone: null, birth_date: null, notes: null })
    expect(document.body.textContent).toContain('O campo nome é obrigatório.')
  })

  it('hides creation from roles that cannot manage customers', async () => {
    const { wrapper } = await mountAt('/empresas/c1/clientes', CustomersView, false)
    expect(button(wrapper, 'Novo cliente')).toBeUndefined()
  })
})

describe('CustomerProfileView', () => {
  beforeEach(() => {
    vi.spyOn(customersApi, 'get').mockResolvedValue(customer())
    vi.spyOn(customersApi, 'history').mockResolvedValue({
      data: [
        {
          id: 'a1',
          status: 'completed',
          status_label: 'Concluído',
          starts_at: '2026-11-02T13:00:00Z',
          ends_at: '2026-11-02T14:00:00Z',
          occupied_until: '2026-11-02T14:00:00Z',
          local: { timezone: 'America/Sao_Paulo', date: '2026-11-02', start: '10:00', end: '11:00' },
          professional: { id: 'p1', name: 'Marina Lopes', color: '#8B5CF6' },
          service: { id: 's1', name: 'Corte', price_cents: 12000, duration_minutes: 60, buffer_minutes: 0 },
          notes: null,
        },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
    })
  })

  it('shows contact, frequency and history', async () => {
    const { wrapper } = await mountAt('/empresas/c1/clientes/cu1', CustomerProfileView)

    expect(customersApi.get).toHaveBeenCalledWith('c1', 'cu1')
    expect(wrapper.find('h1').text()).toContain('Maria Oliveira')
    expect(wrapper.text()).toContain('Corte')
    expect(wrapper.text()).toContain('Concluído')
    // Prices are only for roles with financial access.
    expect(wrapper.text()).not.toContain('R$')
  })

  it('asks for confirmation before archiving', async () => {
    const archive = vi.spyOn(customersApi, 'archive').mockResolvedValue(customer({ is_archived: true }))
    const { wrapper } = await mountAt('/empresas/c1/clientes/cu1', CustomerProfileView)

    await button(wrapper, 'Arquivar')!.trigger('click')
    await flushPromises()
    expect(archive).not.toHaveBeenCalled()

    const confirm = wrapper.findAll('dialog button').find((b) => b.text().trim() === 'Arquivar')!
    await confirm.trigger('click')
    await flushPromises()

    expect(archive).toHaveBeenCalledWith('c1', 'cu1')
    expect(wrapper.find('h1').text()).toContain('Arquivado')
    expect(button(wrapper, 'Reativar')).toBeDefined()
  })
})

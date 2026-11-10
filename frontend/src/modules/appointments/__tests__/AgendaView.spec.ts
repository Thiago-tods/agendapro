import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { bookingApi, type CalendarAvailability } from '@/modules/appointments/api'
import AgendaView from '@/modules/appointments/views/AgendaView.vue'
import { ApiError } from '@/services/http'
import { useAuthStore } from '@/stores/auth'
import type { Appointment, Company } from '@/types/api'

const DAY = '2026-11-16'

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
      role_label: manage ? 'Recepcionista' : 'Profissional',
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

function appointment(overrides: Partial<Appointment> = {}): Appointment {
  return {
    id: 'a1',
    status: 'scheduled',
    status_label: 'Agendado',
    starts_at: '2026-11-16T13:00:00Z',
    ends_at: '2026-11-16T14:00:00Z',
    occupied_until: '2026-11-16T14:15:00Z',
    local: {
      timezone: 'America/Sao_Paulo',
      date: DAY,
      start: '10:00',
      end: '11:00',
    },
    customer: { id: 'cu1', name: 'Maria Oliveira', phone: '11999990000' },
    professional: { id: 'p1', name: 'Marina Lopes', color: '#8B5CF6' },
    service: {
      id: 's1',
      name: 'Corte',
      price_cents: 12000,
      duration_minutes: 60,
      buffer_minutes: 15,
    },
    notes: null,
    events: [],
    ...overrides,
  }
}

const availability: CalendarAvailability = {
  timezone: 'America/Sao_Paulo',
  days: [
    {
      date: DAY,
      company: [
        [540, 720],
        [780, 1080],
      ],
      professionals: {
        p1: [
          [540, 720],
          [780, 1080],
        ],
      },
    },
  ],
  blocked: [
    {
      id: 'b1',
      professional_id: 'p1',
      reason: 'Reunião',
      local_start: `${DAY} 15:00`,
      local_end: `${DAY} 16:00`,
    },
  ],
}

let wrapper: VueWrapper | null = null

async function mountAgenda(
  options: { manage?: boolean; query?: string } = {},
): Promise<{ wrapper: VueWrapper; router: Router }> {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.user = { id: 'u1', name: 'Carla', email: 'c@x.y' }
  auth.companies = [company(options.manage ?? true)]
  auth.status = 'authenticated'

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/empresas/:companyId/agenda', component: AgendaView }],
  })
  await router.push(`/empresas/c1/agenda?${options.query ?? `view=day&date=${DAY}`}`)
  await router.isReady()

  wrapper = mount(AgendaView, {
    attachTo: document.body,
    global: {
      plugins: [pinia, router],
      stubs: {
        NewAppointmentModal: {
          props: ['open', 'initial'],
          template: '<div data-test="booking" :data-open="open" :data-initial="JSON.stringify(initial)" />',
        },
      },
    },
  })
  await flushPromises()
  return { wrapper, router }
}

const button = (w: VueWrapper, text: string) => w.findAll('button').find((b) => b.text().includes(text))

beforeAll(() => {
  // jsdom lacks the modal dialog API.
  HTMLDialogElement.prototype.showModal ??= function (this: HTMLDialogElement) {
    this.setAttribute('open', '')
  }
  HTMLDialogElement.prototype.close ??= function (this: HTMLDialogElement) {
    this.removeAttribute('open')
  }
})

beforeEach(() => {
  vi.spyOn(bookingApi, 'activeProfessionals').mockResolvedValue([
    { id: 'p1', name: 'Marina Lopes', color: '#8B5CF6', is_active: true },
  ])
  vi.spyOn(bookingApi, 'activeServices').mockResolvedValue([])
  vi.spyOn(bookingApi, 'availability').mockResolvedValue(availability)
  vi.spyOn(bookingApi, 'list').mockResolvedValue([appointment()])
  vi.spyOn(bookingApi, 'get').mockResolvedValue(appointment())
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  vi.restoreAllMocks()
})

describe('AgendaView', () => {
  it('loads the visible range and renders blocks, blocked periods and free slots', async () => {
    const { wrapper } = await mountAgenda()

    expect(bookingApi.list).toHaveBeenCalledWith(
      'c1',
      expect.objectContaining({
        from: DAY,
        to: DAY,
        status: ['scheduled', 'confirmed', 'checked_in', 'completed', 'no_show'],
      }),
      expect.anything(),
    )
    const block = wrapper.find('button[aria-label*="Maria Oliveira"]')
    expect(block.attributes('aria-label')).toBe('10:00–11:00 · Maria Oliveira · Corte · Agendado')
    expect(wrapper.text()).toContain('Bloqueado · Reunião')
    // Free slot buttons exist only inside open hours and outside blocks.
    expect(wrapper.find('button[aria-label="Novo agendamento às 09:00, Marina Lopes"]').exists()).toBe(true)
    expect(wrapper.find('button[aria-label="Novo agendamento às 12:00, Marina Lopes"]').exists()).toBe(false)
    expect(wrapper.find('button[aria-label="Novo agendamento às 15:00, Marina Lopes"]').exists()).toBe(false)
  })

  it('opens details and confirms, refreshing the calendar', async () => {
    const confirmed = appointment({
      status: 'confirmed',
      status_label: 'Confirmado',
    })
    const transition = vi.spyOn(bookingApi, 'transition').mockResolvedValue(confirmed)
    const { wrapper } = await mountAgenda()

    await wrapper.find('button[aria-label*="Maria Oliveira"]').trigger('click')
    await flushPromises()
    expect(bookingApi.get).toHaveBeenCalledWith('c1', 'a1')
    expect(document.body.textContent).toContain('Maria Oliveira')

    vi.mocked(bookingApi.list).mockClear()
    await button(wrapper, 'Confirmar')!.trigger('click')
    await flushPromises()

    expect(transition).toHaveBeenCalledWith('c1', 'a1', 'confirm')
    expect(bookingApi.list).toHaveBeenCalledTimes(1)
    expect(document.body.textContent).toContain('Confirmado')
  })

  it('requires a reason before cancelling', async () => {
    const transition = vi
      .spyOn(bookingApi, 'transition')
      .mockResolvedValue(appointment({ status: 'cancelled', status_label: 'Cancelado' }))
    const { wrapper } = await mountAgenda()

    await wrapper.find('button[aria-label*="Maria Oliveira"]').trigger('click')
    await flushPromises()
    await button(wrapper, 'Cancelar')!.trigger('click')

    const submit = button(wrapper, 'Confirmar cancelamento')!
    expect(submit.attributes('disabled')).toBeDefined()

    await wrapper.find('dialog input[type="text"]').setValue('Cliente desmarcou')
    expect(button(wrapper, 'Confirmar cancelamento')!.attributes('disabled')).toBeUndefined()
    await button(wrapper, 'Confirmar cancelamento')!.trigger('submit')
    await flushPromises()

    expect(transition).toHaveBeenCalledWith('c1', 'a1', 'cancel', 'Cliente desmarcou')
  })

  it('shows the API conflict message when rescheduling and does not refresh as if it succeeded', async () => {
    vi.spyOn(bookingApi, 'professionalsFor').mockResolvedValue([
      { id: 'p1', name: 'Marina Lopes', color: '#8B5CF6', is_active: true },
    ])
    const reschedule = vi
      .spyOn(bookingApi, 'reschedule')
      .mockRejectedValue(new ApiError(409, 'O profissional já possui um agendamento neste horário.'))
    const { wrapper } = await mountAgenda()

    await wrapper.find('button[aria-label*="Maria Oliveira"]').trigger('click')
    await flushPromises()
    await button(wrapper, 'Remarcar')!.trigger('click')
    await flushPromises()
    await wrapper.find('dialog input[type="time"]').setValue('14:00')
    vi.mocked(bookingApi.list).mockClear()
    await wrapper.find('dialog form').trigger('submit')
    await flushPromises()

    expect(reschedule).toHaveBeenCalledWith('c1', 'a1', {
      starts_at: `${DAY}T14:00`,
      professional_id: 'p1',
      reason: undefined,
    })
    expect(document.querySelector('dialog [role="alert"]')?.textContent).toContain(
      'O profissional já possui um agendamento neste horário.',
    )
    expect(bookingApi.list).not.toHaveBeenCalled()
  })

  it('shows field errors from the API (422) next to the reschedule form', async () => {
    vi.spyOn(bookingApi, 'professionalsFor').mockResolvedValue([])
    vi.spyOn(bookingApi, 'reschedule').mockRejectedValue(
      new ApiError(422, 'O horário está fora do expediente.', {
        starts_at: ['O horário está fora do expediente.'],
      }),
    )
    const { wrapper } = await mountAgenda()

    await wrapper.find('button[aria-label*="Maria Oliveira"]').trigger('click')
    await flushPromises()
    await button(wrapper, 'Remarcar')!.trigger('click')
    await flushPromises()
    await wrapper.find('dialog form').trigger('submit')
    await flushPromises()

    expect(document.body.textContent).toContain('O horário está fora do expediente.')
  })

  it('opens the booking form prefilled from a free slot', async () => {
    const { wrapper } = await mountAgenda()

    await wrapper.find('button[aria-label="Novo agendamento às 09:00, Marina Lopes"]').trigger('click')
    await flushPromises()

    const booking = wrapper.find('[data-test="booking"]')
    expect(booking.attributes('data-open')).toBe('true')
    expect(JSON.parse(booking.attributes('data-initial') ?? '{}')).toEqual({
      date: DAY,
      time: '09:00',
      professionalId: 'p1',
    })
  })

  it('hides booking and actions from roles that cannot manage appointments', async () => {
    const { wrapper } = await mountAgenda({ manage: false })

    expect(wrapper.find('button[aria-label^="Novo agendamento às"]').exists()).toBe(false)
    expect(button(wrapper, 'Novo agendamento')).toBeUndefined()

    await wrapper.find('button[aria-label*="Maria Oliveira"]').trigger('click')
    await flushPromises()
    expect(button(wrapper, 'Confirmar')).toBeUndefined()
    expect(button(wrapper, 'Cancelar')).toBeUndefined()
  })

  it('navigates between periods and from the month to a day', async () => {
    const { wrapper, router } = await mountAgenda({
      query: `view=week&date=${DAY}`,
    })

    await wrapper.find('button[aria-label="Próximo período"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.date).toBe('2026-11-23')
    expect(bookingApi.list).toHaveBeenLastCalledWith(
      'c1',
      expect.objectContaining({ from: '2026-11-23', to: '2026-11-29' }),
      expect.anything(),
    )

    await router.replace({ query: { view: 'month', date: DAY } })
    await flushPromises()
    await wrapper.find('button[aria-label^="Ver segunda-feira, 16 de novembro"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toMatchObject({
      view: 'day',
      date: DAY,
    })
  })

  it('shows an error state with retry when loading fails', async () => {
    vi.mocked(bookingApi.list).mockRejectedValueOnce(new ApiError(0, 'Não foi possível conectar ao servidor.'))
    const { wrapper } = await mountAgenda()

    expect(wrapper.find('[role="alert"]').text()).toContain('Não foi possível conectar ao servidor.')

    await button(wrapper, 'Tentar novamente')!.trigger('click')
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.find('button[aria-label*="Maria Oliveira"]').exists()).toBe(true)
  })
})

import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import ProfileView from '@/modules/account/views/ProfileView.vue'
import WeeklyHoursEditor from '@/modules/settings/components/WeeklyHoursEditor.vue'
import { accountApi, settingsApi } from '@/modules/settings/api'
import TeamView from '@/modules/settings/views/TeamView.vue'
import { ApiError } from '@/services/http'
import { useAuthStore } from '@/stores/auth'
import type { Company, CompanyMember, CompanyRole, WeeklyPeriod } from '@/types/api'

function company(role: CompanyRole): Company {
  const manage = role === 'owner' || role === 'admin'
  return {
    id: 'c1',
    name: 'Studio',
    slug: 'studio',
    timezone: 'America/Sao_Paulo',
    currency: 'BRL',
    locale: 'pt_BR',
    membership: {
      role,
      role_label: role,
      permissions: {
        manage_company: manage,
        manage_members: manage,
        manage_customers: true,
        manage_catalog: manage,
        manage_availability: manage,
        manage_appointments: true,
        delete_records: manage,
        view_financials: manage,
      },
    },
  }
}

function member(id: string, name: string, role: CompanyRole): CompanyMember {
  return { id, role, role_label: role, user: { id: `u-${id}`, name, email: `${id}@x.y` }, joined_at: '' }
}

let wrapper: VueWrapper | null = null

async function mountView(component: object, role: CompanyRole = 'owner') {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.user = { id: 'u-me', name: 'Ana', email: 'ana@x.y' }
  auth.companies = [company(role)]
  auth.status = 'authenticated'
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/empresas/:companyId/x', component },
      { path: '/empresas/:companyId/:rest(.*)*', name: 'company-settings', component: { render: () => null } },
      { path: '/e/:companyId/1', name: 'business-hours', component: { render: () => null } },
      { path: '/e/:companyId/2', name: 'team', component: { render: () => null } },
      { path: '/e/:companyId/3', name: 'profile', component: { render: () => null } },
    ],
  })
  await router.push('/empresas/c1/x')
  await router.isReady()
  wrapper = mount(component, { attachTo: document.body, global: { plugins: [pinia, router] } })
  await flushPromises()
  return wrapper
}

beforeAll(() => {
  HTMLDialogElement.prototype.showModal ??= function (this: HTMLDialogElement) {
    this.setAttribute('open', '')
  }
  HTMLDialogElement.prototype.close ??= function (this: HTMLDialogElement) {
    this.removeAttribute('open')
  }
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  vi.restoreAllMocks()
})

describe('WeeklyHoursEditor', () => {
  it('opens and closes days and adds a second interval after the first', async () => {
    const periods = ref<WeeklyPeriod[]>([{ weekday: 1, start_time: '09:00', end_time: '12:00' }])
    wrapper = mount(WeeklyHoursEditor, {
      props: { modelValue: periods.value, 'onUpdate:modelValue': (v: WeeklyPeriod[]) => (periods.value = v) },
    })
    const checkboxes = wrapper.findAll('input[type="checkbox"]')
    // Monday first.
    expect((checkboxes[0]!.element as HTMLInputElement).checked).toBe(true)

    await wrapper.findAll('button').find((b) => b.text().includes('Adicionar intervalo'))!.trigger('click')
    expect(periods.value).toContainEqual({ weekday: 1, start_time: '12:00', end_time: '16:00' })

    await checkboxes[1]!.setValue(true)
    expect(periods.value).toContainEqual({ weekday: 2, start_time: '09:00', end_time: '18:00' })

    await wrapper.setProps({ modelValue: periods.value })
    await wrapper.findAll('input[type="checkbox"]')[0]!.setValue(false)
    expect(periods.value.some((p) => p.weekday === 1)).toBe(false)
  })

  it('is read-only when disabled', () => {
    wrapper = mount(WeeklyHoursEditor, {
      props: { modelValue: [{ weekday: 1, start_time: '09:00', end_time: '12:00' }], disabled: true },
    })
    expect(wrapper.findAll('button')).toHaveLength(0)
    expect(wrapper.find('input[type="time"]').attributes('disabled')).toBeDefined()
  })
})

describe('TeamView', () => {
  const team = [member('m1', 'Ana', 'owner'), member('m2', 'Bruno', 'admin'), member('m3', 'Carla', 'receptionist')]

  it('admins cannot touch owners nor grant ownership; their own row is read-only', async () => {
    vi.spyOn(settingsApi, 'members').mockResolvedValue([
      { ...team[0]!, user: { ...team[0]!.user, id: 'u-other' } },
      { ...team[1]!, user: { ...team[1]!.user, id: 'u-me' } },
      team[2]!,
    ])
    const w = await mountView(TeamView, 'admin')

    const selects = w.findAll('select')
    expect(selects).toHaveLength(1) // only Carla
    expect(selects[0]!.findAll('option').map((o) => o.attributes('value'))).not.toContain('owner')
  })

  it('reverts and shows the API message when a role change is refused', async () => {
    vi.spyOn(settingsApi, 'members').mockResolvedValue(team)
    vi.spyOn(settingsApi, 'changeRole').mockRejectedValue(
      new ApiError(422, 'Verifique.', { role: ['A empresa precisa ter pelo menos um proprietário.'] }),
    )
    const w = await mountView(TeamView, 'owner')

    // Demoting the last owner: the API refuses and the select returns to the saved role.
    const select = w.findAll('select')[0]!
    await select.setValue('admin')
    await flushPromises()
    expect(settingsApi.changeRole).toHaveBeenCalledWith('c1', 'm1', 'admin')
    expect((w.findAll('select')[0]!.element as HTMLSelectElement).value).toBe('owner')
  })

  it('adds an existing account without sending name or password', async () => {
    vi.spyOn(settingsApi, 'members').mockResolvedValue(team)
    const add = vi.spyOn(settingsApi, 'addMember').mockResolvedValue(member('m9', 'Zeca', 'professional'))
    const w = await mountView(TeamView, 'owner')

    await w.findAll('button').find((b) => b.text() === 'Adicionar pessoa')!.trigger('click')
    await flushPromises()
    await w.find('dialog input[type="email"]').setValue('zeca@x.y')
    await w.find('dialog select').setValue('professional')
    await w.find('dialog input[type="checkbox"]').setValue(false)
    await w.find('form#member-form').trigger('submit')
    await flushPromises()

    expect(add).toHaveBeenCalledWith('c1', { email: 'zeca@x.y', role: 'professional' })
    expect(w.text()).toContain('Zeca')
  })
})

describe('ProfileView', () => {
  it('shows the wrong current password message and clears fields on success', async () => {
    const update = vi
      .spyOn(accountApi, 'updatePassword')
      .mockRejectedValueOnce(new ApiError(422, 'Verifique.', { current_password: ['A senha atual está incorreta.'] }))
      .mockResolvedValueOnce(undefined)
    const w = await mountView(ProfileView)
    const [current, next, confirmation] = w.findAll('input[type="password"]')

    await current!.setValue('errada')
    await next!.setValue('nova12345')
    await confirmation!.setValue('nova12345')
    const form = w.findAll('form')[1]!
    await form.trigger('submit')
    await flushPromises()
    expect(w.text()).toContain('A senha atual está incorreta.')

    await form.trigger('submit')
    await flushPromises()
    expect(update).toHaveBeenCalledTimes(2)
    expect((current!.element as HTMLInputElement).value).toBe('')
  })

  it('updates the signed-in user after saving the profile', async () => {
    vi.spyOn(accountApi, 'updateProfile').mockResolvedValue({ id: 'u-me', name: 'Ana Souza', email: 'ana@x.y' })
    const w = await mountView(ProfileView)

    await w.find('input[autocomplete="name"]').setValue('Ana Souza')
    await w.findAll('form')[0]!.trigger('submit')
    await flushPromises()

    expect(accountApi.updateProfile).toHaveBeenCalledWith({ name: 'Ana Souza', email: 'ana@x.y' })
    expect(useAuthStore().user?.name).toBe('Ana Souza')
  })
})

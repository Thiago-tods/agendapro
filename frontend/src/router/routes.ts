import type { RouteRecordRaw } from 'vue-router'
import type { Permission } from '@/types/api'

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
    /** Only for signed-in users. */
    requiresAuth?: boolean
    /** Only for visitors (e.g. the login page). */
    guestOnly?: boolean
    /**
     * Hides a page the backend would refuse anyway. A UX shortcut, not a security boundary:
     * every API call behind the page is authorized by the backend.
     */
    permission?: Permission
  }
}

export const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'root',
    // Resolved by the guard: last company, company picker or login.
    component: { render: () => null },
  },
  {
    path: '/entrar',
    name: 'login',
    component: () => import('@/modules/auth/views/LoginView.vue'),
    meta: { title: 'Entrar', guestOnly: true },
  },
  {
    path: '/empresas',
    name: 'company-select',
    component: () => import('@/modules/companies/views/CompanySelectView.vue'),
    meta: { title: 'Selecionar empresa', requiresAuth: true },
  },
  {
    path: '/empresas/:companyId',
    component: () => import('@/modules/companies/views/CompanyShell.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'company-home',
        component: () => import('@/modules/dashboard/views/DashboardView.vue'),
        meta: { title: 'Painel' },
      },
      {
        path: 'agenda',
        name: 'agenda',
        component: () => import('@/modules/appointments/views/AgendaView.vue'),
        meta: { title: 'Agenda' },
      },
      {
        path: 'clientes',
        name: 'customers',
        component: () => import('@/modules/customers/views/CustomersView.vue'),
        meta: { title: 'Clientes' },
      },
      {
        path: 'clientes/:customerId',
        name: 'customer-profile',
        component: () => import('@/modules/customers/views/CustomerProfileView.vue'),
        meta: { title: 'Cliente' },
      },
      {
        path: 'profissionais',
        name: 'professionals',
        component: () => import('@/modules/professionals/views/ProfessionalsView.vue'),
        meta: { title: 'Profissionais' },
      },
      {
        path: 'profissionais/:professionalId',
        name: 'professional-detail',
        component: () => import('@/modules/professionals/views/ProfessionalDetailView.vue'),
        meta: { title: 'Profissional' },
      },
      {
        path: 'servicos',
        name: 'services',
        component: () => import('@/modules/services/views/ServicesView.vue'),
        meta: { title: 'Serviços' },
      },
      {
        path: 'configuracoes/empresa',
        name: 'company-settings',
        component: () => import('@/modules/companies/views/CompanySettingsView.vue'),
        meta: { title: 'Dados da empresa' },
      },
      {
        path: 'configuracoes/expediente',
        name: 'business-hours',
        component: () => import('@/modules/settings/views/BusinessHoursView.vue'),
        meta: { title: 'Expediente' },
      },
      {
        path: 'configuracoes/equipe',
        name: 'team',
        component: () => import('@/modules/settings/views/TeamView.vue'),
        meta: { title: 'Equipe e permissões', permission: 'manage_members' },
      },
      {
        path: 'configuracoes/perfil',
        name: 'profile',
        component: () => import('@/modules/account/views/ProfileView.vue'),
        meta: { title: 'Meu perfil' },
      },
    ],
  },
  ...(import.meta.env.DEV
    ? [
        {
          // Development-only reference of the visual foundation.
          path: '/design-system',
          name: 'design-system',
          component: () => import('@/views/DesignSystemView.vue'),
          meta: { title: 'Componentes' },
        } satisfies RouteRecordRaw,
      ]
    : []),
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

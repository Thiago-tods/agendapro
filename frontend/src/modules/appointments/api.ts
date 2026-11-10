import { http } from '@/services/http'
import type {
  Appointment,
  AppointmentStatus,
  CustomerSummary,
  DataEnvelope,
  Paginated,
  ProfessionalSummary,
  ServiceSummary,
} from '@/types/api'
import type { Range } from './calendar/layout'

export interface NewAppointmentPayload {
  customer_id: string
  professional_id: string
  service_id: string
  /** Local wall-clock time "YYYY-MM-DDTHH:mm"; the API reads it in the company's timezone. */
  starts_at: string
  notes?: string
}

export interface ReschedulePayload {
  starts_at: string
  professional_id?: string
  reason?: string
}

export interface CalendarAvailability {
  timezone: string
  days: {
    date: string
    company: Range[]
    professionals: Record<string, Range[]>
  }[]
  blocked: {
    id: string
    professional_id: string | null
    reason: string | null
    local_start: string
    local_end: string
  }[]
}

export interface AppointmentQuery {
  from: string
  to: string
  professional_id?: string
  service_id?: string
  status?: AppointmentStatus[]
}

const base = (companyId: string) => `/companies/${companyId}`

/** Booking and agenda endpoints. Every rule is validated by the API; the UI only displays results. */
export const bookingApi = {
  async create(companyId: string, payload: NewAppointmentPayload): Promise<Appointment> {
    return (await http.post<DataEnvelope<Appointment>>(`${base(companyId)}/appointments`, payload)).data
  },

  async list(companyId: string, query: AppointmentQuery, signal?: AbortSignal): Promise<Appointment[]> {
    return (await http.get<DataEnvelope<Appointment[]>>(`${base(companyId)}/appointments`, { ...query }, signal)).data
  },

  async get(companyId: string, id: string): Promise<Appointment> {
    return (await http.get<DataEnvelope<Appointment>>(`${base(companyId)}/appointments/${id}`)).data
  },

  async transition(
    companyId: string,
    id: string,
    action: 'confirm' | 'check-in' | 'complete' | 'no-show' | 'cancel',
    reason?: string,
  ): Promise<Appointment> {
    const body = reason ? { reason } : {}
    return (await http.post<DataEnvelope<Appointment>>(`${base(companyId)}/appointments/${id}/${action}`, body)).data
  },

  async reschedule(companyId: string, id: string, payload: ReschedulePayload): Promise<Appointment> {
    return (await http.post<DataEnvelope<Appointment>>(`${base(companyId)}/appointments/${id}/reschedule`, payload))
      .data
  },

  async availability(
    companyId: string,
    query: { from: string; to: string; professional_ids?: string[] },
    signal?: AbortSignal,
  ): Promise<CalendarAvailability> {
    return (
      await http.get<DataEnvelope<CalendarAvailability>>(`${base(companyId)}/calendar/availability`, query, signal)
    ).data
  },

  async activeServices(companyId: string): Promise<ServiceSummary[]> {
    return (
      await http.get<Paginated<ServiceSummary>>(`${base(companyId)}/services`, {
        status: 'active',
        per_page: 100,
      })
    ).data
  },

  async activeProfessionals(companyId: string): Promise<ProfessionalSummary[]> {
    return (
      await http.get<Paginated<ProfessionalSummary>>(`${base(companyId)}/professionals`, {
        status: 'active',
        per_page: 100,
      })
    ).data
  },

  async professionalsFor(companyId: string, serviceId: string): Promise<ProfessionalSummary[]> {
    return (
      await http.get<Paginated<ProfessionalSummary>>(`${base(companyId)}/professionals`, {
        status: 'active',
        service_id: serviceId,
        per_page: 100,
      })
    ).data
  },

  async searchCustomers(companyId: string, search: string, signal?: AbortSignal): Promise<CustomerSummary[]> {
    return (
      await http.get<Paginated<CustomerSummary>>(
        `${base(companyId)}/customers`,
        { search, status: 'active', per_page: 8 },
        signal,
      )
    ).data
  },
}

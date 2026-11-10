import { http } from '@/services/http'
import type {
  Appointment,
  CustomerSummary,
  DataEnvelope,
  Paginated,
  ProfessionalSummary,
  ServiceSummary,
} from '@/types/api'

export interface NewAppointmentPayload {
  customer_id: string
  professional_id: string
  service_id: string
  /** Local wall-clock time "YYYY-MM-DDTHH:mm"; the API reads it in the company's timezone. */
  starts_at: string
  notes?: string
}

/** Endpoints used by the booking form. Business rules are validated by the API. */
export const bookingApi = {
  async create(companyId: string, payload: NewAppointmentPayload): Promise<Appointment> {
    return (await http.post<DataEnvelope<Appointment>>(`/companies/${companyId}/appointments`, payload)).data
  },

  async activeServices(companyId: string): Promise<ServiceSummary[]> {
    return (
      await http.get<Paginated<ServiceSummary>>(`/companies/${companyId}/services`, { status: 'active', per_page: 100 })
    ).data
  },

  async professionalsFor(companyId: string, serviceId: string): Promise<ProfessionalSummary[]> {
    return (
      await http.get<Paginated<ProfessionalSummary>>(`/companies/${companyId}/professionals`, {
        status: 'active',
        service_id: serviceId,
        per_page: 100,
      })
    ).data
  },

  async searchCustomers(companyId: string, search: string, signal?: AbortSignal): Promise<CustomerSummary[]> {
    return (
      await http.get<Paginated<CustomerSummary>>(
        `/companies/${companyId}/customers`,
        { search, status: 'active', per_page: 8 },
        signal,
      )
    ).data
  },
}

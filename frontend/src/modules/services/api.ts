import { http } from '@/services/http'
import type { DataEnvelope, Paginated, Service } from '@/types/api'

export interface ServiceQuery {
  search?: string
  status?: 'active' | 'inactive' | 'all'
  category?: string
  page?: number
  per_page?: number
}

export interface ServicePayload {
  name: string
  description: string | null
  category: string | null
  price_cents: number
  duration_minutes: number
  buffer_minutes: number
  color: string
  is_active: boolean
  professional_ids: string[]
}

const base = (companyId: string) => `/companies/${companyId}/services`

export const servicesApi = {
  list: (companyId: string, query: ServiceQuery, signal?: AbortSignal) =>
    http.get<Paginated<Service>>(base(companyId), { ...query }, signal),

  async get(companyId: string, id: string): Promise<Service> {
    return (await http.get<DataEnvelope<Service>>(`${base(companyId)}/${id}`)).data
  },

  async create(companyId: string, payload: ServicePayload): Promise<Service> {
    return (await http.post<DataEnvelope<Service>>(base(companyId), payload)).data
  },

  async update(companyId: string, id: string, payload: Partial<ServicePayload>): Promise<Service> {
    return (await http.patch<DataEnvelope<Service>>(`${base(companyId)}/${id}`, payload)).data
  },
}

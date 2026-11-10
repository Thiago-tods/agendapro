import { http } from '@/services/http'
import type { Appointment, Customer, DataEnvelope, Paginated } from '@/types/api'

export type CustomerStatusFilter = 'active' | 'archived' | 'all'

export interface CustomerQuery {
  search?: string
  status?: CustomerStatusFilter
  page?: number
  per_page?: number
}

export interface CustomerPayload {
  name: string
  email: string | null
  phone: string | null
  birth_date: string | null
  notes: string | null
}

const base = (companyId: string) => `/companies/${companyId}/customers`

export const customersApi = {
  list: (companyId: string, query: CustomerQuery, signal?: AbortSignal) =>
    http.get<Paginated<Customer>>(base(companyId), { ...query }, signal),

  async get(companyId: string, id: string): Promise<Customer> {
    return (await http.get<DataEnvelope<Customer>>(`${base(companyId)}/${id}`)).data
  },

  async create(companyId: string, payload: CustomerPayload): Promise<Customer> {
    return (await http.post<DataEnvelope<Customer>>(base(companyId), payload)).data
  },

  async update(companyId: string, id: string, payload: Partial<CustomerPayload>): Promise<Customer> {
    return (await http.patch<DataEnvelope<Customer>>(`${base(companyId)}/${id}`, payload)).data
  },

  async archive(companyId: string, id: string): Promise<Customer> {
    return (await http.post<DataEnvelope<Customer>>(`${base(companyId)}/${id}/archive`)).data
  },

  async restore(companyId: string, id: string): Promise<Customer> {
    return (await http.post<DataEnvelope<Customer>>(`${base(companyId)}/${id}/restore`)).data
  },

  history: (companyId: string, id: string, page: number) =>
    http.get<Paginated<Appointment>>(`${base(companyId)}/${id}/appointments`, {
      page,
      per_page: 10,
    }),
}

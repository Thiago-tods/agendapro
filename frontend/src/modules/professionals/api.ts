import { http } from '@/services/http'
import type { DataEnvelope, Paginated, Professional, WeeklyHours, WeeklyPeriod } from '@/types/api'

export interface ProfessionalQuery {
  search?: string
  status?: 'active' | 'inactive' | 'all'
  page?: number
  per_page?: number
}

export interface ProfessionalPayload {
  name: string
  title: string | null
  email: string | null
  phone: string | null
  bio: string | null
  color: string
  is_active: boolean
  service_ids: string[]
}

const base = (companyId: string) => `/companies/${companyId}/professionals`

export const professionalsApi = {
  list: (companyId: string, query: ProfessionalQuery, signal?: AbortSignal) =>
    http.get<Paginated<Professional>>(base(companyId), { ...query }, signal),

  async get(companyId: string, id: string): Promise<Professional> {
    return (await http.get<DataEnvelope<Professional>>(`${base(companyId)}/${id}`)).data
  },

  async create(companyId: string, payload: ProfessionalPayload): Promise<Professional> {
    return (await http.post<DataEnvelope<Professional>>(base(companyId), payload)).data
  },

  async update(companyId: string, id: string, payload: Partial<ProfessionalPayload>): Promise<Professional> {
    return (await http.patch<DataEnvelope<Professional>>(`${base(companyId)}/${id}`, payload)).data
  },

  async workingHours(companyId: string, id: string): Promise<WeeklyHours> {
    return (await http.get<DataEnvelope<WeeklyHours>>(`${base(companyId)}/${id}/working-hours`)).data
  },

  async saveWorkingHours(companyId: string, id: string, periods: WeeklyPeriod[]): Promise<WeeklyHours> {
    return (await http.put<DataEnvelope<WeeklyHours>>(`${base(companyId)}/${id}/working-hours`, { periods })).data
  },
}

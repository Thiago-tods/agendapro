import { http } from '@/services/http'
import type { AuthUser, CompanyMember, CompanyRole, DataEnvelope, WeeklyHours, WeeklyPeriod } from '@/types/api'

export interface NewMemberPayload {
  email: string
  role: CompanyRole
  /** Only for people without an account yet. */
  name?: string
  password?: string
}

const base = (companyId: string) => `/companies/${companyId}`

export const settingsApi = {
  async businessHours(companyId: string): Promise<WeeklyHours> {
    return (await http.get<DataEnvelope<WeeklyHours>>(`${base(companyId)}/business-hours`)).data
  },

  async saveBusinessHours(companyId: string, periods: WeeklyPeriod[]): Promise<WeeklyHours> {
    return (await http.put<DataEnvelope<WeeklyHours>>(`${base(companyId)}/business-hours`, { periods })).data
  },

  async members(companyId: string): Promise<CompanyMember[]> {
    return (await http.get<DataEnvelope<CompanyMember[]>>(`${base(companyId)}/members`)).data
  },

  async addMember(companyId: string, payload: NewMemberPayload): Promise<CompanyMember> {
    return (await http.post<DataEnvelope<CompanyMember>>(`${base(companyId)}/members`, payload)).data
  },

  async changeRole(companyId: string, memberId: string, role: CompanyRole): Promise<CompanyMember> {
    return (await http.patch<DataEnvelope<CompanyMember>>(`${base(companyId)}/members/${memberId}`, { role })).data
  },

  removeMember: (companyId: string, memberId: string) => http.delete(`${base(companyId)}/members/${memberId}`),
}

export const accountApi = {
  async updateProfile(payload: { name: string; email: string; current_password?: string }): Promise<AuthUser> {
    return (await http.patch<DataEnvelope<AuthUser>>('/auth/profile', payload)).data
  },

  updatePassword: (payload: { current_password: string; password: string; password_confirmation: string }) =>
    http.put<void>('/auth/password', payload),
}

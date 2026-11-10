import { http } from '@/services/http'
import type { DataEnvelope, SessionPayload } from '@/types/api'

export interface LoginCredentials {
  email: string
  password: string
  remember: boolean
}

export const authApi = {
  async login(credentials: LoginCredentials): Promise<SessionPayload> {
    return (await http.post<DataEnvelope<SessionPayload>>('/auth/login', credentials)).data
  },

  async me(): Promise<SessionPayload> {
    return (await http.get<DataEnvelope<SessionPayload>>('/auth/me')).data
  },

  logout(): Promise<void> {
    return http.post<void>('/auth/logout')
  },
}

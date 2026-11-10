import { http } from '@/services/http'
import type { DataEnvelope } from '@/types/api'
import type { Dashboard } from './types'

export const dashboardApi = {
  async get(companyId: string, signal?: AbortSignal): Promise<Dashboard> {
    return (await http.get<DataEnvelope<Dashboard>>(`/companies/${companyId}/dashboard`, undefined, signal)).data
  },
}

import { http } from '@/services/http'
import type { Company, DataEnvelope } from '@/types/api'

export interface CompanyUpdatePayload {
  name?: string
  timezone?: string
}

export const companiesApi = {
  async update(companyId: string, payload: CompanyUpdatePayload): Promise<Company> {
    return (await http.patch<DataEnvelope<Company>>(`/companies/${companyId}`, payload)).data
  },
}

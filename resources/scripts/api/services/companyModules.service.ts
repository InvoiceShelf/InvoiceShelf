import { client } from '../client'
import type { CompanyModuleSummary } from '@/scripts/features/company/modules/store'

export interface CompanyModulesListResponse {
  data: CompanyModuleSummary[]
}

export const companyModulesService = {
  async list(): Promise<CompanyModulesListResponse> {
    const { data } = await client.get('/api/v1/company-modules')
    return data
  },
}

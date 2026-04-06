import { ref } from 'vue'
import type { Ref } from 'vue'
import { taxTypeService } from '@v2/api/services/tax-type.service'
import type { TaxType } from '@v2/types/domain/tax'
import { handleApiError } from '@v2/utils/error-handling'

export function useTaxTypes(): {
  taxTypes: Ref<TaxType[]>
  fetchTaxTypes: () => Promise<TaxType[]>
} {
  const taxTypes = ref<TaxType[]>([])

  async function fetchTaxTypes(): Promise<TaxType[]> {
    try {
      const response = await taxTypeService.list({ limit: 'all' })
      taxTypes.value = response.data
      return taxTypes.value
    } catch (error: unknown) {
      handleApiError(error)
      throw error
    }
  }

  return {
    taxTypes,
    fetchTaxTypes,
  }
}

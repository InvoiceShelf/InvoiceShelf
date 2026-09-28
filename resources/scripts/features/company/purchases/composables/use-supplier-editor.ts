import { reactive, ref, onUnmounted } from 'vue'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { purchaseService } from '@/scripts/api/services/purchase.service'
import type {
  SupplierDraft,
  SupplierAddress,
  PurchaseOptions,
  PurchaseRecord,
} from '@/scripts/types/domain/purchase'
import { usePurchaseForm } from './use-purchase-form'
import {
  purchaseCustomFields,
  purchaseCustomFieldPayload,
  usePurchaseCustomFieldValidation,
} from './use-purchase-custom-fields'
export const emptySupplierAddress = (): SupplierAddress => ({
  address_street_1: '',
  address_street_2: '',
  city: '',
  state: '',
  zip: '',
  country_id: null,
})
export function useSupplierEditor() {
  const company = useCompanyStore(),
    global = useGlobalStore()
  const validation = usePurchaseForm()
  const customValidation = usePurchaseCustomFieldValidation()
  const blank = (): SupplierDraft => ({
    customFields: [],
    name: '',
    contact_name: '',
    email: '',
    phone: '',
    website: '',
    tax_id: '',
    currency_id: company.selectedCompanyCurrency?.id ?? null,
    payment_terms: 30,
    expense_category_id: null,
    enabled: true,
    notes: '',
    addresses: [emptySupplierAddress()],
  })
  const form = reactive<SupplierDraft>(blank())
  const options = ref<PurchaseOptions>({
    currencies: [],
    categories: [],
    taxes: [],
    payment_methods: [],
  })
  const loading = ref(false),
    ready = ref(false),
    saving = ref(false)
  let request = 0
  async function load(recordOrId?: PurchaseRecord | number | null) {
    const current = ++request
    loading.value = true
    ready.value = false
    validation.clearError()
    customValidation.reset()
    Object.assign(form, blank())
    try {
      const [lookups, , supplier] = await Promise.all([
        purchaseService.options('Supplier'),
        global.fetchCountries(),
        typeof recordOrId === 'number' || (recordOrId?.id && !recordOrId.fields)
          ? purchaseService.get(
              'suppliers',
              typeof recordOrId === 'number' ? recordOrId : recordOrId!.id,
            )
          : Promise.resolve(recordOrId),
      ])
      if (current !== request) return
      options.value = lookups
      if (supplier) {
        const defaults = blank()
        for (const key of Object.keys(defaults) as Array<keyof SupplierDraft>) {
          if (key === 'customFields') continue
          if (key === 'addresses')
            form.addresses = supplier.addresses?.length
              ? supplier.addresses.map((address) => ({
                  ...emptySupplierAddress(),
                  ...address,
                }))
              : [emptySupplierAddress()]
          else Object.assign(form, { [key]: supplier[key] ?? defaults[key] })
        }
      }
      form.customFields = purchaseCustomFields(
        lookups.custom_fields || [],
        supplier?.fields || [],
      )
      ready.value = true
    } catch (error) {
      if (current === request) validation.setError(error)
    } finally {
      if (current === request) loading.value = false
    }
  }
  async function save(id?: number) {
    if (!ready.value || saving.value) return
    validation.clearError()
    if (!(await customValidation.validate())) return
    saving.value = true
    try {
      return await purchaseService.save(
        'suppliers',
        {
          ...form,
          customFields: purchaseCustomFieldPayload(form.customFields),
          addresses: form.addresses.map((address) => ({ ...address })),
        },
        id,
      )
    } catch (error) {
      validation.setError(error)
    } finally {
      saving.value = false
    }
  }
  onUnmounted(() => request++)
  return {
    customFieldScope: customValidation.scope,
    form,
    options,
    loading,
    ready,
    saving,
    load,
    save,
    ...validation,
  }
}

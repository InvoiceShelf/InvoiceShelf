import { computed } from 'vue'
import { useUserStore } from '@/scripts/stores/user.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { ABILITIES } from '@/scripts/config/abilities'

export interface CreateAction {
  label: string
  icon: string
  to: string
}

const ACTIONS: Array<CreateAction & { ability: string }> = [
  { label: 'invoices.new_invoice', icon: 'DocumentTextIcon', to: '/admin/invoices/create', ability: ABILITIES.CREATE_INVOICE },
  { label: 'estimates.new_estimate', icon: 'DocumentIcon', to: '/admin/estimates/create', ability: ABILITIES.CREATE_ESTIMATE },
  { label: 'payments.new_payment', icon: 'CreditCardIcon', to: '/admin/payments/create', ability: ABILITIES.CREATE_PAYMENT },
  { label: 'expenses.new_expense', icon: 'CalculatorIcon', to: '/admin/expenses/create', ability: ABILITIES.CREATE_EXPENSE },
  { label: 'customers.new_customer', icon: 'UserIcon', to: '/admin/customers/create', ability: ABILITIES.CREATE_CUSTOMER },
]

/**
 * The "New …" actions the current user may take, for the header's New menu
 * and the search palette. Empty in administration mode, which has no company
 * to create documents in.
 */
export function useCreateActions() {
  const userStore = useUserStore()
  const companyStore = useCompanyStore()

  const createActions = computed<CreateAction[]>(() => {
    if (companyStore.isAdminMode) {
      return []
    }

    return ACTIONS.filter((action) => userStore.hasAbilities(action.ability)).map(
      ({ label, icon, to }) => ({ label, icon, to }),
    )
  })

  return { createActions }
}

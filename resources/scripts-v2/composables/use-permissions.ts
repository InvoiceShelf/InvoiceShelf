import { ref, computed } from 'vue'
import type { Ref, ComputedRef } from 'vue'
import { ABILITIES } from '@v2/config/abilities'
import type { Ability } from '@v2/config/abilities'

export interface UserAbility {
  name: string
  [key: string]: unknown
}

export interface UsePermissionsReturn {
  currentAbilities: Ref<UserAbility[]>
  isOwner: Ref<boolean>
  isSuperAdmin: Ref<boolean>
  setAbilities: (abilities: UserAbility[]) => void
  setOwner: (owner: boolean) => void
  setSuperAdmin: (superAdmin: boolean) => void
  hasAbility: (ability: Ability | Ability[]) => boolean
  hasAllAbilities: (abilities: Ability[]) => boolean
  canViewCustomer: ComputedRef<boolean>
  canCreateCustomer: ComputedRef<boolean>
  canEditCustomer: ComputedRef<boolean>
  canDeleteCustomer: ComputedRef<boolean>
  canViewInvoice: ComputedRef<boolean>
  canCreateInvoice: ComputedRef<boolean>
  canEditInvoice: ComputedRef<boolean>
  canDeleteInvoice: ComputedRef<boolean>
  canViewEstimate: ComputedRef<boolean>
  canCreateEstimate: ComputedRef<boolean>
  canEditEstimate: ComputedRef<boolean>
  canDeleteEstimate: ComputedRef<boolean>
  canViewPayment: ComputedRef<boolean>
  canCreatePayment: ComputedRef<boolean>
  canEditPayment: ComputedRef<boolean>
  canDeletePayment: ComputedRef<boolean>
  canViewExpense: ComputedRef<boolean>
  canCreateExpense: ComputedRef<boolean>
  canEditExpense: ComputedRef<boolean>
  canDeleteExpense: ComputedRef<boolean>
  canViewDashboard: ComputedRef<boolean>
  canViewFinancialReport: ComputedRef<boolean>
}

const currentAbilities = ref<UserAbility[]>([])
const isOwner = ref<boolean>(false)
const isSuperAdmin = ref<boolean>(false)

/**
 * Composable for managing user permissions and abilities.
 * Extracted from the user store's hasAbilities/hasAllAbilities logic,
 * with typed convenience computed properties for common CRUD checks.
 */
export function usePermissions(): UsePermissionsReturn {
  function setAbilities(abilities: UserAbility[]): void {
    currentAbilities.value = abilities
  }

  function setOwner(owner: boolean): void {
    isOwner.value = owner
  }

  function setSuperAdmin(superAdmin: boolean): void {
    isSuperAdmin.value = superAdmin
  }

  /**
   * Check if the current user has a given ability or any of the given abilities.
   * A wildcard ability ('*') grants access to everything.
   *
   * @param ability - A single ability string or array of abilities
   * @returns True if the user has the ability
   */
  function hasAbility(ability: Ability | Ability[]): boolean {
    return !!currentAbilities.value.find((ab) => {
      if (ab.name === '*') return true

      if (typeof ability === 'string') {
        return ab.name === ability
      }

      return !!ability.find((p) => ab.name === p)
    })
  }

  /**
   * Check if the current user has ALL of the given abilities.
   *
   * @param abilities - Array of abilities that must all be present
   * @returns True if the user has every listed ability
   */
  function hasAllAbilities(abilities: Ability[]): boolean {
    return abilities.every((ability) =>
      currentAbilities.value.some(
        (ab) => ab.name === '*' || ab.name === ability
      )
    )
  }

  // Convenience computed properties for common permission checks
  const canViewCustomer = computed<boolean>(() => hasAbility(ABILITIES.VIEW_CUSTOMER))
  const canCreateCustomer = computed<boolean>(() => hasAbility(ABILITIES.CREATE_CUSTOMER))
  const canEditCustomer = computed<boolean>(() => hasAbility(ABILITIES.EDIT_CUSTOMER))
  const canDeleteCustomer = computed<boolean>(() => hasAbility(ABILITIES.DELETE_CUSTOMER))

  const canViewInvoice = computed<boolean>(() => hasAbility(ABILITIES.VIEW_INVOICE))
  const canCreateInvoice = computed<boolean>(() => hasAbility(ABILITIES.CREATE_INVOICE))
  const canEditInvoice = computed<boolean>(() => hasAbility(ABILITIES.EDIT_INVOICE))
  const canDeleteInvoice = computed<boolean>(() => hasAbility(ABILITIES.DELETE_INVOICE))

  const canViewEstimate = computed<boolean>(() => hasAbility(ABILITIES.VIEW_ESTIMATE))
  const canCreateEstimate = computed<boolean>(() => hasAbility(ABILITIES.CREATE_ESTIMATE))
  const canEditEstimate = computed<boolean>(() => hasAbility(ABILITIES.EDIT_ESTIMATE))
  const canDeleteEstimate = computed<boolean>(() => hasAbility(ABILITIES.DELETE_ESTIMATE))

  const canViewPayment = computed<boolean>(() => hasAbility(ABILITIES.VIEW_PAYMENT))
  const canCreatePayment = computed<boolean>(() => hasAbility(ABILITIES.CREATE_PAYMENT))
  const canEditPayment = computed<boolean>(() => hasAbility(ABILITIES.EDIT_PAYMENT))
  const canDeletePayment = computed<boolean>(() => hasAbility(ABILITIES.DELETE_PAYMENT))

  const canViewExpense = computed<boolean>(() => hasAbility(ABILITIES.VIEW_EXPENSE))
  const canCreateExpense = computed<boolean>(() => hasAbility(ABILITIES.CREATE_EXPENSE))
  const canEditExpense = computed<boolean>(() => hasAbility(ABILITIES.EDIT_EXPENSE))
  const canDeleteExpense = computed<boolean>(() => hasAbility(ABILITIES.DELETE_EXPENSE))

  const canViewDashboard = computed<boolean>(() => hasAbility(ABILITIES.DASHBOARD))
  const canViewFinancialReport = computed<boolean>(() => hasAbility(ABILITIES.VIEW_FINANCIAL_REPORT))

  return {
    currentAbilities,
    isOwner,
    isSuperAdmin,
    setAbilities,
    setOwner,
    setSuperAdmin,
    hasAbility,
    hasAllAbilities,
    canViewCustomer,
    canCreateCustomer,
    canEditCustomer,
    canDeleteCustomer,
    canViewInvoice,
    canCreateInvoice,
    canEditInvoice,
    canDeleteInvoice,
    canViewEstimate,
    canCreateEstimate,
    canEditEstimate,
    canDeleteEstimate,
    canViewPayment,
    canCreatePayment,
    canEditPayment,
    canDeletePayment,
    canViewExpense,
    canCreateExpense,
    canEditExpense,
    canDeleteExpense,
    canViewDashboard,
    canViewFinancialReport,
  }
}

<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useCustomerStore } from '../store'
import { useUserStore } from '../../../../stores/user.store'
import CustomerDropdown from '../components/CustomerDropdown.vue'
import CustomerViewSidebar from '@/scripts/features/company/customers/components/CustomerViewSidebar.vue'
import CustomerChart from '@/scripts/features/company/customers/components/CustomerChart.vue'
import CustomerStatement from '@/scripts/features/company/customers/components/CustomerStatement.vue'
import type { Customer } from '@/scripts/types/domain/customer'

const ABILITIES = {
  EDIT_CUSTOMER: 'edit-customer',
  DELETE_CUSTOMER: 'delete-customer',
  CREATE_ESTIMATE: 'create-estimate',
  CREATE_INVOICE: 'create-invoice',
  CREATE_PAYMENT: 'create-payment',
  CREATE_EXPENSE: 'create-expense',
  VIEW_CUSTOMER: 'view-customer',
  VIEW_FINANCIAL_REPORTS: 'view-financial-reports',
} as const

const customerStore = useCustomerStore()
const userStore = useUserStore()

const router = useRouter()
const route = useRoute()

const pageTitle = computed<string>(() => {
  return customerStore.selectedViewCustomer.name ?? ''
})

const isLoading = computed<boolean>(() => customerStore.isFetchingViewData)

// The quiet line under the name: who to talk to, or how to reach them
const subtitle = computed<string>(() => {
  const customer = customerStore.selectedViewCustomer
  return customer.contact_name || customer.email || ''
})

const initials = computed<string>(() => {
  const words = (customerStore.selectedViewCustomer.name ?? '').trim().split(/\s+/)
  return words.slice(0, 2).map((word) => word.charAt(0).toUpperCase()).join('')
})

const customerCurrency = computed(() => customerStore.selectedViewCustomer.currency)
const selectedCustomer = computed<Customer | null>(() => (
  customerStore.selectedViewCustomer.id
    ? customerStore.selectedViewCustomer as Customer
    : null
))
const invoiceDueAmount = computed(() => customerStore.selectedViewCustomer.invoice_due_amount ?? customerStore.selectedViewCustomer.due_amount ?? 0)
const availableCredit = computed(() => customerStore.selectedViewCustomer.available_credit ?? 0)
const accountBalance = computed(() => customerStore.selectedViewCustomer.account_balance ?? (invoiceDueAmount.value - availableCredit.value))
const canViewStatement = computed(() => {
  return userStore.hasAbilities(ABILITIES.VIEW_CUSTOMER)
    && userStore.hasAbilities(ABILITIES.VIEW_FINANCIAL_REPORTS)
})

watch(
  () => route.params.id,
  (id) => {
    if (id) {
      void customerStore.fetchViewCustomer({ id: Number(id) })
    }
  },
  { immediate: true },
)

function canCreateTransaction(): boolean {
  return userStore.hasAbilities([
    ABILITIES.CREATE_ESTIMATE,
    ABILITIES.CREATE_INVOICE,
    ABILITIES.CREATE_PAYMENT,
    ABILITIES.CREATE_EXPENSE,
  ])
}

function hasAtleastOneAbility(): boolean {
  return userStore.hasAbilities([
    ABILITIES.DELETE_CUSTOMER,
    ABILITIES.EDIT_CUSTOMER,
  ])
}

function refreshData(): void {
  router.push('/admin/customers')
}
</script>

<template>
  <div class="flex min-h-full">

    <BasePage class="min-w-0">
      <BasePageHeader :title="pageTitle" :subtitle="subtitle">
        <template #leading>
          <span
            class="flex items-center justify-center w-12 h-12 text-base font-semibold rounded-2xl shrink-0 bg-primary-600 text-on-primary"
            aria-hidden="true"
          >
            {{ initials }}
          </span>
        </template>

        <BaseBreadcrumb>
          <BaseBreadcrumbItem :title="$t('customers.customer', 2)" to="/admin/customers" />
        </BaseBreadcrumb>

        <template #actions>
          <router-link
            v-if="userStore.hasAbilities(ABILITIES.EDIT_CUSTOMER)"
            :to="`/admin/customers/${route.params.id}/edit`"
          >
            <BaseButton variant="white" :content-loading="isLoading">
              <template #left="slotProps">
                <BaseIcon name="PencilSquareIcon" :class="slotProps.class" />
              </template>
              {{ $t('general.edit') }}
            </BaseButton>
          </router-link>

          <BaseDropdown
            v-if="canCreateTransaction()"
            position="bottom-end"
            width-class="w-56"
            :content-loading="isLoading"
          >
            <template #activator>
              <span
                class="
                  inline-flex items-center justify-center w-full gap-2 px-3.5 text-sm font-medium transition-colors
                  rounded-lg h-11 md:h-9 bg-btn-primary text-on-primary hover:bg-btn-primary-hover
                "
              >
                <BaseIcon name="PlusIcon" class="w-4.5 h-4.5" />
                {{ $t('customers.new_transaction') }}
              </span>
            </template>

            <router-link
              v-if="userStore.hasAbilities(ABILITIES.CREATE_INVOICE)"
              :to="`/admin/invoices/create?customer=${$route.params.id}`"
            >
              <BaseDropdownItem>
                <BaseIcon name="DocumentTextIcon" class="w-5 h-5 mr-3 text-subtle" />
                {{ $t('invoices.new_invoice') }}
              </BaseDropdownItem>
            </router-link>

            <router-link
              v-if="userStore.hasAbilities(ABILITIES.CREATE_ESTIMATE)"
              :to="`/admin/estimates/create?customer=${$route.params.id}`"
            >
              <BaseDropdownItem>
                <BaseIcon name="DocumentIcon" class="w-5 h-5 mr-3 text-subtle" />
                {{ $t('estimates.new_estimate') }}
              </BaseDropdownItem>
            </router-link>

            <router-link
              v-if="userStore.hasAbilities(ABILITIES.CREATE_PAYMENT)"
              :to="`/admin/payments/create?customer=${$route.params.id}`"
            >
              <BaseDropdownItem>
                <BaseIcon name="CreditCardIcon" class="w-5 h-5 mr-3 text-subtle" />
                {{ $t('payments.new_payment') }}
              </BaseDropdownItem>
            </router-link>

            <router-link
              v-if="userStore.hasAbilities(ABILITIES.CREATE_EXPENSE)"
              :to="`/admin/expenses/create?customer=${$route.params.id}`"
            >
              <BaseDropdownItem>
                <BaseIcon name="CalculatorIcon" class="w-5 h-5 mr-3 text-subtle" />
                {{ $t('expenses.new_expense') }}
              </BaseDropdownItem>
            </router-link>
          </BaseDropdown>

          <CustomerDropdown
            v-if="hasAtleastOneAbility()"
            :row="customerStore.selectedViewCustomer"
            :load-data="refreshData"
          />
        </template>
      </BasePageHeader>

      <BaseStatStrip :columns="3">
        <BaseStat :label="$t('customers.net_account_balance')" emphasis>
          <BaseFormatMoney :amount="Math.abs(accountBalance)" :currency="customerCurrency" />
          <span v-if="accountBalance < 0" class="ml-1.5 text-xs font-medium text-status-green">
            {{ $t('customers.credit') }}
          </span>
        </BaseStat>
        <BaseStat :label="$t('customers.invoice_due')">
          <BaseFormatMoney :amount="invoiceDueAmount" :currency="customerCurrency" />
        </BaseStat>
        <BaseStat :label="$t('customers.available_credit')">
          <BaseFormatMoney :amount="availableCredit" :currency="customerCurrency" />
        </BaseStat>
      </BaseStatStrip>

      <BaseTabGroup>
        <BaseTab :title="$t('customers.overview')">
          <CustomerChart />
        </BaseTab>
        <BaseTab v-if="canViewStatement" :title="$t('customers.statement')">
          <CustomerStatement v-if="selectedCustomer" :customer="selectedCustomer" />
        </BaseTab>
      </BaseTabGroup>
    </BasePage>

    <!-- The other customers, beside this one (wide screens only) -->
    <CustomerViewSidebar />
  </div>
</template>

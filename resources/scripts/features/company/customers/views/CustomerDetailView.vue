<script setup lang="ts">
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useCustomerStore } from '../store'
import { useUserStore } from '../../../../stores/user.store'
import CustomerDropdown from '../components/CustomerDropdown.vue'
import CustomerViewSidebar from '@/scripts/features/company/customers/components/CustomerViewSidebar.vue'
import CustomerChart from '@/scripts/features/company/customers/components/CustomerChart.vue'

const ABILITIES = {
  EDIT_CUSTOMER: 'edit-customer',
  DELETE_CUSTOMER: 'delete-customer',
  CREATE_ESTIMATE: 'create-estimate',
  CREATE_INVOICE: 'create-invoice',
  CREATE_PAYMENT: 'create-payment',
  CREATE_EXPENSE: 'create-expense',
} as const

const customerStore = useCustomerStore()
const userStore = useUserStore()

const router = useRouter()
const route = useRoute()

const pageTitle = computed<string>(() => {
  return customerStore.selectedViewCustomer.name ?? ''
})

const isLoading = computed<boolean>(() => customerStore.isFetchingViewData)

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
  <BasePage class="xl:pl-96">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <router-link
          v-if="userStore.hasAbilities(ABILITIES.EDIT_CUSTOMER)"
          :to="`/admin/customers/${route.params.id}/edit`"
        >
          <BaseButton
            class="mr-3"
            variant="primary-outline"
            :content-loading="isLoading"
          >
            {{ $t('general.edit') }}
          </BaseButton>
        </router-link>

        <BaseDropdown
          v-if="canCreateTransaction()"
          position="bottom-end"
          :content-loading="isLoading"
        >
          <template #activator>
            <BaseButton
              class="mr-3"
              variant="primary"
              :content-loading="isLoading"
            >
              {{ $t('customers.new_transaction') }}
            </BaseButton>
          </template>

          <router-link
            v-if="userStore.hasAbilities(ABILITIES.CREATE_ESTIMATE)"
            :to="`/admin/estimates/create?customer=${$route.params.id}`"
          >
            <BaseDropdownItem>
              <BaseIcon name="DocumentIcon" class="mr-3 text-body" />
              {{ $t('estimates.new_estimate') }}
            </BaseDropdownItem>
          </router-link>

          <router-link
            v-if="userStore.hasAbilities(ABILITIES.CREATE_INVOICE)"
            :to="`/admin/invoices/create?customer=${$route.params.id}`"
          >
            <BaseDropdownItem>
              <BaseIcon name="DocumentTextIcon" class="mr-3 text-body" />
              {{ $t('invoices.new_invoice') }}
            </BaseDropdownItem>
          </router-link>

          <router-link
            v-if="userStore.hasAbilities(ABILITIES.CREATE_PAYMENT)"
            :to="`/admin/payments/create?customer=${$route.params.id}`"
          >
            <BaseDropdownItem>
              <BaseIcon name="CreditCardIcon" class="mr-3 text-body" />
              {{ $t('payments.new_payment') }}
            </BaseDropdownItem>
          </router-link>

          <router-link
            v-if="userStore.hasAbilities(ABILITIES.CREATE_EXPENSE)"
            :to="`/admin/expenses/create?customer=${$route.params.id}`"
          >
            <BaseDropdownItem>
              <BaseIcon name="CalculatorIcon" class="mr-3 text-body" />
              {{ $t('expenses.new_expense') }}
            </BaseDropdownItem>
          </router-link>
        </BaseDropdown>

        <CustomerDropdown
          v-if="hasAtleastOneAbility()"
          :class="{
            'ml-3': isLoading,
          }"
          :row="customerStore.selectedViewCustomer"
          :load-data="refreshData"
        />
      </template>
    </BasePageHeader>

    <!-- Customer View Sidebar -->
    <CustomerViewSidebar />

    <!-- Chart -->
    <CustomerChart />
  </BasePage>
</template>

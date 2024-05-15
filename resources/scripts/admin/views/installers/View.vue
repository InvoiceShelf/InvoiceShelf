<template>
  <BasePage class="xl:pl-96">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <router-link
          v-if="userStore.hasAbilities(abilities.EDIT_INSTALLER)"
          :to="`/admin/installers/${route.params.id}/edit`"
        >
          <BaseButton
            class="mr-3"
            variant="primary-outline"
            :content-loading="isLoading"
          >
            {{ $t('general.edit') }}
          </BaseButton>
        </router-link>

        <!-- <BaseDropdown
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
              {{ $t('installers.new_transaction') }}
            </BaseButton>
          </template>

          <router-link
            v-if="userStore.hasAbilities(abilities.CREATE_ESTIMATE)"
            :to="`/admin/estimates/create?installer=${$route.params.id}`"
          >
            <BaseDropdownItem class="">
              <BaseIcon name="DocumentIcon" class="mr-3 text-gray-600" />
              {{ $t('estimates.new_estimate') }}
            </BaseDropdownItem>
          </router-link>

          <router-link
            v-if="userStore.hasAbilities(abilities.CREATE_INVOICE)"
            :to="`/admin/invoices/create?installer=${$route.params.id}`"
          >
            <BaseDropdownItem>
              <BaseIcon name="DocumentTextIcon" class="mr-3 text-gray-600" />
              {{ $t('invoices.new_invoice') }}
            </BaseDropdownItem>
          </router-link>

          <router-link
            v-if="userStore.hasAbilities(abilities.CREATE_PAYMENT)"
            :to="`/admin/payments/create?installer=${$route.params.id}`"
          >
            <BaseDropdownItem>
              <BaseIcon name="CreditCardIcon" class="mr-3 text-gray-600" />
              {{ $t('payments.new_payment') }}
            </BaseDropdownItem>
          </router-link>

          <router-link
            v-if="userStore.hasAbilities(abilities.CREATE_EXPENSE)"
            :to="`/admin/expenses/create?installer=${$route.params.id}`"
          >
            <BaseDropdownItem>
              <BaseIcon name="CalculatorIcon" class="mr-3 text-gray-600" />
              {{ $t('expenses.new_expense') }}
            </BaseDropdownItem>
          </router-link>
        </BaseDropdown> -->

        <InstallerDropdown
          v-if="hasAtleastOneAbility()"
          :class="{
            'ml-3': isLoading,
          }"
          :row="installerStore.selectedViewInstaller"
          :load-data="refreshData"
        />
      </template>
    </BasePageHeader>

    <!-- Installer View Sidebar -->
    <InstallerViewSidebar />

    <!-- Chart -->
    <InstallerChart />
  </BasePage>
</template>

<script setup>
import InstallerViewSidebar from './partials/InstallerViewSidebar.vue'
import InstallerChart from './partials/InstallerChart.vue'
import { ref, computed, inject } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useInstallerStore } from '@/scripts/admin/stores/installer'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useUserStore } from '@/scripts/admin/stores/user'
import InstallerDropdown from '@/scripts/admin/components/dropdowns/InstallerIndexDropdown.vue'
import abilities from '@/scripts/admin/stub/abilities'

const utils = inject('utils')
const dialogStore = useDialogStore()
const installerStore = useInstallerStore()
const userStore = useUserStore()
const { t } = useI18n()

const router = useRouter()
const route = useRoute()
const installer = ref(null)

const pageTitle = computed(() => {
  return installerStore.selectedViewInstaller.installer
    ? installerStore.selectedViewInstaller.installer.name
    : ''
})

let isLoading = computed(() => {
  return installerStore.isFetchingViewData
})

function canCreateTransaction() {
  return userStore.hasAbilities([
    abilities.CREATE_ESTIMATE,
    abilities.CREATE_INVOICE,
    abilities.CREATE_PAYMENT,
    abilities.CREATE_EXPENSE,
  ])
}

function hasAtleastOneAbility() {
  return userStore.hasAbilities([
    abilities.DELETE_INSTALLER,
    abilities.EDIT_INSTALLER,
  ])
}

function refreshData() {
  router.push('/admin/installers')
}
</script>

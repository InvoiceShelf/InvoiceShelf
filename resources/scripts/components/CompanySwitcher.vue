<template>
  <div ref="companySwitchBar" class="relative rounded">
    <CompanyModal />

    <div
      class="
        flex
        items-center
        justify-center
        px-3
        h-8
        md:h-9
        ml-2
        text-sm text-white
        bg-white/20
        rounded
        cursor-pointer
      "
      @click="isShow = !isShow"
    >
      <span
        v-if="companyStore.isAdminMode"
        class="w-16 text-sm font-medium truncate sm:w-auto"
      >
        {{ $t('navigation.administration') }}
      </span>
      <span
        v-else-if="companyStore.selectedCompany"
        class="w-16 text-sm font-medium truncate sm:w-auto"
      >
        {{ companyStore.selectedCompany.name }}
      </span>
      <BaseIcon name="ChevronDownIcon" class="h-5 ml-1 text-white" />
    </div>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-1 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-1 opacity-0"
    >
      <div
        v-if="isShow"
        class="absolute right-0 mt-2 bg-white rounded-md shadow-lg"
      >
        <div
          class="
            overflow-y-auto
            scrollbar-thin scrollbar-thumb-rounded-full
            w-[300px]
            max-h-[350px]
            scrollbar-thumb-gray-300 scrollbar-track-gray-10
            pb-4
          "
        >
          <!-- Administration Mode -->
          <div v-if="userStore.currentUser?.is_super_admin">
            <div
              class="p-2 px-3 rounded-md cursor-pointer hover:bg-gray-100 hover:text-primary-500"
              :class="{
                'bg-gray-100 text-primary-500': companyStore.isAdminMode,
              }"
              @click="enterAdminMode"
            >
              <div class="flex items-center">
                <span
                  class="flex items-center justify-center mr-3 overflow-hidden text-base font-semibold bg-primary-100 rounded-md w-9 h-9 shrink-0 text-primary-500"
                >
                  <BaseIcon name="ShieldCheckIcon" class="w-5 h-5" />
                </span>
                <div class="flex flex-col">
                  <span class="text-sm font-medium">{{ $t('navigation.administration') }}</span>
                </div>
              </div>
            </div>
            <div class="border-t border-gray-100 my-1"></div>
          </div>

          <label
            class="
              px-3
              py-2
              text-xs
              font-semibold
              text-gray-400
              mb-0.5
              block
              uppercase
            "
          >
            {{ $t('company_switcher.label') }}
          </label>

          <div
            v-if="companyStore.companies.length < 1"
            class="
              flex flex-col
              items-center
              justify-center
              p-2
              px-3
              mt-4
              text-base text-gray-400
            "
          >
            <BaseIcon name="ExclamationCircleIcon" class="h-5 text-gray-400" />
            {{ $t('company_switcher.no_results_found') }}
          </div>
          <div v-else>
            <div v-if="companyStore.companies.length > 0">
              <div
                v-for="(company, index) in companyStore.companies"
                :key="index"
                class="
                  p-2
                  px-3
                  rounded-md
                  cursor-pointer
                  hover:bg-gray-100 hover:text-primary-500
                "
                :class="{
                  'bg-gray-100 text-primary-500':
                    companyStore.selectedCompany && companyStore.selectedCompany.id === company.id,
                }"
                @click="changeCompany(company)"
              >
                <div class="flex items-center">
                  <span
                    class="
                      flex
                      items-center
                      justify-center
                      mr-3
                      overflow-hidden
                      text-base
                      font-semibold
                      bg-gray-200
                      rounded-md
                      w-9
                      h-9
                      text-primary-500
                    "
                  >
                    <span v-if="!company.logo">
                      {{ initGenerator(company.name) }}
                    </span>
                    <img
                      v-else
                      :src="company.logo"
                      alt="Company logo"
                      class="w-full h-full object-contain"
                    />
                  </span>
                  <div class="flex flex-col">
                    <span class="text-sm">{{ company.name }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- Pending Invitations -->
        <div
          v-if="invitationStore.pendingInvitations.length > 0"
          class="border-t border-gray-100 p-2"
        >
          <label
            class="
              block px-1 pt-1 pb-2 text-xs font-semibold
              leading-tight text-gray-400 uppercase
            "
          >
            {{ $t('members.pending_invitations') }}
          </label>
          <div
            v-for="invitation in invitationStore.pendingInvitations"
            :key="invitation.id"
            class="p-2 px-3 rounded-md"
          >
            <div class="flex items-center mb-2">
              <span
                class="
                  flex items-center justify-center mr-3
                  overflow-hidden text-xs font-semibold
                  bg-gray-200 rounded-md w-9 h-9 shrink-0 text-gray-400
                "
              >
                {{ initGenerator(invitation.company?.name || '?') }}
              </span>
              <div class="flex flex-col min-w-0">
                <span class="text-sm text-gray-700 truncate">{{ invitation.company?.name }}</span>
                <span class="text-xs text-gray-400">{{ invitation.role?.title }}</span>
              </div>
            </div>
            <div class="flex space-x-1 pl-12">
              <button
                class="text-xs px-2 py-1 rounded bg-primary-500 text-white hover:bg-primary-600"
                @click.stop="acceptInvitation(invitation.token)"
              >
                {{ $t('general.accept') }}
              </button>
              <button
                class="text-xs px-2 py-1 rounded bg-gray-200 text-gray-600 hover:bg-gray-300"
                @click.stop="declineInvitation(invitation.token)"
              >
                {{ $t('general.decline') }}
              </button>
            </div>
          </div>
        </div>

        <div
          v-if="userStore.currentUser.is_owner"
          class="
            flex
            items-center
            justify-center
            p-4
            pl-3
            border-t-2 border-gray-100
            cursor-pointer
            text-primary-400
            hover:text-primary-500
          "
          @click="addNewCompany"
        >
          <BaseIcon name="PlusIcon" class="h-5 mr-2" />

          <span class="font-medium">
            {{ $t('company_switcher.add_new_company') }}
          </span>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { onClickOutside } from '@vueuse/core'
import { useRoute, useRouter } from 'vue-router'
import { useModalStore } from '../stores/modal'
import { useI18n } from 'vue-i18n'
import { useGlobalStore } from '@/scripts/admin//stores/global'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useInvitationStore } from '@/scripts/admin/stores/invitation'

import CompanyModal from '@/scripts/admin/components/modal-components/CompanyModal.vue'
import abilities from '@/scripts/admin/stub/abilities'

const companyStore = useCompanyStore()
const modalStore = useModalStore()
const route = useRoute()
const router = useRouter()
const globalStore = useGlobalStore()
const { t } = useI18n()
const userStore = useUserStore()
const invitationStore = useInvitationStore()
const isShow = ref(false)
const name = ref('')
const companySwitchBar = ref(null)

watch(route, () => {
  isShow.value = false
  name.value = ''
})

onClickOutside(companySwitchBar, () => {
  isShow.value = false
})

function initGenerator(name) {
  if (name) {
    const nameSplit = name.split(' ')
    const initials = nameSplit[0].charAt(0).toUpperCase()
    return initials
  }
}

function addNewCompany() {
  modalStore.openModal({
    title: t('company_switcher.new_company'),
    componentName: 'CompanyModal',
    size: 'sm',
  })
}

async function enterAdminMode() {
  companyStore.setAdminMode(true)
  isShow.value = false
  router.push('/admin/administration/dashboard')
  await globalStore.setIsAppLoaded(false)
  await globalStore.bootstrap()
}

async function changeCompany(company) {
  companyStore.setAdminMode(false)
  await companyStore.setSelectedCompany(company)
  router.push('/admin/dashboard')
  await globalStore.setIsAppLoaded(false)
  await globalStore.bootstrap()
}

async function acceptInvitation(token) {
  await invitationStore.accept(token)
}

async function declineInvitation(token) {
  await invitationStore.decline(token)
}
</script>

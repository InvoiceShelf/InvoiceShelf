<template>
  <div ref="searchBar" class="hidden rounded md:block relative">
    <div>
      <BaseInput
        v-model="name"
        :placeholder="$t('global_search.search')"
        container-class="!rounded"
        class="h-8 md:h-9 !rounded"
        default-input-class="font-base block w-full sm:text-sm border-gray-200 dark:border-gray-600 rounded-md text-black dark:text-white bg-white dark:bg-gray-700 focus:ring-primary-400 focus:border-primary-400 dark:focus:ring-primary-500 dark:focus:border-primary-500"
        @input="onSearch"
      >
        <template #left>
          <BaseIcon name="MagnifyingGlassIcon" class="text-gray-400 dark:text-gray-500" />
        </template>
        <template #right>
          <SpinnerIcon v-if="isSearching" class="h-5 text-primary-500" />
        </template>
      </BaseInput>
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
        class="
          scrollbar-thin
          scrollbar-thumb-rounded-full
          scrollbar-thumb-gray-300
          dark:scrollbar-thumb-gray-600
          scrollbar-track-gray-100
          dark:scrollbar-track-gray-800
          overflow-y-auto
          bg-white
          dark:bg-gray-800
          rounded-md
          mt-2
          shadow-lg
          dark:shadow-gray-900/50
          p-3
          absolute
          w-[300px]
          h-[200px]
          right-0
          border
          border-gray-200
          dark:border-gray-700
        "
      >
        <div
          v-if="
            usersStore.userList.length < 1 && usersStore.customerList.length < 1
          "
          class="
            flex
            items-center
            justify-center
            text-gray-400
            dark:text-gray-500
            text-base
            flex-col
            mt-4
          "
        >
          <BaseIcon name="ExclamationCircleIcon" class="text-gray-400 dark:text-gray-500" />

          {{ $t('global_search.no_results_found') }}
        </div>
        <div v-else>
          <div v-if="usersStore.customerList.length > 0">
            <label class="text-sm text-gray-400 dark:text-gray-500 mb-0.5 block px-2 uppercase">
              {{ $t('global_search.customers') }}
            </label>
            <div
              v-for="(customer, index) in usersStore.customerList"
              :key="index"
              class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer rounded-md"
            >
              <router-link
                :to="{ path: `/admin/customers/${customer.id}/view` }"
                class="flex items-center"
              >
                <span
                  class="
                    flex
                    items-center
                    justify-center
                    w-9
                    h-9
                    mr-3
                    text-base
                    font-semibold
                    bg-gray-200
                    dark:bg-gray-600
                    rounded-full
                    text-primary-500
                    dark:text-primary-400
                  "
                >
                  {{ initGenerator(customer.name) }}
                </span>
                <div class="flex flex-col">
                  <span class="text-sm text-gray-900 dark:text-gray-100">{{ customer.name }}</span>
                  <span
                    v-if="customer.contact_name"
                    class="text-xs text-gray-400 dark:text-gray-500"
                  >
                    {{ customer.contact_name }}
                  </span>
                  <span v-else class="text-xs text-gray-400 dark:text-gray-500">{{
                    customer.email
                  }}</span>
                </div>
              </router-link>
            </div>
          </div>

          <div v-if="usersStore.userList.length > 0" class="mt-2">
            <label
              class="text-sm text-gray-400 dark:text-gray-500 mb-2 block px-2 mb-0.5 uppercase"
            >
              {{ $t('global_search.users') }}
            </label>
            <div
              v-for="(user, index) in usersStore.userList"
              :key="index"
              class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer rounded-md"
            >
              <router-link
                :to="{ path: `/admin/users/${user.id}/edit` }"
                class="flex items-center"
              >
                <span
                  class="
                    flex
                    items-center
                    justify-center
                    w-9
                    h-9
                    mr-3
                    text-base
                    font-semibold
                    bg-gray-200
                    dark:bg-gray-600
                    rounded-full
                    text-primary-500
                    dark:text-primary-400
                  "
                >
                  {{ initGenerator(user.name) }}
                </span>
                <div class="flex flex-col">
                  <span class="text-sm text-gray-900 dark:text-gray-100">{{ user.name }}</span>
                  <span class="text-xs text-gray-400 dark:text-gray-500">{{ user.email }}</span>
                </div>
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useUsersStore } from '@/scripts/admin/stores/users'
import { onClickOutside } from '@vueuse/core'
import { useRoute } from 'vue-router'
import SpinnerIcon from '@/scripts/components/icons/SpinnerIcon.vue'
import { debounce } from 'lodash'

const usersStore = useUsersStore()

const isShow = ref(false)
const name = ref('')
const searchBar = ref(null)
const isSearching = ref(false)
const route = useRoute()

watch(route, () => {
  isShow.value = false
  name.value = ''
})

onSearch = debounce(onSearch, 500)

onClickOutside(searchBar, () => {
  isShow.value = false
  name.value = ''
})

function onSearch() {
  let data = {
    search: name.value,
  }

  if (name.value) {
    isSearching.value = true
    usersStore.searchUsers(data).then(() => {
      isShow.value = true
    })
    isSearching.value = false
  }
  if (name.value === '') {
    isShow.value = false
  }
}

function initGenerator(name) {
  if (name) {
    const nameSplit = name.split(' ')
    const initials = nameSplit[0].charAt(0).toUpperCase()
    return initials
  }
}
</script>

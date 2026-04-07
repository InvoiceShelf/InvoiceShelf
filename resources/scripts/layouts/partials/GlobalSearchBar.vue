<template>
  <div ref="searchBar" class="hidden rounded md:block relative">
    <div>
      <BaseInput
        v-model="searchQuery"
        :placeholder="$t('global_search.search')"
        container-class="!rounded-lg !shadow-none"
        class="h-8 md:h-9 !rounded-lg !bg-white/20 !border-white/10 !text-white !placeholder-white/60"
        @input="onSearchInput"
      >
        <template #left>
          <BaseIcon name="MagnifyingGlassIcon" class="!text-white/70" />
        </template>
        <template #right>
          <span v-if="isSearching" class="h-5 w-5 animate-spin text-primary-500" />
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
          scrollbar-thin scrollbar-thumb-rounded-full scrollbar-thumb-surface-muted
          scrollbar-track-surface-secondary overflow-y-auto bg-surface rounded-md
          mt-2 shadow-lg p-3 absolute w-[300px] h-[200px] right-0
        "
      >
        <div
          v-if="customerList.length < 1 && userList.length < 1"
          class="flex items-center justify-center text-subtle text-base flex-col mt-4"
        >
          <BaseIcon name="ExclamationCircleIcon" class="text-subtle" />
          {{ $t('global_search.no_results_found') }}
        </div>

        <div v-else>
          <div v-if="customerList.length > 0">
            <label class="text-sm text-subtle mb-0.5 block px-2 uppercase">
              {{ $t('global_search.customers') }}
            </label>
            <div
              v-for="(customer, index) in customerList"
              :key="index"
              class="p-2 hover:bg-hover-strong cursor-pointer rounded-md"
            >
              <router-link
                :to="{ path: `/admin/customers/${customer.id}/view` }"
                class="flex items-center"
              >
                <span
                  class="
                    flex items-center justify-center w-9 h-9 mr-3 text-base font-semibold
                    bg-surface-muted rounded-full text-primary-500
                  "
                >
                  {{ initGenerator(customer.name) }}
                </span>
                <div class="flex flex-col">
                  <span class="text-sm">{{ customer.name }}</span>
                  <span v-if="customer.contact_name" class="text-xs text-subtle">
                    {{ customer.contact_name }}
                  </span>
                  <span v-else class="text-xs text-subtle">{{ customer.email }}</span>
                </div>
              </router-link>
            </div>
          </div>

          <div v-if="userList.length > 0" class="mt-2">
            <label class="text-sm text-subtle mb-0.5 block px-2 uppercase">
              {{ $t('global_search.users') }}
            </label>
            <div
              v-for="(user, index) in userList"
              :key="index"
              class="p-2 hover:bg-hover-strong cursor-pointer rounded-md"
            >
              <router-link
                :to="{ path: `/admin/members/${user.id}/edit` }"
                class="flex items-center"
              >
                <span
                  class="
                    flex items-center justify-center w-9 h-9 mr-3 text-base font-semibold
                    bg-surface-muted rounded-full text-primary-500
                  "
                >
                  {{ initGenerator(user.name) }}
                </span>
                <div class="flex flex-col">
                  <span class="text-sm">{{ user.name }}</span>
                  <span class="text-xs text-subtle">{{ user.email }}</span>
                </div>
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { onClickOutside, useDebounceFn } from '@vueuse/core'
import { useRoute } from 'vue-router'
import { client } from '@/scripts/api/client'
import { API } from '@/scripts/api/endpoints'

interface SearchResult {
  id: number
  name: string
  email?: string
  contact_name?: string
}

const isShow = ref<boolean>(false)
const searchQuery = ref<string>('')
const searchBar = ref<HTMLElement | null>(null)
const isSearching = ref<boolean>(false)
const customerList = ref<SearchResult[]>([])
const userList = ref<SearchResult[]>([])
const route = useRoute()

watch(route, () => {
  isShow.value = false
  searchQuery.value = ''
})

onClickOutside(searchBar, () => {
  isShow.value = false
  searchQuery.value = ''
})

const debouncedSearch = useDebounceFn(async () => {
  if (!searchQuery.value) {
    isShow.value = false
    return
  }

  isSearching.value = true

  try {
    const { data } = await client.get(API.SEARCH, {
      params: { search: searchQuery.value },
    })

    customerList.value = data.customers ?? []
    userList.value = data.users ?? []
    isShow.value = true
  } finally {
    isSearching.value = false
  }
}, 500)

function onSearchInput(): void {
  if (searchQuery.value === '') {
    isShow.value = false
    return
  }
  debouncedSearch()
}

function initGenerator(name: string): string {
  if (name) {
    const nameSplit = name.split(' ')
    return nameSplit[0].charAt(0).toUpperCase()
  }
  return ''
}
</script>

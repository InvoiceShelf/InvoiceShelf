<template>
  <Disclosure
    v-slot="{ open }"
    as="nav"
    class="bg-surface shadow-xs fixed top-0 left-0 z-20 w-full"
  >
    <div class="mx-auto px-8">
      <div class="flex justify-between h-16 w-full">
        <div class="flex">
          <div class="shrink-0 flex items-center">
            <a
              :href="`/${globalStore.companySlug}/customer/dashboard`"
              class="
                float-none
                text-lg
                not-italic
                font-black
                tracking-wider
                text-white
                brand-main
                md:float-left
                font-base
              "
            >
              <MainLogo v-if="!customerLogo" class="h-6" />
              <img v-else :src="customerLogo" class="h-6" />
            </a>
          </div>
          <div class="hidden sm:-my-px sm:ml-6 sm:flex sm:space-x-8">
            <router-link
              v-for="item in globalStore.mainMenu"
              :key="item.title"
              :to="`/${globalStore.companySlug}${item.link}`"
              :class="[
                hasActiveUrl(item.link)
                  ? 'border-primary-500 text-primary-600'
                  : 'border-transparent text-muted hover:text-body hover:border-line-strong',
                'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium',
              ]"
            >
              {{ $t(item.title) }}
            </router-link>
          </div>
        </div>
        <div class="hidden sm:ml-6 sm:flex sm:items-center">
          <button
            type="button"
            class="
              bg-surface
              p-1
              rounded-full
              text-subtle
              hover:text-muted
              focus:outline-hidden
              focus:ring-2
              focus:ring-offset-2
              focus:ring-primary-500
            "
          ></button>

          <!-- Profile dropdown -->

          <Menu as="div" class="ml-3 relative">
            <BaseDropdown width-class="w-48">
              <template #activator>
                <MenuButton
                  class="
                    bg-surface
                    flex
                    text-sm
                    rounded-full
                    focus:outline-hidden
                    focus:ring-2
                    focus:ring-offset-2
                    focus:ring-primary-500
                  "
                >
                  <img
                    class="h-8 w-8 rounded-full"
                    :src="previewAvatar"
                    alt=""
                  />
                </MenuButton>
              </template>
              <router-link :to="{ name: 'customer.profile' }">
                <BaseDropdownItem>
                  <CogIcon
                    class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
                    aria-hidden="true"
                  />
                  {{ $t('navigation.settings') }}
                </BaseDropdownItem>
              </router-link>

              <BaseDropdownItem @click="logout">
                <ArrowRightOnRectangleIcon
                  class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
                  aria-hidden="true"
                />
                {{ $t('navigation.logout') }}
              </BaseDropdownItem>
            </BaseDropdown>
          </Menu>
        </div>
        <div class="-mr-2 flex items-center sm:hidden">
          <!-- Mobile menu button -->
          <DisclosureButton
            class="
              bg-surface
              inline-flex
              items-center
              justify-center
              p-2
              rounded-md
              text-subtle
              hover:text-muted hover:bg-hover-strong
              focus:outline-hidden
              focus:ring-2
              focus:ring-offset-2
              focus:ring-primary-500
            "
          >
            <span class="sr-only">Open main menu</span>
            <Bars3Icon v-if="!open" class="block h-6 w-6" aria-hidden="true" />
            <XMarkIcon v-else class="block h-6 w-6" aria-hidden="true" />
          </DisclosureButton>
        </div>
      </div>
    </div>

    <DisclosurePanel class="sm:hidden">
      <div class="pt-2 pb-3 space-y-1">
        <router-link
          v-for="item in globalStore.mainMenu"
          :key="item.title"
          :to="`/${globalStore.companySlug}${item.link}`"
          :class="[
            hasActiveUrl(item.link)
              ? 'bg-primary-50 border-primary-500 text-primary-700'
              : 'border-transparent text-body hover:bg-hover hover:border-line-strong hover:text-heading',
            'block pl-3 pr-4 py-2 border-l-4 text-base font-medium',
          ]"
          :aria-current="item.current ? 'page' : undefined"
          >{{ $t(item.title) }}
        </router-link>
      </div>
      <div class="pt-4 pb-3 border-t border-line-default">
        <div class="flex items-center px-4">
          <div class="shrink-0">
            <img class="h-10 w-10 rounded-full" :src="previewAvatar" alt="" />
          </div>
          <div class="ml-3">
            <div class="text-base font-medium text-heading">
              {{ globalStore.currentUser.title }}
            </div>
            <div class="text-sm font-medium text-muted">
              {{ globalStore.currentUser.email }}
            </div>
          </div>
          <button
            type="button"
            class="
              ml-auto
              bg-surface
              shrink-0
              p-1
              rounded-full
              text-subtle
              hover:text-muted
              focus:outline-hidden
              focus:ring-2
              focus:ring-offset-2
              focus:ring-primary-500
            "
          ></button>
        </div>
        <div class="mt-3 space-y-1">
          <router-link
            v-for="item in userNavigation"
            :key="item.title"
            :to="item.link"
            :class="[
              hasActiveUrl(item.link)
                ? 'bg-primary-50 border-primary-500 text-primary-700'
                : 'border-transparent text-body hover:bg-hover hover:border-line-strong hover:text-heading',
              'block pl-3 pr-4 py-2 border-l-4 text-base font-medium',
            ]"
            >{{ $t(item.title) }}</router-link
          >
        </div>
      </div>
    </DisclosurePanel>
  </Disclosure>
</template>

<script setup>
import { useAuthStore } from '@/scripts/customer/stores/auth'
import { useRoute, useRouter } from 'vue-router'
import { ref, watch, computed } from 'vue'
import { useGlobalStore } from '@/scripts/customer/stores/global'
import MainLogo from '@/scripts/components/icons/MainLogo.vue'
import {
  Disclosure,
  DisclosureButton,
  DisclosurePanel,
  Menu,
  MenuButton,
} from '@headlessui/vue'
import { Bars3Icon, XMarkIcon, ArrowRightOnRectangleIcon, CogIcon } from '@heroicons/vue/24/outline'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const route = useRoute()
const globalStore = useGlobalStore()

const userNavigation = [
  {
    title: t('navigation.logout'),
    link: `/${globalStore.companySlug}/customer/login`,
  },
]

const authStore = useAuthStore()
const router = useRouter()
const activeRoute = ref('')

const previewAvatar = computed(() => {
  return globalStore.currentUser && globalStore.currentUser.avatar !== 0
    ? globalStore.currentUser.avatar
    : getDefaultAvatar()
})

function getDefaultAvatar() {
  const imgUrl = new URL('$images/default-avatar.jpg', import.meta.url)
  return imgUrl
}

watch(
  route,
  (val) => {
    activeRoute.value = val.path
  },
  { immediate: true }
)

const customerLogo = computed(() => {
  if (window.customer_logo) {
    return window.customer_logo
  }

  return false
})

function hasActiveUrl(url) {
  return route.path.indexOf(url) > -1
}

function logout() {
  authStore.logout(globalStore.companySlug).then((res) => {
    if (res) {
      router.push({ name: 'customer.login' })
    }
  })
}
</script>

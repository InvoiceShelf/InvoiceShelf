<template>
  <header
    class="
      fixed top-0 left-0 z-20 flex w-full items-center justify-between border-b
      border-line-default bg-surface px-4 py-3 shadow-xs md:px-8
    "
  >
    <div class="flex min-w-0 items-center gap-6">
      <router-link
        :to="dashboardPath"
        class="shrink-0"
      >
        <MainLogo
          v-if="!customerLogo"
          class="h-6 w-auto text-primary-500"
        />
        <img
          v-else
          :src="customerLogo"
          class="h-6 w-auto"
        />
      </router-link>

      <nav class="hidden items-center gap-5 md:flex">
        <router-link
          v-for="item in store.mainMenu"
          :key="item.link"
          :to="menuLink(item.link)"
          :class="[
            isActiveLink(item.link)
              ? 'text-primary-500'
              : 'text-muted hover:text-heading',
            'text-sm font-medium transition-colors',
          ]"
        >
          {{ $t(item.title) }}
        </router-link>
      </nav>
    </div>

    <div class="flex items-center gap-3">
      <div class="hidden text-right sm:block">
        <p class="text-sm font-medium text-heading">
          {{ store.currentUser?.name ?? '' }}
        </p>
        <p class="text-xs text-muted">
          {{ store.currentUser?.email ?? '' }}
        </p>
      </div>

      <BaseDropdown width-class="w-56">
        <template #activator>
          <button
            class="flex items-center gap-2 rounded-full p-1 transition-colors hover:bg-surface-tertiary"
            type="button"
          >
            <img
              :src="previewAvatar"
              class="h-9 w-9 rounded-full object-cover"
            />
            <BaseIcon
              class="hidden h-4 w-4 text-muted md:block"
              name="ChevronDownIcon"
            />
          </button>
        </template>

        <div class="px-2 pb-2 md:hidden">
          <router-link
            v-for="item in store.mainMenu"
            :key="`${item.link}-mobile`"
            :to="menuLink(item.link)"
          >
            <BaseDropdownItem>
              {{ $t(item.title) }}
            </BaseDropdownItem>
          </router-link>
        </div>

        <router-link :to="settingsPath">
          <BaseDropdownItem>
            <BaseIcon
              class="mr-3 h-5 w-5 text-subtle group-hover:text-muted"
              name="CogIcon"
            />
            {{ $t('navigation.settings') }}
          </BaseDropdownItem>
        </router-link>

        <BaseDropdownItem @click="logout">
          <BaseIcon
            class="mr-3 h-5 w-5 text-subtle group-hover:text-muted"
            name="ArrowRightOnRectangleIcon"
          />
          {{ $t('navigation.logout') }}
        </BaseDropdownItem>
      </BaseDropdown>
    </div>
  </header>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCustomerPortalStore } from '../store'
import { buildCustomerPortalPath, prefixCustomerPortalMenuLink } from '../utils/routes'
import MainLogo from '@/scripts/components/icons/MainLogo.vue'

declare global {
  interface Window {
    customer_logo?: string
  }
}

const store = useCustomerPortalStore()
const route = useRoute()
const router = useRouter()

const customerLogo = computed<string | false>(() => {
  return window.customer_logo || false
})

const dashboardPath = computed<string>(() => {
  return buildCustomerPortalPath(store.companySlug, 'dashboard')
})

const settingsPath = computed<string>(() => {
  return buildCustomerPortalPath(store.companySlug, 'settings')
})

const previewAvatar = computed<string>(() => {
  if (typeof store.currentUser?.avatar === 'string' && store.currentUser.avatar) {
    return store.currentUser.avatar
  }

  return getDefaultAvatar()
})

function getDefaultAvatar(): string {
  const imageUrl = new URL('$images/default-avatar.jpg', import.meta.url)
  return imageUrl.href
}

function menuLink(link: string): string {
  return prefixCustomerPortalMenuLink(store.companySlug, link)
}

function isActiveLink(link: string): boolean {
  const resolvedLink = menuLink(link)

  if (resolvedLink.endsWith('/dashboard')) {
    return route.path === resolvedLink
  }

  return route.path.startsWith(resolvedLink)
}

async function logout(): Promise<void> {
  const companySlug = store.companySlug
  await store.logout()
  await router.push({
    name: 'customer-portal.login',
    params: { company: companySlug },
  })
}
</script>

<template>
  <div class="flex h-screen items-center justify-center">
    <div class="text-center">
      <h1 class="text-6xl font-bold text-gray-300">404</h1>
      <p class="mt-4 text-lg text-gray-500">
        {{ $t('errors.page_not_found', 'Page not found') }}
      </p>
      <router-link
        to="/admin/dashboard"
        class="mt-6 inline-block text-primary-500 hover:text-primary-600"
      >
        {{ $t('general.go_to_dashboard', 'Go to Dashboard') }}
      </router-link>
    </div>
  </div>
</template>

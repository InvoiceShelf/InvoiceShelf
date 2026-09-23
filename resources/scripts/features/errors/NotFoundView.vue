<template>
  <div class="flex h-screen items-center justify-center">
    <div class="text-center">
      <h1 class="text-6xl font-bold text-subtle">404</h1>
      <p class="mt-4 text-lg text-muted">
        {{ $t('errors.page_not_found', 'Page not found') }}
      </p>
      <router-link
        to="/admin/dashboard"
        class="mt-6 inline-block text-primary-600 hover:text-primary-700"
      >
        {{ $t('general.go_to_dashboard', 'Go to Dashboard') }}
      </router-link>
    </div>
  </div>
</template>

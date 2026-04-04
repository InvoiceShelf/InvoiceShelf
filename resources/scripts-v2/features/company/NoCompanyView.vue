<template>
  <div class="flex h-full items-center justify-center p-6">
    <div class="text-center">
      <h2 class="text-xl font-semibold text-gray-700">
        {{ $t('general.no_company_selected', 'No company selected') }}
      </h2>
      <p class="mt-2 text-gray-500">
        {{ $t('general.select_company_to_continue', 'Please select a company to continue.') }}
      </p>
    </div>
  </div>
</template>

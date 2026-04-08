<template>
  <div
    role="alert"
    class="
      mt-6
      rounded-md
      border border-red-200
      bg-red-50
      p-4
      text-sm text-red-950
    "
  >
    <div class="flex gap-3">
      <BaseIcon
        name="ShieldExclamationIcon"
        class="h-6 w-6 shrink-0 text-red-700"
      />
      <div class="min-w-0">
        <p class="font-semibold leading-snug text-red-950">
          {{ $t('modules.third_party_security_notice_title') }}
        </p>
        <p class="mt-2 leading-relaxed text-red-900">
          {{ $t('modules.third_party_security_notice_body') }}
        </p>
      </div>
    </div>
  </div>
</template>

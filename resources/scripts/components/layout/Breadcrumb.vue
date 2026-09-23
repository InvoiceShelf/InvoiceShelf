<template>
  <!--
    A back trail above the page title. Home and the current page drop out
    (BaseBreadcrumbItem hides them), so a top-level page shows nothing here
    and a nested one shows the way back, for example "‹ Invoices".
  -->
  <nav
    class="order-first mb-1.5 [&:not(:has(li.crumb))]:hidden"
    :aria-label="$t('general.breadcrumb')"
  >
    <ol class="flex flex-wrap items-center text-sm text-muted">
      <li aria-hidden="true" class="flex items-center -ml-1 mr-0.5">
        <BaseIcon name="ChevronLeftIcon" class="w-4 h-4" />
      </li>
      <slot />
    </ol>
  </nav>
</template>

<script setup lang="ts">
</script>

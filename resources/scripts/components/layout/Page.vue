<template>
  <!--
    One vertical rhythm for every page: the header, filters, tabs and content
    are spaced by the gap, so pages don't add margins of their own between them.
  -->
  <div class="flex flex-col flex-1 w-full gap-4 px-4 pt-4 pb-10 md:gap-5 md:px-6 md:pt-6 lg:px-8">
    <slot />
  </div>
</template>

<script setup lang="ts">
</script>

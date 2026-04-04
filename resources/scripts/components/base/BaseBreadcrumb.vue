<template>
  <nav>
    <ol class="flex flex-wrap py-4 text-heading rounded list-reset">
      <slot />
    </ol>
  </nav>
</template>

<script>
export default {
  name: 'BaseBreadcrumb',
}
</script>

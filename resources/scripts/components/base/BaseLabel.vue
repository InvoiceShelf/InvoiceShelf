<template>
  <!-- A styled label: callers pass `for`, or nest the control in the slot -->
  <!-- eslint-disable-next-line vuejs-accessibility/label-has-for -->
  <label class="text-sm not-italic font-medium leading-5 text-primary-800">
    <slot />
  </label>
</template>

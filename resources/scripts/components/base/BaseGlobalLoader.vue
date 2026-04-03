<template>
  <div class="flex flex-col items-center justify-center h-screen">
    <MainLogo
      class="w-28 h-auto text-primary-400 mb-6"
      alt="InvoiceShelf Logo"
    />
    <div class="flex space-x-1.5">
      <span class="loader-dot" />
      <span class="loader-dot delay-150" />
      <span class="loader-dot delay-300" />
    </div>
  </div>
</template>

<script setup>
import MainLogo from '@/scripts/components/icons/MainLogo.vue'

const props = defineProps({
  showBgOverlay: {
    default: false,
    type: Boolean,
  },
})
</script>

<style>
.loader-dot {
  width: 0.625rem;
  height: 0.625rem;
  border-radius: 9999px;
  background-color: oklch(0.585 0.233 277.117);
  animation: loader-bounce 1.4s infinite ease-in-out both;
}

.loader-dot.delay-150 {
  animation-delay: 0.15s;
}

.loader-dot.delay-300 {
  animation-delay: 0.3s;
}

@keyframes loader-bounce {
  0%, 80%, 100% {
    transform: scale(0.4);
    opacity: 0.4;
  }
  40% {
    transform: scale(1);
    opacity: 1;
  }
}
</style>

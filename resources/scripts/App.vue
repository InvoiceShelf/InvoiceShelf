<template>
  <!-- Reka UI's menus, selects and tabs follow the reading direction -->
  <ConfigProvider :dir="direction">
    <router-view />
    <BaseDialog />
  </ConfigProvider>
</template>

<script setup lang="ts">
import { ConfigProvider } from 'reka-ui'
import { direction } from '@/scripts/utils/direction'
</script>

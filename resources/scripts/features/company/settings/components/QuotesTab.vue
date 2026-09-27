<script setup lang="ts">
import { provide } from 'vue'
import EstimatesTab from './EstimatesTab.vue'
provide('salesProposalKind', 'quote')
</script>
<template><EstimatesTab /></template>

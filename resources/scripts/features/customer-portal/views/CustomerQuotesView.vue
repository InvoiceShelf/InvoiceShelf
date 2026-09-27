<script setup lang="ts">
import { provide } from 'vue'
import CustomerEstimatesView from './CustomerEstimatesView.vue'
provide('salesProposalKind', 'quote')
</script>
<template><CustomerEstimatesView /></template>

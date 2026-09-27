<script setup lang="ts">
import { provide } from 'vue'
import CustomerEstimateDetailView from './CustomerEstimateDetailView.vue'
provide('salesProposalKind', 'quote')
</script>
<template><CustomerEstimateDetailView /></template>

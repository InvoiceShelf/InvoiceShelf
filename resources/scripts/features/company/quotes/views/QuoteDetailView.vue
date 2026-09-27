<script setup lang="ts">
import { provide } from 'vue'
import EstimateDetailView from '../../estimates/views/EstimateDetailView.vue'
provide('salesProposalKind', 'quote')
</script>
<template><EstimateDetailView /></template>

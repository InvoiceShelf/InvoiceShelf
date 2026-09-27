<script setup lang="ts">
import { provide } from 'vue'
import EstimateIndexView from '../../estimates/views/EstimateIndexView.vue'
provide('salesProposalKind', 'quote')
</script>
<template><EstimateIndexView /></template>

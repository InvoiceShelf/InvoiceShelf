<script setup lang="ts">
import { provide } from 'vue'
import EstimateCreateView from '../../estimates/views/EstimateCreateView.vue'
provide('salesProposalKind', 'quote')
</script>
<template><EstimateCreateView /></template>

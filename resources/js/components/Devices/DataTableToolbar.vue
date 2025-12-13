<script setup lang="ts">
import DataTableToolbar from '@/components/ui/DataTableToolbar.vue'
defineProps()
</script>

<template>
  <DataTableToolbar v-bind="$attrs" v-on="$listeners" />
</template>

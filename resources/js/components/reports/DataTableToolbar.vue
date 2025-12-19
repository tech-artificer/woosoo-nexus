<script setup lang="ts">
import DataTableToolbar from '@/components/ui/DataTableToolbar.vue'
import type { Table } from '@tanstack/vue-table'

defineProps<{
  table: Table<any>
}>()
</script>

<template>
  <DataTableToolbar :table="table" v-bind="$attrs" />
</template>

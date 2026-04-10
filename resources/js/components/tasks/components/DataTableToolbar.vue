<script setup lang="ts">
/* eslint-disable vue/valid-define-props */
import type { Table } from '@tanstack/vue-table'
import DataTableToolbar from '@/components/ui/DataTableToolbar.vue'
const props = defineProps<{ table: Table<any> }>()
</script>

<template>
  <DataTableToolbar :table="props.table" v-bind="$attrs" />
</template>

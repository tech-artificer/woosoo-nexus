<template>
  <div>
    <div class="mb-4">ServiceRequests DataTable (placeholder)</div>
    <table class="w-full border-collapse">
      <thead>
        <tr>
          <th v-for="col in columns" :key="col.key" class="text-left p-2 border">{{ col.label }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in data" :key="row.id" class="border-t">
          <td v-for="col in columns" :key="col.key" class="p-2 border">{{ row[col.key] }}</td>
        </tr>
      </tbody>
    </table>
    <div class="mt-4">
      <button @click="$emit('refresh')" class="px-3 py-1 border rounded">Refresh</button>
    </div>
  </div>
</template>

<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import { defineProps } from 'vue'
import type { Ref } from 'vue'
import { columns as defaultColumns } from './columns'

const props = defineProps({
  data: { type: Array, default: () => [] },
  columns: { type: Array, default: () => defaultColumns },
  pagination: { type: Object, default: null },
  filters: { type: Object, default: null },
  tableServices: { type: Array, default: () => [] },
})
</script>

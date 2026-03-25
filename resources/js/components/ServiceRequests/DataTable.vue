<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import { computed } from 'vue'
import { columns as defaultColumns } from './columns'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

const props = defineProps({
  data: { type: Array, default: () => [] },
  columns: { type: Array, default: () => defaultColumns },
  pagination: { type: Object, default: null },
  filters: { type: Object, default: null },
  tableServices: { type: Array, default: () => [] },
})

const emit = defineEmits<{
  (e: 'refresh'): void
  (e: 'row-click', row: any): void
}>()

const normalizedRows = computed(() => props.data ?? [])

const shouldIgnoreRowClick = (event: MouseEvent) => {
  const target = event.target as HTMLElement | null
  if (!target) return false
  return Boolean(target.closest('button') || target.closest('a') || target.closest('input'))
}

const handleRowClick = (event: MouseEvent, row: any) => {
  if (shouldIgnoreRowClick(event)) return
  emit('row-click', row)
}
</script>

<template>
  <div class="space-y-4">
    <div class="overflow-hidden rounded-md border bg-background">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead v-for="col in columns" :key="(col as any).key">{{ (col as any).label }}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="row in normalizedRows"
            :key="(row as any).id"
            class="cursor-pointer transition-colors hover:bg-muted/50"
            @click="handleRowClick($event, row)"
          >
            <TableCell v-for="col in columns" :key="(col as any).key">
              {{ (row as any)[(col as any).key] }}
            </TableCell>
          </TableRow>
          <TableRow v-if="normalizedRows.length === 0">
            <TableCell :colspan="columns.length" class="h-24 text-center">No service requests found.</TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <div class="flex justify-end">
      <Button variant="outline" size="sm" @click="$emit('refresh')">Refresh</Button>
    </div>
  </div>
</template>

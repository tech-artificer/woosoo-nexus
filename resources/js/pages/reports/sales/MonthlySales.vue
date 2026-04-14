<script setup lang="ts">
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"


defineProps<{
  rows: any[],
}>();

const monthLabel = (monthNumber: unknown): string => {
  const value = Number(monthNumber)
  if (!Number.isFinite(value) || value < 1 || value > 12) {
    return '-'
  }

  return new Date(Date.UTC(2000, value - 1, 1)).toLocaleString('default', { month: 'long' })
}

</script>

<template>

  <Table>
    <TableCaption>A list of your recent invoices.</TableCaption>
    <TableHeader>
      <TableRow>
        <TableHead class="w-[100px]">
          Date
        </TableHead>
        <TableHead>Sales</TableHead>
        <TableHead>Customers</TableHead>
        <TableHead class="text-right">
          Cost
        </TableHead>
      </TableRow>
    </TableHeader>
    <TableBody>
      <TableRow v-for="row in rows" :key="row.DATE">
        <TableCell class="font-medium">
          {{ monthLabel(row.DATE) }}
        </TableCell>
        <!-- format number with 2 decimal and comma -->
        <TableCell>
            {{ new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(row.sales ?? 0) }}
        </TableCell>
        <TableCell>{{ row.customers ?? 0 }}</TableCell>
        <TableCell class="text-right">
          {{ row.labor_cost ?? 0 }}
        </TableCell>
      </TableRow>
    </TableBody>
  </Table>
</template>
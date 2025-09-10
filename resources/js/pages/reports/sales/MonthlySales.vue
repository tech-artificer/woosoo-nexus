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
      <TableRow v-for="row in rows" :key="row.date">
        <TableCell class="font-medium">
            <!-- TODO : Convert to date, 1 means january .. . -->
          {{ new Date(Date.UTC(0, row.DATE - 1)).toLocaleString('default', { month: 'long' }) ?? "-" }}
        </TableCell>
        <!-- format number with 2 decimal and comma -->
        <TableCell>
            <!-- TODO : Convert to date, 1 means january .. . -->
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
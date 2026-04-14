<script setup lang="ts">
import { LineChart } from "@/components/ui/chart-line"
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

interface SalesDataItem {
  date: string
  sales: number
  orders: number
}

const page = usePage()
const salesData = computed(() => {
  const data = page.props?.salesData
  return Array.isArray(data) ? data as SalesDataItem[] : []
})

const data = computed(() => {
  if (!salesData.value || salesData.value.length === 0) {
    return [{
      date: 'No data',
      Sales: 0,
      Orders: 0,
    }]
  }
  
  return salesData.value.map((item) => ({
    date: item.date,
    Sales: item.sales,
    Orders: item.orders,
  }))
})
</script>

<template>
  <LineChart
    :data="data"
    index="date"
    :categories="['Sales', 'Orders']"
    :y-formatter="(tick: number | Date, i: number) => {
      return typeof tick === 'number'
        ? `${new Intl.NumberFormat('en-PH').format(tick).toString()}`
        : ''
    }"
  />
</template>

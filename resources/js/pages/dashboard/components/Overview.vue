<script setup lang="ts">
import { BarChart } from '@/components/ui/chart-bar'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

interface SalesDataItem {
  date: string
  sales: number
  orders: number
}

const page = usePage()

const data = computed(() => {
  const raw = page.props.salesData as SalesDataItem[] | undefined
  if (!Array.isArray(raw) || raw.length === 0) return []
  return raw.map(item => ({
    date: item.date,
    Sales: item.sales,
  }))
})

const hasData = computed(() => data.value.length > 0)
</script>

<template>
  <BarChart
    v-if="hasData"
    :data="data"
    index="date"
    :categories="['Sales']"
    :y-formatter="(tick: number | Date) => typeof tick === 'number' ? `₱${new Intl.NumberFormat('en-PH').format(tick)}` : ''"
  />
  <div v-else class="flex flex-col items-center justify-center h-40 text-sm text-muted-foreground gap-2 opacity-70">
    <p>No data available</p>
  </div>
</template>
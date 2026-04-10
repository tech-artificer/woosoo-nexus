<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { DonutChart } from '@/components/ui/chart-donut'

interface TopItem {
  name: string
  qty: number
  revenue: number
}

const page = usePage()
const data = computed(() => {
  const items = page.props.topItems as TopItem[] | undefined
  if (!Array.isArray(items) || items.length === 0) return []
  return items.map(item => ({ name: item.name, Orders: item.qty }))
})

const hasData = computed(() => data.value.length > 0)
</script>

<template>
  <DonutChart
    v-if="hasData"
    index="name"
    :category="'Orders'"
    :data="data"
    :type="'pie'"
    :value-formatter="(v: number | Date) => typeof v === 'number' ? `${v} orders` : ''"
  />
  <div v-else class="flex items-center justify-center h-20 text-sm text-muted-foreground opacity-70">
    No data yet today
  </div>
</template>
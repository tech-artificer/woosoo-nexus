<script setup lang="ts">
import { DonutChart } from '@/components/ui/chart-donut'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

interface TopItem {
  name: string
  qty: number
  revenue: number
}

const page = usePage()

const data = computed(() => {
  const items = page.props.topItems as TopItem[] | undefined
  if (!Array.isArray(items) || items.length === 0) return []
  return items.map(item => ({
    name: item.name,
    Orders: item.qty,
  }))
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
  <div
    v-else
    class="flex flex-col items-center justify-center h-48 text-center text-muted-foreground gap-2"
  >
    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
    </svg>
    <p class="text-sm">No item data for today</p>
  </div>
</template>
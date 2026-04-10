<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'

interface TopItem {
  name: string
  qty: number
  revenue: number
}

const page = usePage()
const topItems = computed(() => {
  const items = page.props.topItems as TopItem[] | undefined
  return Array.isArray(items) ? items.slice(0, 5) : []
})

const formatPHP = (value: number) =>
  '₱' + new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value)
</script>

<template>
  <div v-if="topItems.length > 0" class="space-y-4">
    <div v-for="(item, idx) in topItems" :key="item.name" class="flex items-center justify-between gap-2">
      <div class="flex items-center gap-3 min-w-0">
        <span class="w-6 h-6 rounded-full bg-muted flex items-center justify-center text-xs font-bold shrink-0">
          {{ idx + 1 }}
        </span>
        <span class="text-sm font-medium truncate">{{ item.name }}</span>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <span class="text-xs text-muted-foreground">{{ item.qty }}x</span>
        <Badge variant="secondary" class="text-xs font-mono">{{ formatPHP(item.revenue) }}</Badge>
      </div>
    </div>
  </div>
  <div v-else class="flex items-center justify-center h-20 text-sm text-muted-foreground opacity-70">
    No sales yet today
  </div>
</template>
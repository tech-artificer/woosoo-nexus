<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { computed } from 'vue'

const props = defineProps<{
  stats: {
    total_pending?: number
    total_active?: number
    total_today?: number
    avg_response_time?: number
  }
}>()

const statsCards = computed(() => [
  { title: 'Total Pending', value: props.stats?.total_pending ?? 0, variant: 'warning' },
  { title: 'Total Active', value: props.stats?.total_active ?? 0, variant: 'primary' },
  { title: 'Today', value: props.stats?.total_today ?? 0, variant: 'accent' },
  { title: 'Avg Response', value: `${props.stats?.avg_response_time ?? 0}m`, variant: 'default' },
])
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <Card v-for="card in statsCards" :key="card.title">
      <CardHeader class="flex flex-row items-center justify-between pb-2">
        <CardTitle class="text-sm font-medium text-muted-foreground">{{ card.title }}</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="text-2xl font-bold">{{ card.value }}</div>
      </CardContent>
    </Card>
  </div>
</template>

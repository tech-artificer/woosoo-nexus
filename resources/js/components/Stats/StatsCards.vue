<script setup lang="ts">
import { type PropType, computed } from 'vue'
import type { Component } from 'vue'

interface StatCard {
  title: string
  value: string | number
  subtitle?: string
  variant?: 'default' | 'primary' | 'accent' | 'danger'
  icon?: Component
  delta?: number // percent change, optional
}

const props = defineProps<{ cards: StatCard[] }>()

const getAccent = (variant: StatCard['variant']) => {
  switch (variant) {
    case 'primary':
      return 'bg-blue-50 border-blue-100 text-blue-700'
    case 'accent':
      return 'bg-emerald-50 border-emerald-100 text-emerald-700'
    case 'danger':
      return 'bg-rose-50 border-rose-100 text-rose-700'
    default:
      return 'bg-white border-gray-100 text-gray-900'
  }
}

const getSparkPoints = (arr?: number[]) => {
  if (!arr || !arr.length) return ''
  const width = 100
  const height = 24
  const n = arr.length
  const min = Math.min(...arr)
  const max = Math.max(...arr)
  const range = max - min || 1
  const points = arr.map((v, i) => {
    const x = n === 1 ? width / 2 : (i * (width / (n - 1)))
    const y = Math.round((1 - (v - min) / range) * height)
    return `${x},${y}`
  })
  return points.join(' ')
}
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div v-for="card in cards" :key="card.title" :class="['p-4 rounded-lg shadow-sm border', getAccent(card.variant)]">
      <div class="flex items-start justify-between gap-4">
        <div class="flex-1">
          <div class="text-xs text-muted-foreground font-medium">{{ card.title }}</div>
          <div class="mt-1 text-3xl font-bold leading-tight">{{ card.value }}</div>
        </div>
        <div class="flex flex-col items-end">
          <component v-if="card.icon" :is="card.icon" class="h-6 w-6 opacity-80" />
          <div v-if="typeof card.delta !== 'undefined'" :class="['mt-2 text-sm font-medium', card.delta >= 0 ? 'text-emerald-600' : 'text-rose-600']">
            <span v-if="card.delta >= 0">+{{ card.delta }}%</span>
            <span v-else>{{ card.delta }}%</span>
          </div>
        </div>
      </div>
      <div v-if="card.subtitle" class="mt-2 text-sm text-muted-foreground">{{ card.subtitle }}</div>
      <div v-if="card.sparkline && card.sparkline.length" class="mt-3">
        <svg viewBox="0 0 100 24" preserveAspectRatio="none" class="w-full h-6">
          <polyline :points="getSparkPoints(card.sparkline)" fill="none" stroke="currentColor" stroke-width="1.5" class="opacity-70" />
        </svg>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
export default {}
</script>

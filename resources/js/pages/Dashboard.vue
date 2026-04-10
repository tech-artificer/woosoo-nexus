<script setup lang="ts">
import { computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import {
  type LucideIcon,
  ChartSpline,
  Contact,
  ArrowUp10,
  ChartPie,
  Wifi,
  WifiOff,
  ClipboardList,
  TrendingUp,
} from 'lucide-vue-next'

import LineChart from '@/components/charts/LineChart.vue'
import DonutChart from '@/components/charts/DonutChart.vue'

interface TopItem {
  name: string
  qty: number
  revenue: number
}

interface ReverbStatus {
  ok: boolean
  host: string
  port: number
  latencyMs: number
  error?: string
  checkedAt: string
}

const props = defineProps<{
  title?: string
  description?: string
  tableOrders: any[]
  openOrders: any[]
  sessionId?: number
  totalSales: string | number
  guestCount: string | number
  totalOrders: string | number
  monthlySales: string | number
  salesData?: any[]
  topItems?: TopItem[]
  reverbStatus?: ReverbStatus
  devices?: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
]

const formatPHP = (value: string | number) =>
  '₱' + new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(String(value).replace(/,/g, '')))

interface DashCard {
  title: string
  value: string | number
  icon: LucideIcon
  helpText: string
  colorClass: string
}

const dashCards = computed<DashCard[]>(() => [
  {
    title: 'Total Sales Today',
    value: formatPHP(props.totalSales),
    icon: ChartSpline,
    helpText: `${props.totalOrders} Transactions`,
    colorClass: 'text-woosoo-accent',
  },
  {
    title: "Today's Orders",
    value: props.totalOrders,
    icon: ArrowUp10,
    helpText: 'Completed orders today',
    colorClass: 'text-woosoo-green',
  },
  {
    title: 'Total Guests',
    value: props.guestCount,
    icon: Contact,
    helpText: 'Guests served today',
    colorClass: 'text-woosoo-blue',
  },
  {
    title: 'Monthly Sales',
    value: formatPHP(props.monthlySales),
    icon: ChartPie,
    helpText: new Date().toLocaleString('default', { month: 'long', year: 'numeric' }),
    colorClass: 'text-woosoo-primary-dark',
  },
])

const hasSalesData = computed(() => Array.isArray(props.salesData) && props.salesData.length > 0)
const hasTopItems = computed(() => Array.isArray(props.topItems) && props.topItems.length > 0)
const topItems = computed(() => (props.topItems ?? []).slice(0, 5))

// Latest open orders for the quick-view table
const liveOrders = computed(() => (props.openOrders ?? []).slice(0, 5))

// Device summary
const activeDevices = computed(() => (props.devices ?? []).filter((d: any) => d.is_active).length)
const totalDevices = computed(() => (props.devices ?? []).length)
</script>

<template>
  <Head :title="props.title ?? 'Dashboard'" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6 p-1">

      <!-- Page heading + Reverb status -->
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold tracking-tight font-header">Overview</h1>
          <p class="text-muted-foreground text-sm">Welcome to the Woosoo admin dashboard</p>
        </div>
        <div v-if="reverbStatus" class="flex items-center gap-1.5 text-sm">
          <component
            :is="reverbStatus.ok ? Wifi : WifiOff"
            :size="15"
            :class="reverbStatus.ok ? 'text-emerald-500' : 'text-destructive'"
          />
          <span :class="reverbStatus.ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive'">
            {{ reverbStatus.ok ? `Reverb live (${reverbStatus.latencyMs}ms)` : 'Reverb offline' }}
          </span>
        </div>
      </div>

      <!-- KPI cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <Card
          v-for="card in dashCards"
          :key="card.title"
          class="relative overflow-hidden border border-border transition-shadow duration-200 hover:shadow-md"
        >
          <span
            class="absolute top-0 inset-x-0 h-0.5 rounded-t-[inherit]"
            :class="`bg-current ${card.colorClass}`"
            aria-hidden="true"
          />
          <CardHeader class="flex flex-row items-center justify-between p-4 pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">{{ card.title }}</CardTitle>
            <div class="rounded-lg p-1.5" :class="`bg-current/8 ${card.colorClass}`">
              <component :is="card.icon" :size="18" :class="card.colorClass" />
            </div>
          </CardHeader>
          <CardContent class="px-4 pb-4 pt-0">
            <div class="text-3xl font-black tracking-tight">{{ card.value }}</div>
            <p class="text-xs text-muted-foreground mt-1">{{ card.helpText }}</p>
          </CardContent>
        </Card>
      </div>

      <!-- Charts row -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- Sales trend (takes 2/3 width) -->
        <Card class="lg:col-span-2">
          <CardHeader class="pb-2">
            <CardTitle class="text-base">7-Day Sales Trend</CardTitle>
            <CardDescription>Daily sales and order volume for the past week</CardDescription>
          </CardHeader>
          <CardContent>
            <template v-if="hasSalesData">
              <LineChart />
            </template>
            <div
              v-else
              class="flex flex-col items-center justify-center rounded-lg border border-dashed border-border h-48 gap-2 text-muted-foreground"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
              </svg>
              <p class="text-sm">No sales data for today</p>
            </div>
          </CardContent>
        </Card>

        <!-- Top items donut (1/3 width) -->
        <Card>
          <CardHeader class="pb-2">
            <CardTitle class="text-base">Top Items Today</CardTitle>
            <CardDescription>Most ordered menu items</CardDescription>
          </CardHeader>
          <CardContent>
            <DonutChart />
          </CardContent>
        </Card>
      </div>

      <!-- Bottom row: Top items table + Live orders preview + Devices -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- Top items ranked list -->
        <Card>
          <CardHeader class="pb-3">
            <div class="flex items-center gap-2">
              <TrendingUp :size="16" class="text-muted-foreground" />
              <CardTitle class="text-base">Best Sellers</CardTitle>
            </div>
            <CardDescription>Top 5 menu items by quantity today</CardDescription>
          </CardHeader>
          <CardContent>
            <div v-if="hasTopItems" class="space-y-3">
              <div
                v-for="(item, idx) in topItems"
                :key="item.name"
                class="flex items-center justify-between gap-3"
              >
                <div class="flex items-center gap-2 min-w-0">
                  <span class="text-xs font-bold w-5 text-muted-foreground shrink-0">{{ idx + 1 }}</span>
                  <span class="text-sm font-medium truncate">{{ item.name }}</span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <span class="text-xs text-muted-foreground">{{ item.qty }}x</span>
                  <Badge variant="secondary" class="text-xs">{{ formatPHP(item.revenue) }}</Badge>
                </div>
              </div>
            </div>
            <div v-else class="flex flex-col items-center justify-center h-32 text-center text-sm text-muted-foreground gap-2 opacity-70">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
              No sales today yet
            </div>
          </CardContent>
        </Card>

        <!-- Live orders quick view (takes 2/3 width) -->
        <Card class="lg:col-span-2">
          <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <ClipboardList :size="16" class="text-muted-foreground" />
                <CardTitle class="text-base">Open Orders</CardTitle>
              </div>
              <Badge v-if="openOrders?.length" variant="secondary">
                {{ openOrders.length }} active
              </Badge>
            </div>
            <CardDescription>Most recent active orders</CardDescription>
          </CardHeader>
          <CardContent>
            <div v-if="liveOrders.length > 0" class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b">
                    <th class="text-left py-2 px-3 font-medium text-muted-foreground">Order #</th>
                    <th class="text-left py-2 px-3 font-medium text-muted-foreground">Table</th>
                    <th class="text-left py-2 px-3 font-medium text-muted-foreground">Status</th>
                    <th class="text-right py-2 px-3 font-medium text-muted-foreground">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="order in liveOrders"
                    :key="order.id ?? order.order_uuid"
                    class="border-b last:border-0 hover:bg-muted/50"
                  >
                    <td class="py-2 px-3 font-mono text-xs">{{ order.order_number ?? `#${order.id}` }}</td>
                    <td class="py-2 px-3">{{ order.table?.name ?? order.table_name ?? '—' }}</td>
                    <td class="py-2 px-3">
                      <Badge variant="outline" class="text-xs capitalize">{{ order.status }}</Badge>
                    </td>
                    <td class="py-2 px-3 text-right font-semibold">{{ formatPHP(order.total ?? 0) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="flex flex-col items-center justify-center h-32 text-center text-sm text-muted-foreground gap-2 opacity-70">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
              No active orders right now
            </div>
          </CardContent>
        </Card>
      </div>

    </div>
  </AppLayout>
</template>
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Link } from '@inertiajs/vue3'
import type { BreadcrumbItem } from '@/types'
import {
  BarChart2,
  UtensilsCrossed,
  Clock,
  Users,
  ClipboardList,
  Printer,
  Tag,
} from 'lucide-vue-next'

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'Reports', href: route('reports.index') },
]

const reportLinks = [
  {
    title: 'Daily Sales',
    description: 'Sales totals, transaction counts, and average order values by day.',
    icon: BarChart2,
    href: route('reports.daily-sales'),
    color: 'text-woosoo-green',
  },
  {
    title: 'Menu Items',
    description: 'Top-selling menu items, revenue by item, and package bestsellers.',
    icon: UtensilsCrossed,
    href: route('reports.menu-items'),
    color: 'text-woosoo-orange',
  },
  {
    title: 'Hourly Sales',
    description: 'Identify peak hours with hourly breakdown of orders and revenue.',
    icon: Clock,
    href: route('reports.hourly-sales'),
    color: 'text-woosoo-blue',
  },
  {
    title: 'Guest Count',
    description: 'Total guests served per day and average covers per order.',
    icon: Users,
    href: route('reports.guest-count'),
    color: 'text-woosoo-blue',
  },
  {
    title: 'Order Status',
    description: 'Breakdown of orders by status - completed, voided, cancelled.',
    icon: ClipboardList,
    href: route('reports.order-status'),
    color: 'text-woosoo-blue',
  },
  {
    title: 'Print Audit',
    description: 'Track print jobs - successful prints, failed attempts, retry counts.',
    icon: Printer,
    href: route('reports.print-audit'),
    color: 'text-destructive',
  },
  {
    title: 'Discount & Tax',
    description: 'Total discounts applied and tax collected across all orders.',
    icon: Tag,
    href: route('reports.discount-tax'),
    color: 'text-woosoo-accent',
  },
]
</script>

<template>
  <Head title="Reports" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-5">
      <div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
        <div class="relative space-y-3">
          <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">Analytics</span>
          <div>
            <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">Reports</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">Sales performance, guest trends, print audit trails, and operational breakdowns across the active date range.</p>
          </div>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <Card
          v-for="item in reportLinks"
          :key="item.title"
          class="transition-all duration-200 hover:shadow-md hover:border-woosoo-accent/30 group"
        >
          <CardHeader class="pb-3">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div :class="`p-2 rounded-lg bg-muted ${item.color}`">
                  <component :is="item.icon" :size="18" />
                </div>
                <CardTitle class="text-base">{{ item.title }}</CardTitle>
              </div>
            </div>
            <CardDescription class="mt-2">{{ item.description }}</CardDescription>
          </CardHeader>
          <CardContent class="pt-0">
            <Button variant="outline" size="sm" as-child class="w-full group-hover:border-woosoo-accent/50 group-hover:text-foreground transition-colors">
              <Link :href="item.href">View Report</Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { DonutChart } from '@/components/ui/chart-donut'
import { ref } from 'vue'
import type { BreadcrumbItem } from '@/types'

interface OrderStatusData {
    status: string
    order_count: number
    total_revenue: number
    avg_order_value: number
    total_guests: number
}

interface PageProps {
    title: string
    data: OrderStatusData[]
    meta: Record<string, any>
    startDate?: string
    endDate?: string
}

const props = defineProps<PageProps>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: route('reports.index') },
    { title: props.title, href: '#' },
]

const chartData = ref(
    props.data.map(row => ({
        name: row.status,
        value: row.order_count,
    }))
)

const totalOrders = (props.data ?? []).reduce((sum, row) => sum + row.order_count, 0)
const totalRevenue = (props.data ?? []).reduce((sum, row) => sum + row.total_revenue, 0)

const getStatusColor = (status: string): 'success' | 'warning' | 'destructive' | 'secondary' | 'outline' => {
    const s = status.toUpperCase()
    if (s === 'COMPLETED') return 'success'
    if (s === 'CONFIRMED' || s === 'SERVED' || s === 'READY') return 'secondary'
    if (s === 'VOIDED' || s === 'CANCELLED') return 'destructive'
    if (s === 'PENDING' || s === 'IN_PROGRESS') return 'warning'
    return 'outline'
}
</script>

<template>

    <Head :title="props.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5">
            <!-- Hero -->
            <div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
                <div class="relative space-y-3">
                    <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">Analytics · Order Status</span>
                    <div>
                        <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">{{ props.title }}</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">Understand order completion and status distribution.</p>
                    </div>
                </div>
            </div>

            <!-- Date range -->
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Date range:</span>
                <span class="text-sm font-medium">{{ props.startDate ?? '—' }}</span>
                <span class="text-muted-foreground">→</span>
                <span class="text-sm font-medium">{{ props.endDate ?? 'today' }}</span>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Total Orders</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalOrders }}</div>
                        <p class="text-xs text-muted-foreground mt-1">All statuses</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Total Revenue</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ "₱" + new Intl.NumberFormat("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}).format(totalRevenue) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">From all statuses</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Status Count</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ props.data.length }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Unique statuses</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Avg Order Value</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">₱{{ totalOrders > 0 ? new Intl.NumberFormat('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}).format(totalRevenue / totalOrders) : '0.00' }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Across all orders</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Chart -->
            <Card>
                <CardHeader>
                    <CardTitle>Order Status Distribution</CardTitle>
                    <CardDescription>Number of orders by status</CardDescription>
                </CardHeader>
                <CardContent>
                    <DonutChart :data="chartData" category="value" index="name"
                        :colors="['#10b981', '#3b82f6', '#f59e0b', '#ef4444']"
                            :valueFormatter="(value) => Number(value).toFixed(0)" />
                </CardContent>
            </Card>

            <!-- Status Breakdown -->
            <Card>
                <CardHeader>
                    <CardTitle>Status Breakdown</CardTitle>
                    <CardDescription>Detailed metrics by order status</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-for="row in props.data" :key="row.status"
                            class="flex items-center justify-between rounded-xl border border-black/8 px-4 py-3 dark:border-white/10">
                            <div class="flex items-center gap-3">
                                <Badge :variant="getStatusColor(row.status)">{{ row.status }}</Badge>
                                <div>
                                    <div class="font-semibold">{{ row.order_count }} orders</div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ (totalOrders > 0 ? ((row.order_count / totalOrders) * 100) : 0).toFixed(1) }}% of total
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-bold">₱{{ new Intl.NumberFormat('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}).format(row.total_revenue) }}</div>
                                <div class="text-sm text-muted-foreground">Avg: ₱{{ new Intl.NumberFormat('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}).format(row.avg_order_value) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Data Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Status Details</CardTitle>
                    <CardDescription>Complete status breakdown with metrics</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-black/8 dark:border-white/10">
                                    <th class="px-4 py-3 text-left text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Order Count</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">% of Total</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Total Revenue</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Avg Order Value</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Total Guests</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in props.data" :key="row.status" class="border-b border-black/6 transition-colors hover:bg-black/[0.025] dark:border-white/8 dark:hover:bg-white/[0.03]">
                                    <td class="py-3 px-4">
                                        <Badge :variant="getStatusColor(row.status)">{{ row.status }}</Badge>
                                    </td>
                                    <td class="text-right py-3 px-4">{{ row.order_count }}</td>
                                    <td class="text-right py-3 px-4">{{ (totalOrders > 0 ? ((row.order_count / totalOrders) *
                                        100) : 0).toFixed(1) }}%</td>
                                    <td class="text-right py-3 px-4">₱{{ new Intl.NumberFormat('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}).format(row.total_revenue) }}</td>
                                    <td class="text-right py-3 px-4">₱{{ new Intl.NumberFormat('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}).format(row.avg_order_value) }}</td>
                                    <td class="text-right py-3 px-4">{{ row.total_guests }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

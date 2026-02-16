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
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Reports', href: '#' },
    { label: props.title, href: '#' },
]

const chartData = ref(
    props.data.map(row => ({
        name: row.status,
        value: row.order_count,
    }))
)

const totalOrders = props.data.reduce((sum, row) => sum + row.order_count, 0)
const totalRevenue = props.data.reduce((sum, row) => sum + row.total_revenue, 0)

const getStatusColor = (status: string) => {
    return status === 'COMPLETED' ? 'default' : status === 'CONFIRMED' ? 'secondary' : 'outline'
}
</script>

<template>

    <Head :title="props.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">{{ props.title }}</h1>
                    <p class="text-sm text-muted-foreground mt-1">Understand order completion and status distribution
                    </p>
                </div>
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
                        <div class="text-2xl font-bold">${{ totalRevenue.toFixed(2) }}</div>
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
                        <div class="text-2xl font-bold">${{ totalOrders > 0 ? (totalRevenue / totalOrders).toFixed(2) :
                            '0.00' }}</div>
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
                        :valueFormatter="(value: number) => value.toFixed(0)" />
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
                            class="flex items-center justify-between p-4 border rounded-lg">
                            <div class="flex items-center gap-3">
                                <Badge :variant="getStatusColor(row.status)">{{ row.status }}</Badge>
                                <div>
                                    <div class="font-semibold">{{ row.order_count }} orders</div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ ((row.order_count / totalOrders) * 100).toFixed(1) }}% of total
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-bold">${{ row.total_revenue.toFixed(2) }}</div>
                                <div class="text-sm text-muted-foreground">Avg: ${{ row.avg_order_value.toFixed(2) }}
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
                                <tr class="border-b">
                                    <th class="text-left py-3 px-4 font-semibold">Status</th>
                                    <th class="text-right py-3 px-4 font-semibold">Order Count</th>
                                    <th class="text-right py-3 px-4 font-semibold">% of Total</th>
                                    <th class="text-right py-3 px-4 font-semibold">Total Revenue</th>
                                    <th class="text-right py-3 px-4 font-semibold">Avg Order Value</th>
                                    <th class="text-right py-3 px-4 font-semibold">Total Guests</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in props.data" :key="row.status" class="border-b hover:bg-muted/50">
                                    <td class="py-3 px-4">
                                        <Badge :variant="getStatusColor(row.status)">{{ row.status }}</Badge>
                                    </td>
                                    <td class="text-right py-3 px-4">{{ row.order_count }}</td>
                                    <td class="text-right py-3 px-4">{{ ((row.order_count / totalOrders) *
                                        100).toFixed(1) }}%</td>
                                    <td class="text-right py-3 px-4">${{ row.total_revenue.toFixed(2) }}</td>
                                    <td class="text-right py-3 px-4">${{ row.avg_order_value.toFixed(2) }}</td>
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

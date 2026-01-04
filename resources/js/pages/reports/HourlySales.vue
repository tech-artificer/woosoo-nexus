<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { LineChart } from '@/components/ui/chart-line'
import { ref } from 'vue'
import type { BreadcrumbItem } from '@/types'

interface HourlySalesData {
    hour: number
    hour_label: string
    transaction_count: number
    total_sales: number
    avg_order_value: number
}

interface PageProps {
    title: string
    data: HourlySalesData[]
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
        hour: row.hour_label,
        'Sales': row.total_sales,
        'Transactions': row.transaction_count,
    }))
)

const peakHour = props.data.reduce((max, curr) =>
    curr.total_sales > max.total_sales ? curr : max,
    props.data[0]
)

const totalSales = props.data.reduce((sum, row) => sum + row.total_sales, 0)
const totalTransactions = props.data.reduce((sum, row) => sum + row.transaction_count, 0)
</script>

<template>

    <Head :title="props.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">{{ props.title }}</h1>
                    <p class="text-sm text-muted-foreground mt-1">Identify peak hours and sales patterns throughout the
                        day</p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Total Sales</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">${{ totalSales.toFixed(2) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">All hours</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Total Transactions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalTransactions }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Across all hours</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Peak Hour</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ peakHour.hour_label }}</div>
                        <p class="text-xs text-muted-foreground mt-1">${{ peakHour.total_sales.toFixed(2) }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Hours with Sales</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ props.data.length }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Operating hours</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Chart -->
            <Card>
                <CardHeader>
                    <CardTitle>Hourly Sales Pattern</CardTitle>
                    <CardDescription>Sales and transaction volume by hour of day</CardDescription>
                </CardHeader>
                <CardContent>
                    <LineChart :data="chartData" index="hour" :categories="['Sales', 'Transactions']"
                        :colors="['#10b981', '#3b82f6']"
                        :valueFormatter="(value: number) => typeof value === 'number' ? `$${value.toFixed(0)}` : value" />
                </CardContent>
            </Card>

            <!-- Data Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Hourly Breakdown</CardTitle>
                    <CardDescription>Detailed hourly sales metrics</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-3 px-4 font-semibold">Hour</th>
                                    <th class="text-right py-3 px-4 font-semibold">Transactions</th>
                                    <th class="text-right py-3 px-4 font-semibold">Total Sales</th>
                                    <th class="text-right py-3 px-4 font-semibold">Avg Order</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in props.data" :key="row.hour" class="border-b hover:bg-muted/50"
                                    :class="{ 'bg-amber-50': row.hour === peakHour.hour }">
                                    <td class="py-3 px-4 font-medium">{{ row.hour_label }}</td>
                                    <td class="text-right py-3 px-4">{{ row.transaction_count }}</td>
                                    <td class="text-right py-3 px-4">${{ row.total_sales.toFixed(2) }}</td>
                                    <td class="text-right py-3 px-4">${{ row.avg_order_value.toFixed(2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

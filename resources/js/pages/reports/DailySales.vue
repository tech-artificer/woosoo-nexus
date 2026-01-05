<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { LineChart } from '@/components/ui/chart-line'
import { ref } from 'vue'
import type { BreadcrumbItem } from '@/types'

interface DailySalesData {
    date: string
    transaction_count: number
    total_sales: number
    avg_order_value: number
    total_guests: number
}

interface PageProps {
    title: string
    data: DailySalesData[]
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
        date: row.date,
        'Total Sales': row.total_sales,
        'Transactions': row.transaction_count,
    }))
)

const totalSales = props.data.reduce((sum, row) => sum + row.total_sales, 0)
const totalTransactions = props.data.reduce((sum, row) => sum + row.transaction_count, 0)
const avgOrderValue = totalTransactions > 0 ? totalSales / totalTransactions : 0
const totalGuests = props.data.reduce((sum, row) => sum + row.total_guests, 0)
</script>

<template>

    <Head :title="props.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">{{ props.title }}</h1>
                    <p class="text-sm text-muted-foreground mt-1">Analyze daily sales performance and trends</p>
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
                        <p class="text-xs text-muted-foreground mt-1">{{ props.data.length }} days</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Total Transactions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalTransactions }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Orders completed</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Avg Order Value</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">${{ avgOrderValue.toFixed(2) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Per transaction</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Total Guests</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalGuests }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Served</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Chart -->
            <Card>
                <CardHeader>
                    <CardTitle>Sales Trend</CardTitle>
                    <CardDescription>Daily sales and transaction volume</CardDescription>
                </CardHeader>
                <CardContent>
                    <LineChart :data="chartData" index="date" :categories="['Total Sales', 'Transactions']"
                        :colors="['#10b981', '#3b82f6']"
                        :valueFormatter="(value: number) => typeof value === 'number' ? `$${value.toFixed(0)}` : value" />
                </CardContent>
            </Card>

            <!-- Data Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Daily Breakdown</CardTitle>
                    <CardDescription>Detailed daily sales data</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-3 px-4 font-semibold">Date</th>
                                    <th class="text-right py-3 px-4 font-semibold">Transactions</th>
                                    <th class="text-right py-3 px-4 font-semibold">Total Sales</th>
                                    <th class="text-right py-3 px-4 font-semibold">Avg Order</th>
                                    <th class="text-right py-3 px-4 font-semibold">Guests</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in props.data" :key="row.date" class="border-b hover:bg-muted/50">
                                    <td class="py-3 px-4">{{ row.date }}</td>
                                    <td class="text-right py-3 px-4">{{ row.transaction_count }}</td>
                                    <td class="text-right py-3 px-4">${{ row.total_sales.toFixed(2) }}</td>
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

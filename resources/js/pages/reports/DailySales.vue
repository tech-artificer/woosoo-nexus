<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { LineChart } from '@/components/ui/chart-line'
import ReportDateRangeToolbar from '@/components/reports/ReportDateRangeToolbar.vue'
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
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: route('reports.index') },
    { title: props.title, href: '#' },
]

const chartData = ref(
    props.data.map(row => ({
        date: row.date,
        'Total Sales': row.total_sales,
        'Transactions': row.transaction_count,
    }))
)

const totalSales = (props.data ?? []).reduce((sum, row) => sum + row.total_sales, 0)
const totalTransactions = (props.data ?? []).reduce((sum, row) => sum + row.transaction_count, 0)
const avgOrderValue = totalTransactions > 0 ? totalSales / totalTransactions : 0
const totalGuests = (props.data ?? []).reduce((sum, row) => sum + row.total_guests, 0)

const currencyFormatter = (value: unknown) => {
    return typeof value === 'number'
        ? '₱' + new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value)
        : String(value)
}
</script>

<template>

    <Head :title="props.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5">
            <!-- Hero -->
            <div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
                <div class="relative space-y-3">
                    <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">Analytics · Daily Sales</span>
                    <div>
                        <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">{{ props.title }}</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">Analyze daily sales performance and trends.</p>
                    </div>
                </div>
            </div>

            <!-- Date range -->
            <ReportDateRangeToolbar
              :start-date="props.startDate"
              :end-date="props.endDate"
              :export-route="route('reports.daily-sales.export')"
            />

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Total Sales</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ currencyFormatter(totalSales) }}</div>
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
                        <div class="text-2xl font-bold">{{ currencyFormatter(avgOrderValue) }}</div>
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
                        :valueFormatter="currencyFormatter" />
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
                                <tr class="border-b border-black/8 dark:border-white/10">
                                    <th class="px-4 py-3 text-left text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Date</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Transactions</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Total Sales</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Avg Order</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Guests</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in props.data" :key="row.date" class="border-b border-black/6 transition-colors hover:bg-black/[0.025] dark:border-white/8 dark:hover:bg-white/[0.03]">
                                    <td class="py-3 px-4">{{ row.date }}</td>
                                    <td class="text-right py-3 px-4">{{ row.transaction_count }}</td>
                                    <td class="text-right py-3 px-4">{{ currencyFormatter(row.total_sales) }}</td>
                                    <td class="text-right py-3 px-4">{{ currencyFormatter(row.avg_order_value) }}</td>
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

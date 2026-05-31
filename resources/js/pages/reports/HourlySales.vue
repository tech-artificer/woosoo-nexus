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
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: route('reports.index') },
    { title: props.title, href: '#' },
]

const chartData = ref(
    props.data.map(row => ({
        hour: row.hour_label,
        'Sales': row.total_sales,
        'Transactions': row.transaction_count,
    }))
)

const peakHour = (props.data ?? []).length ? (props.data ?? []).reduce((max, curr) =>
    curr.total_sales > max.total_sales ? curr : max,
    (props.data ?? [])[0]
) : null

const totalSales = props.data.reduce((sum, row) => sum + row.total_sales, 0)
const totalTransactions = props.data.reduce((sum, row) => sum + row.transaction_count, 0)

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
                    <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">Analytics · Hourly Sales</span>
                    <div>
                        <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">{{ props.title }}</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">Identify peak hours and sales patterns throughout the day.</p>
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
                        <CardTitle class="text-sm font-medium">Total Sales</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ "₱" + new Intl.NumberFormat("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}).format(totalSales) }}</div>
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
                        <div v-if="peakHour" class="space-y-1">
                            <div class="text-2xl font-bold">{{ peakHour.hour_label }}</div>
                            <p class="text-xs text-muted-foreground mt-1">{{ "₱" + new Intl.NumberFormat("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}).format(peakHour.total_sales) }}</p>
                        </div>
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
                        :valueFormatter="currencyFormatter" />
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
                                <tr class="border-b border-black/8 dark:border-white/10">
                                    <th class="px-4 py-3 text-left text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Hour</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Transactions</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Total Sales</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Avg Order</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in props.data" :key="row.hour" class="border-b border-black/6 transition-colors hover:bg-black/[0.025] dark:border-white/8 dark:hover:bg-white/[0.03]"
                                     :class="{ 'bg-woosoo-accent/8 dark:bg-woosoo-accent/6': peakHour && row.hour === peakHour.hour }">
                                    <td class="py-3 px-4 font-medium">{{ row.hour_label }}</td>
                                    <td class="text-right py-3 px-4">{{ row.transaction_count }}</td>
                                    <td class="text-right py-3 px-4">{{ "₱" + new Intl.NumberFormat("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}).format(row.total_sales) }}</td>
                                    <td class="text-right py-3 px-4">{{ "₱" + new Intl.NumberFormat("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}).format(row.avg_order_value) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

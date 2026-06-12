<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { LineChart } from '@/components/ui/chart-line'
import { ref } from 'vue'
import type { BreadcrumbItem } from '@/types'

interface DiscountTaxData {
    date: string
    order_count: number
    total_discount: number
    avg_discount: number
    total_tax: number
    avg_tax: number
    total_sales: number
    discount_percentage: number
    tax_percentage: number
}

interface PageProps {
    title: string
    data: DiscountTaxData[]
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
        'Discount': row.total_discount,
        'Tax': row.total_tax,
        'Sales': row.total_sales,
    }))
)

const totalDiscount = (props.data ?? []).reduce((sum, row) => sum + row.total_discount, 0)
const totalTax = (props.data ?? []).reduce((sum, row) => sum + row.total_tax, 0)
const totalSales = (props.data ?? []).reduce((sum, row) => sum + row.total_sales, 0)
const totalOrders = (props.data ?? []).reduce((sum, row) => sum + row.order_count, 0)
const avgDiscount = totalOrders > 0 ? totalDiscount / totalOrders : 0
const avgTax = totalOrders > 0 ? totalTax / totalOrders : 0

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
                    <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">Analytics · Discount & Tax</span>
                    <div>
                        <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">{{ props.title }}</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">Track discount usage and tax collection across all orders.</p>
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
                        <CardTitle class="text-sm font-medium">Total Discount</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ "₱" + new Intl.NumberFormat("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}).format(totalDiscount) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">{{ (totalSales > 0 ? ((totalDiscount / totalSales) * 100) : 0).toFixed(2)
                            }}% of sales</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Total Tax</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ "₱" + new Intl.NumberFormat("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}).format(totalTax) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">{{ (totalSales > 0 ? ((totalTax / totalSales) * 100) : 0).toFixed(2) }}%
                            of sales</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Avg Discount/Order</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ "₱" + new Intl.NumberFormat("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}).format(avgDiscount) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Per order</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Avg Tax/Order</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ "₱" + new Intl.NumberFormat("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}).format(avgTax) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Per order</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Chart -->
            <Card>
                <CardHeader>
                    <CardTitle>Discount & Tax Trends</CardTitle>
                    <CardDescription>Daily discount and tax collection over time</CardDescription>
                </CardHeader>
                <CardContent>
                    <LineChart :data="chartData" index="date" :categories="['Discount', 'Tax', 'Sales']"
                        :colors="['#f59e0b', '#10b981', '#3b82f6']"
                        :valueFormatter="currencyFormatter" />
                </CardContent>
            </Card>

            <!-- Data Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Daily Discount & Tax Breakdown</CardTitle>
                    <CardDescription>Detailed daily analysis</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-black/8 dark:border-white/10">
                                    <th class="px-4 py-3 text-left text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Date</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Orders</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Sales</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Discount</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Discount %</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Tax</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Tax %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in props.data" :key="row.date" class="border-b border-black/6 transition-colors hover:bg-black/[0.025] dark:border-white/8 dark:hover:bg-white/[0.03]">
                                    <td class="py-3 px-4 font-medium">{{ row.date }}</td>
                                    <td class="text-right py-3 px-4">{{ row.order_count }}</td>
                                    <td class="text-right py-3 px-4">{{ "₱" + new Intl.NumberFormat("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}).format(row.total_sales) }}</td>
                                    <td class="text-right py-3 px-4">{{ "₱" + new Intl.NumberFormat("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}).format(row.total_discount) }}</td>
                                    <td class="text-right py-3 px-4">{{ row.discount_percentage.toFixed(2) }}%</td>
                                    <td class="text-right py-3 px-4">{{ "₱" + new Intl.NumberFormat("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}).format(row.total_tax) }}</td>
                                    <td class="text-right py-3 px-4">{{ row.tax_percentage.toFixed(2) }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Summary Stats -->
            <Card>
                <CardHeader>
                    <CardTitle>Period Summary</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div class="rounded-[18px] border border-black/8 bg-white/60 px-4 py-3 dark:border-white/10 dark:bg-white/[0.04]">
                            <div class="text-xs font-semibold uppercase tracking-[0.15em] text-muted-foreground">Total Orders</div>
                            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ totalOrders }}</div>
                        </div>
                        <div class="rounded-[18px] border border-black/8 bg-white/60 px-4 py-3 dark:border-white/10 dark:bg-white/[0.04]">
                            <div class="text-xs font-semibold uppercase tracking-[0.15em] text-muted-foreground">Total Sales</div>
                            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ "₱" + new Intl.NumberFormat("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}).format(totalSales) }}</div>
                        </div>
                        <div class="rounded-[18px] border border-black/8 bg-white/60 px-4 py-3 dark:border-white/10 dark:bg-white/[0.04]">
                            <div class="text-xs font-semibold uppercase tracking-[0.15em] text-muted-foreground">Discount Expense</div>
                            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ "₱" + new Intl.NumberFormat("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}).format(totalDiscount) }}</div>
                        </div>
                        <div class="rounded-[18px] border border-black/8 bg-white/60 px-4 py-3 dark:border-white/10 dark:bg-white/[0.04]">
                            <div class="text-xs font-semibold uppercase tracking-[0.15em] text-muted-foreground">Tax Collected</div>
                            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ "₱" + new Intl.NumberFormat("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}).format(totalTax) }}</div>
                        </div>
                        <div class="rounded-[18px] border border-black/8 bg-white/60 px-4 py-3 dark:border-white/10 dark:bg-white/[0.04]">
                            <div class="text-xs font-semibold uppercase tracking-[0.15em] text-muted-foreground">Avg Discount %</div>
                            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ (props.data.length > 0
                                ? (props.data.reduce((sum, r) => sum + r.discount_percentage, 0) / props.data.length)
                                : 0).toFixed(2) }}%</div>
                        </div>
                        <div class="rounded-[18px] border border-black/8 bg-white/60 px-4 py-3 dark:border-white/10 dark:bg-white/[0.04]">
                            <div class="text-xs font-semibold uppercase tracking-[0.15em] text-muted-foreground">Avg Tax %</div>
                            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ (props.data.length > 0
                                ? (props.data.reduce((sum, r) => sum + r.tax_percentage, 0) / props.data.length)
                                : 0).toFixed(2) }}%</div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

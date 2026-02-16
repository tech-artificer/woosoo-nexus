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
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Reports', href: '#' },
    { label: props.title, href: '#' },
]

const chartData = ref(
    props.data.map(row => ({
        date: row.date,
        'Discount': row.total_discount,
        'Tax': row.total_tax,
        'Sales': row.total_sales,
    }))
)

const totalDiscount = props.data.reduce((sum, row) => sum + row.total_discount, 0)
const totalTax = props.data.reduce((sum, row) => sum + row.total_tax, 0)
const totalSales = props.data.reduce((sum, row) => sum + row.total_sales, 0)
const totalOrders = props.data.reduce((sum, row) => sum + row.order_count, 0)
const avgDiscount = totalOrders > 0 ? totalDiscount / totalOrders : 0
const avgTax = totalOrders > 0 ? totalTax / totalOrders : 0
</script>

<template>

    <Head :title="props.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">{{ props.title }}</h1>
                    <p class="text-sm text-muted-foreground mt-1">Track discount usage and tax collection</p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Total Discount</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">${{ totalDiscount.toFixed(2) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">{{ ((totalDiscount / totalSales) * 100).toFixed(2)
                            }}% of sales</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Total Tax</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">${{ totalTax.toFixed(2) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">{{ ((totalTax / totalSales) * 100).toFixed(2) }}%
                            of sales</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Avg Discount/Order</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">${{ avgDiscount.toFixed(2) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Per order</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Avg Tax/Order</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">${{ avgTax.toFixed(2) }}</div>
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
                        :valueFormatter="(value: number) => typeof value === 'number' ? `$${value.toFixed(0)}` : value" />
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
                                <tr class="border-b">
                                    <th class="text-left py-3 px-4 font-semibold">Date</th>
                                    <th class="text-right py-3 px-4 font-semibold">Orders</th>
                                    <th class="text-right py-3 px-4 font-semibold">Sales</th>
                                    <th class="text-right py-3 px-4 font-semibold">Discount</th>
                                    <th class="text-right py-3 px-4 font-semibold">Discount %</th>
                                    <th class="text-right py-3 px-4 font-semibold">Tax</th>
                                    <th class="text-right py-3 px-4 font-semibold">Tax %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in props.data" :key="row.date" class="border-b hover:bg-muted/50">
                                    <td class="py-3 px-4 font-medium">{{ row.date }}</td>
                                    <td class="text-right py-3 px-4">{{ row.order_count }}</td>
                                    <td class="text-right py-3 px-4">${{ row.total_sales.toFixed(2) }}</td>
                                    <td class="text-right py-3 px-4">${{ row.total_discount.toFixed(2) }}</td>
                                    <td class="text-right py-3 px-4">{{ row.discount_percentage.toFixed(2) }}%</td>
                                    <td class="text-right py-3 px-4">${{ row.total_tax.toFixed(2) }}</td>
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
                        <div>
                            <div class="text-sm font-medium text-muted-foreground">Total Orders</div>
                            <div class="text-2xl font-bold">{{ totalOrders }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-muted-foreground">Total Sales</div>
                            <div class="text-2xl font-bold">${{ totalSales.toFixed(2) }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-muted-foreground">Discount Expense</div>
                            <div class="text-2xl font-bold">${{ totalDiscount.toFixed(2) }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-muted-foreground">Tax Collected</div>
                            <div class="text-2xl font-bold">${{ totalTax.toFixed(2) }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-muted-foreground">Avg Discount %</div>
                            <div class="text-2xl font-bold">{{(props.data.reduce((sum, r) => sum +
                                r.discount_percentage, 0) / props.data.length).toFixed(2) }}%</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-muted-foreground">Avg Tax %</div>
                            <div class="text-2xl font-bold">{{(props.data.reduce((sum, r) => sum + r.tax_percentage, 0)
                                / props.data.length).toFixed(2) }}%</div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

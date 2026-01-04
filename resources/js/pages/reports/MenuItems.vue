<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { BarChart } from '@/components/ui/chart-bar'
import { ref } from 'vue'
import type { BreadcrumbItem } from '@/types'

interface MenuItemData {
    menu_id: number
    menu_name: string
    quantity_sold: number
    total_revenue: number
    avg_price: number
    package_count: number
    is_package_best_seller: boolean
}

interface PageProps {
    title: string
    data: MenuItemData[]
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
    props.data.slice(0, 15).map(row => ({
        name: row.menu_name,
        'Quantity': row.quantity_sold,
        'Revenue': row.total_revenue,
    }))
)

const topItems = props.data.slice(0, 5)
const packageBestSellers = props.data.filter(item => item.package_count > 0).slice(0, 10)
const totalRevenue = props.data.reduce((sum, row) => sum + row.total_revenue, 0)
const totalQuantity = props.data.reduce((sum, row) => sum + row.quantity_sold, 0)
</script>

<template>

    <Head :title="props.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">{{ props.title }}</h1>
                    <p class="text-sm text-muted-foreground mt-1">Menu item sales performance and package best sellers
                    </p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Total Menu Items</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ props.data.length }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Unique items sold</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Total Revenue</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">${{ totalRevenue.toFixed(2) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">From menu sales</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Total Quantity</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalQuantity }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Items sold</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Package Bestsellers</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ packageBestSellers.length }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Items as packages</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Chart -->
            <Card>
                <CardHeader>
                    <CardTitle>Top 15 Menu Items</CardTitle>
                    <CardDescription>By quantity sold and revenue</CardDescription>
                </CardHeader>
                <CardContent>
                    <BarChart :data="chartData" index="name" :categories="['Quantity', 'Revenue']"
                        :colors="['#3b82f6', '#10b981']" layout="vertical"
                        :valueFormatter="(value: number) => typeof value === 'number' ? value.toFixed(0) : value" />
                </CardContent>
            </Card>

            <!-- Top Sellers -->
            <Card>
                <CardHeader>
                    <CardTitle>Top 5 Revenue Generators</CardTitle>
                    <CardDescription>Highest revenue menu items</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-for="(item, idx) in topItems" :key="item.menu_id"
                            class="flex items-center justify-between p-3 border rounded-lg">
                            <div>
                                <div class="font-semibold">{{ idx + 1 }}. {{ item.menu_name }}</div>
                                <div class="text-sm text-muted-foreground">{{ item.quantity_sold }} sold • Avg: ${{
                                    item.avg_price.toFixed(2) }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-bold">${{ item.total_revenue.toFixed(2) }}</div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Package Best Sellers -->
            <Card>
                <CardHeader>
                    <CardTitle>Package Best Sellers</CardTitle>
                    <CardDescription>Items ordered as packages (first item in order)</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-3 px-4 font-semibold">Menu Item</th>
                                    <th class="text-right py-3 px-4 font-semibold">Package Count</th>
                                    <th class="text-right py-3 px-4 font-semibold">Total Qty</th>
                                    <th class="text-right py-3 px-4 font-semibold">Revenue</th>
                                    <th class="text-right py-3 px-4 font-semibold">Avg Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in packageBestSellers" :key="item.menu_id"
                                    class="border-b hover:bg-muted/50">
                                    <td class="py-3 px-4 font-medium">{{ item.menu_name }}</td>
                                    <td class="text-right py-3 px-4">
                                        <Badge variant="default">{{ item.package_count }}</Badge>
                                    </td>
                                    <td class="text-right py-3 px-4">{{ item.quantity_sold }}</td>
                                    <td class="text-right py-3 px-4">${{ item.total_revenue.toFixed(2) }}</td>
                                    <td class="text-right py-3 px-4">${{ item.avg_price.toFixed(2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- All Items -->
            <Card>
                <CardHeader>
                    <CardTitle>All Menu Items</CardTitle>
                    <CardDescription>Complete menu sales breakdown</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-3 px-4 font-semibold">Menu Item</th>
                                    <th class="text-right py-3 px-4 font-semibold">Quantity</th>
                                    <th class="text-right py-3 px-4 font-semibold">Avg Price</th>
                                    <th class="text-right py-3 px-4 font-semibold">Revenue</th>
                                    <th class="text-center py-3 px-4 font-semibold">Is Package</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in props.data" :key="item.menu_id" class="border-b hover:bg-muted/50">
                                    <td class="py-3 px-4">{{ item.menu_name }}</td>
                                    <td class="text-right py-3 px-4">{{ item.quantity_sold }}</td>
                                    <td class="text-right py-3 px-4">${{ item.avg_price.toFixed(2) }}</td>
                                    <td class="text-right py-3 px-4">${{ item.total_revenue.toFixed(2) }}</td>
                                    <td class="text-center py-3 px-4">
                                        <Badge v-if="item.is_package_best_seller" variant="secondary">Yes</Badge>
                                        <span v-else class="text-muted-foreground">–</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

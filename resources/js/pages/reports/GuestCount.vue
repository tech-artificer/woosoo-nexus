<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { LineChart } from '@/components/ui/chart-line'
import { ref } from 'vue'
import type { BreadcrumbItem } from '@/types'

interface GuestCountData {
    date: string
    total_guests: number
    order_count: number
    avg_guests_per_order: number
}

interface PageProps {
    title: string
    data: GuestCountData[]
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
        'Total Guests': row.total_guests,
        'Orders': row.order_count,
    }))
)

const totalGuests = props.data.reduce((sum, row) => sum + row.total_guests, 0)
const totalOrders = props.data.reduce((sum, row) => sum + row.order_count, 0)
const avgGuestsPerDay = totalGuests / props.data.length
const maxGuestDay = props.data.reduce((max, curr) =>
    curr.total_guests > max.total_guests ? curr : max,
    props.data[0]
)
</script>

<template>

    <Head :title="props.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">{{ props.title }}</h1>
                    <p class="text-sm text-muted-foreground mt-1">Track guest volume and dining trends</p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Total Guests</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalGuests }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Period total</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Avg Guests/Day</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ avgGuestsPerDay.toFixed(0) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Daily average</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Busiest Day</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ maxGuestDay.total_guests }}</div>
                        <p class="text-xs text-muted-foreground mt-1">{{ maxGuestDay.date }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Avg Guests/Order</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ (totalGuests / totalOrders).toFixed(2) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Party size</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Chart -->
            <Card>
                <CardHeader>
                    <CardTitle>Guest Volume Trend</CardTitle>
                    <CardDescription>Daily guest count and order volume</CardDescription>
                </CardHeader>
                <CardContent>
                    <LineChart :data="chartData" index="date" :categories="['Total Guests', 'Orders']"
                        :colors="['#8b5cf6', '#3b82f6']"
                        :valueFormatter="(value: number) => typeof value === 'number' ? value.toFixed(0) : value" />
                </CardContent>
            </Card>

            <!-- Data Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Daily Guest Breakdown</CardTitle>
                    <CardDescription>Guest count details by day</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-3 px-4 font-semibold">Date</th>
                                    <th class="text-right py-3 px-4 font-semibold">Total Guests</th>
                                    <th class="text-right py-3 px-4 font-semibold">Orders</th>
                                    <th class="text-right py-3 px-4 font-semibold">Avg Guests/Order</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in props.data" :key="row.date" class="border-b hover:bg-muted/50">
                                    <td class="py-3 px-4">{{ row.date }}</td>
                                    <td class="text-right py-3 px-4">{{ row.total_guests }}</td>
                                    <td class="text-right py-3 px-4">{{ row.order_count }}</td>
                                    <td class="text-right py-3 px-4">{{ row.avg_guests_per_order.toFixed(2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

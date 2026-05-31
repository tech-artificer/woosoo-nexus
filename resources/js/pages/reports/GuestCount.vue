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
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: route('reports.index') },
    { title: props.title, href: '#' },
]

const chartData = ref(
    props.data.map(row => ({
        date: row.date,
        'Total Guests': row.total_guests,
        'Orders': row.order_count,
    }))
)

const totalGuests = (props.data ?? []).reduce((sum, row) => sum + row.total_guests, 0)
const totalOrders = (props.data ?? []).reduce((sum, row) => sum + row.order_count, 0)
const avgGuestsPerDay = (props.data ?? []).length > 0 ? totalGuests / (props.data ?? []).length : 0
const maxGuestDay = (props.data ?? []).length ? (props.data ?? []).reduce((max, curr) =>
    curr.total_guests > max.total_guests ? curr : max,
    (props.data ?? [])[0]
) : null

const numberFormatter = (value: unknown) => {
    return typeof value === 'number' ? value.toFixed(0) : String(value)
}
</script>

<template>

    <Head :title="props.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5">
            <!-- Hero -->
            <div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
                <div class="relative space-y-3">
                    <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">Analytics · Guest Count</span>
                    <div>
                        <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">{{ props.title }}</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">Track guest volume and dining trends.</p>
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
                        <div v-if="maxGuestDay">
                            <div class="text-2xl font-bold">{{ maxGuestDay.total_guests }}</div>
                            <p class="text-xs text-muted-foreground mt-1">{{ maxGuestDay.date }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Avg Guests/Order</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalOrders > 0 ? (totalGuests / totalOrders).toFixed(2) : '0.00' }}</div>
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
                        :valueFormatter="numberFormatter" />
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
                                <tr class="border-b border-black/8 dark:border-white/10">
                                    <th class="px-4 py-3 text-left text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Date</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Total Guests</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Orders</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Avg Guests/Order</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in props.data" :key="row.date" class="border-b border-black/6 transition-colors hover:bg-black/[0.025] dark:border-white/8 dark:hover:bg-white/[0.03]">
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

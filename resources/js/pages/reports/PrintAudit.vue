<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import type { BreadcrumbItem } from '@/types'

interface PrintAuditData {
    id: number
    order_number: string
    printed_by: string | null
    printed_at: string
    status: string
    total: number
    branch_id: number
    device_id: number
}

interface PageProps {
    title: string
    data: PrintAuditData[]
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

const totalPrints = props.data.length
const totalPrintedValue = props.data.reduce((sum, row) => sum + row.total, 0)
</script>

<template>

    <Head :title="props.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">{{ props.title }}</h1>
                    <p class="text-sm text-muted-foreground mt-1">Kitchen ticket print history and audit trail</p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Total Prints</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalPrints }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Kitchen tickets</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Total Value</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">${{ totalPrintedValue.toFixed(2) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Printed orders</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Avg Order Value</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">${{ totalPrints > 0 ? (totalPrintedValue /
                            totalPrints).toFixed(2) : '0.00' }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Per print job</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Period</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ props.meta.total }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Total records</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Print Jobs Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Print Job Log</CardTitle>
                    <CardDescription>Complete kitchen ticket history</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-3 px-4 font-semibold">Order #</th>
                                    <th class="text-left py-3 px-4 font-semibold">Printed By</th>
                                    <th class="text-left py-3 px-4 font-semibold">Printed At</th>
                                    <th class="text-center py-3 px-4 font-semibold">Status</th>
                                    <th class="text-right py-3 px-4 font-semibold">Total</th>
                                    <th class="text-center py-3 px-4 font-semibold">Device</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in props.data" :key="row.id" class="border-b hover:bg-muted/50">
                                    <td class="py-3 px-4 font-medium">#{{ row.order_number }}</td>
                                    <td class="py-3 px-4">{{ row.printed_by || '–' }}</td>
                                    <td class="py-3 px-4 text-xs">{{ new Date(row.printed_at).toLocaleString() }}</td>
                                    <td class="text-center py-3 px-4">
                                        <Badge v-if="row.status === 'COMPLETED'" variant="default">{{ row.status }}
                                        </Badge>
                                        <Badge v-else-if="row.status === 'CONFIRMED'" variant="secondary">{{ row.status
                                            }}</Badge>
                                        <Badge v-else variant="outline">{{ row.status }}</Badge>
                                    </td>
                                    <td class="text-right py-3 px-4">${{ row.total.toFixed(2) }}</td>
                                    <td class="text-center py-3 px-4 text-xs">{{ row.device_id }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="totalPrints === 0" class="text-center py-8 text-muted-foreground">
                            No print jobs found for the selected period.
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

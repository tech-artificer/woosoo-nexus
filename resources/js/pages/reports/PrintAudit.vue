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
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: route('reports.index') },
    { title: props.title, href: '#' },
]

const totalPrints = props.data.length
const totalPrintedValue = props.data.reduce((sum, row) => sum + row.total, 0)

const getStatusColor = (status: string): 'success' | 'warning' | 'destructive' | 'secondary' | 'outline' => {
    const s = status.toUpperCase()
    if (s === 'COMPLETED') return 'success'
    if (s === 'CONFIRMED' || s === 'SERVED' || s === 'READY') return 'secondary'
    if (s === 'VOIDED' || s === 'CANCELLED') return 'destructive'
    if (s === 'PENDING' || s === 'IN_PROGRESS') return 'warning'
    return 'outline'
}
</script>

<template>

    <Head :title="props.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5">
            <!-- Hero -->
            <div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
                <div class="relative space-y-3">
                    <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                        Analytics · Print Audit
                    </span>
                    <div>
                        <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">{{ props.title }}</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">Kitchen ticket print history and audit trail.</p>
                    </div>
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
                        <div class="text-2xl font-bold">{{ "₱" + new Intl.NumberFormat("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}).format(totalPrintedValue) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Printed orders</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Avg Order Value</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">₱{{ totalPrints > 0 ? new Intl.NumberFormat('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}).format(totalPrintedValue / totalPrints) : '0.00' }}</div>
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
            <Card class="overflow-hidden rounded-[26px] border border-black/8 bg-card/92 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
                <CardHeader>
                    <CardTitle>Print Job Log</CardTitle>
                    <CardDescription>Complete kitchen ticket history</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-black/8 dark:border-white/10">
                                    <th class="px-4 py-3 text-left text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Order #</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Printed By</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Printed At</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Total</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Device</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in props.data" :key="row.id" class="border-b border-black/6 transition-colors hover:bg-black/[0.025] dark:border-white/8 dark:hover:bg-white/[0.03]">
                                    <td class="py-3 px-4 font-medium">#{{ row.order_number }}</td>
                                    <td class="py-3 px-4">{{ row.printed_by || '-' }}</td>
                                    <td class="py-3 px-4 text-xs">{{ new Date(row.printed_at).toLocaleString() }}</td>
                                    <td class="text-center py-3 px-4">
                                        <Badge :variant="getStatusColor(row.status)">{{ row.status }}</Badge>
                                    </td>
                                    <td class="text-right py-3 px-4">{{ "₱" + new Intl.NumberFormat("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}).format(row.total) }}</td>
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

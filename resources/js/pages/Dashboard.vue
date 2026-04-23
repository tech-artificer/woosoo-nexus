<script setup lang="ts">
import { onMounted, computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { 
    type LucideIcon, 
    ChartSpline,
    Contact,
    ArrowUp10,
    ChartPie,
} from 'lucide-vue-next';

import LineChart from '@/components/charts/LineChart.vue';
import DonutChart from '@/components/charts/DonutChart.vue';


interface DashCards {
  title?: string;
  value?: string | number;
  icon?: LucideIcon;
  helpText?: string;
}

const props = defineProps<{
    title?: string
    description?: string
    tableOrders: any
    openOrders: any,
    sessionId: number | null,
    totalSales: string | number,
    guestCount: string | number,
    totalOrders: string | number,
    monthlySales: string | number,
    salesData?: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

function parseCurrency(value: string | number | undefined | null): number {
    if (value === null || value === undefined) return 0
    if (typeof value === 'number') return isNaN(value) ? 0 : value
    return Number(String(value).replace(/,/g, '')) || 0
}

const dashCards = computed<DashCards[]>(() => [
    {
        title: 'Total Sales Today',
        value: new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(parseCurrency(props.totalSales)),
        icon: ChartSpline,
        helpText: `${props.totalOrders ?? 0} Transactions`,
    },
    {
        title: `Today's Orders`,
        value: props.totalOrders ?? 0,
        icon: ArrowUp10,
        helpText: 'Completed orders today',
    },
    {
        title: `Total Guests`,
        value: props.guestCount ?? 0,
        icon: Contact,
        helpText: 'Guests served today',
    },
    {
        title: `Monthly Sales`,
        value: new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(parseCurrency(props.monthlySales)),
        icon: ChartPie,
        helpText: new Date().toLocaleString('default', { month: 'long', year: 'numeric' }),
    },
])




onMounted(() => {
  
});


</script>

<template>

    <Head :title="props.title" :description="props.description" />

    <AppLayout :breadcrumbs="breadcrumbs">

        <div class="space-y-6">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.45fr)_minmax(280px,0.8fr)]">
                <div class="relative overflow-hidden rounded-[28px] border border-black/8 bg-[linear-gradient(135deg,rgba(246,181,109,0.22),rgba(255,255,255,0.88)_42%,rgba(176,128,71,0.12))] px-5 py-6 shadow-[0_28px_70px_-40px_rgba(176,128,71,0.45)] dark:border-white/12 dark:bg-[linear-gradient(135deg,rgba(246,181,109,0.22),rgba(255,255,255,0.09)_45%,rgba(176,128,71,0.14))] md:px-6">
                    <div class="pointer-events-none absolute -right-14 top-0 h-40 w-40 rounded-full bg-white/35 blur-3xl dark:bg-[#f6b56d]/12"></div>
                    <div class="relative space-y-3">
                        <span class="inline-flex rounded-full border border-black/8 bg-white/60 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground dark:border-white/10 dark:bg-white/[0.05]">Live operations</span>
                        <div>
                            <h1 class="font-header text-3xl font-semibold tracking-tight text-foreground md:text-[2.2rem]">Dashboard overview</h1>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground md:text-base">Keep service flow visible, monitor sales performance, and watch the floor from one calmer control surface.</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-[24px] border border-black/8 bg-white/72 px-4 py-4 shadow-[0_22px_55px_-42px_rgba(37,37,37,0.4)] dark:border-white/12 dark:bg-white/[0.08]">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">Session</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight">{{ props.sessionId ? '#' + props.sessionId : '\u2014' }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">Current operational window</p>
                    </div>
                    <div class="rounded-[24px] border border-black/8 bg-white/72 px-4 py-4 shadow-[0_22px_55px_-42px_rgba(37,37,37,0.4)] dark:border-white/12 dark:bg-white/[0.08]">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">Open tables</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight">{{ props.openOrders?.length ?? 0 }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">Tables with active ordering activity</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card v-for="dashCard in dashCards" :key="dashCard.title" class="gap-4 border-black/8 px-1 transition-transform duration-200 hover:-translate-y-1 hover:shadow-[0_30px_70px_-46px_rgba(37,37,37,0.45)] dark:border-white/10">
                    <CardHeader class="flex flex-row items-start justify-between p-5 pb-1">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                        {{ dashCard.title }}
                        </CardTitle>
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#f6b56d]/16 text-[#8f6436] dark:bg-[#f6b56d]/12 dark:text-[#f6b56d]">
                            <component :is="dashCard.icon" class="size-5 shrink-0" />
                        </div>
                    </CardHeader>
                    <CardContent class="p-5 pt-0">
                        <div class="text-3xl font-semibold tabular-nums tracking-tight">
                        {{ dashCard.value }}
                        </div>
                        <p class="mt-2 text-sm text-muted-foreground">
                        {{ dashCard.helpText }}
                        </p>
                    </CardContent>
                </Card>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card class="border-black/8 dark:border-white/10">
                    <CardHeader class="p-5 pb-1">
                        <CardTitle class="text-lg font-semibold">Sales Trend</CardTitle>
                        <p class="text-sm text-muted-foreground">Revenue movement across the current reporting window.</p>
                    </CardHeader>
                    <CardContent class="p-5 pt-0">
                        <LineChart />
                    </CardContent>
                </Card>
                <Card class="border-black/8 dark:border-white/10">
                    <CardHeader class="p-5 pb-1">
                        <CardTitle class="text-lg font-semibold">Distribution</CardTitle>
                        <p class="text-sm text-muted-foreground">Quick split of activity across the active data set.</p>
                    </CardHeader>
                    <CardContent class="flex items-center justify-center p-5 pt-0">
                        <DonutChart />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

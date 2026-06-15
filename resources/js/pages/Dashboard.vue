<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { type LucideIcon, ArrowUp10, ChartPie, ChartSpline, Contact } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

import DonutChart from '@/components/charts/DonutChart.vue';
import LineChart from '@/components/charts/LineChart.vue';
import { Button } from '@/components/ui/button';

type StatsRange = 'today' | 'week' | 'month';

const statsRange = ref<StatsRange>('today');
const liveStats = ref<Record<string, any> | null>(null);
const statsLoading = ref(false);

const rangeLabels: Record<StatsRange, string> = {
    today: 'Today',
    week: 'This week',
    month: 'This month',
};

let latestStatsRequest = 0;

async function fetchLiveStats() {
    const requestId = ++latestStatsRequest;
    statsLoading.value = true;
    try {
        const res = await fetch(`${route('dashboard.stats')}?range=${statsRange.value}`);
        if (res.ok) {
            const data = await res.json();
            // Ignore out-of-order responses from a superseded range selection.
            if (requestId === latestStatsRequest) {
                liveStats.value = data;
            }
        }
    } catch {
        // Keep server-rendered props as fallback
    } finally {
        if (requestId === latestStatsRequest) {
            statsLoading.value = false;
        }
    }
}

watch(statsRange, () => {
    fetchLiveStats();
});

interface DashCards {
    title?: string;
    value?: string | number;
    icon?: LucideIcon;
    helpText?: string;
}

const props = defineProps<{
    title?: string;
    description?: string;
    tableOrders: any;
    openOrders: any;
    sessionId: number | null;
    totalSales: string | number;
    guestCount: string | number;
    totalOrders: string | number;
    monthlySales: string | number;
    salesData?: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

function parseCurrency(value: string | number | undefined | null): number {
    if (value === null || value === undefined) return 0;
    if (typeof value === 'number') return isNaN(value) ? 0 : value;
    return Number(String(value).replace(/,/g, '')) || 0;
}

const dashCards = computed<DashCards[]>(() => {
    const rangeLabel = rangeLabels[statsRange.value];
    const sales = liveStats.value?.total_sales ?? props.totalSales;
    const orders = liveStats.value?.total_orders ?? props.totalOrders;
    const guests = liveStats.value?.guest_count ?? props.guestCount;
    const monthly = liveStats.value?.today_revenue ?? props.monthlySales;

    return [
    {
        title: `Total Sales (${rangeLabel})`,
        value: typeof sales === 'string'
            ? sales
            : new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(parseCurrency(sales)),
        icon: ChartSpline,
        helpText: `${orders ?? 0} Transactions`,
    },
    {
        title: `Orders (${rangeLabel})`,
        value: orders ?? 0,
        icon: ArrowUp10,
        helpText: 'Completed orders in range',
    },
    {
        title: `Guests (${rangeLabel})`,
        value: guests ?? 0,
        icon: Contact,
        helpText: 'Guests served in range',
    },
    {
        title: `Revenue (${rangeLabel})`,
        value: typeof monthly === 'string'
            ? monthly
            : new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(parseCurrency(monthly)),
        icon: ChartPie,
        helpText: rangeLabel,
    },
];
});

onMounted(() => {
    fetchLiveStats();
});
</script>

<template>
    <Head :title="props.title" :description="props.description" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.45fr)_minmax(280px,0.8fr)]">
                <div
                    class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm md:px-6 dark:border-white/10"
                >
                    <div class="relative space-y-3">
                        <span
                            class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase"
                            >Live operations</span
                        >
                        <div>
                            <h1 class="font-header text-3xl font-semibold tracking-tight text-foreground md:text-[2.2rem]">Dashboard overview</h1>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground md:text-base">
                                Keep service flow visible, monitor sales performance, and watch the floor from one calmer control surface.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    <div
                        class="rounded-[22px] border border-black/8 bg-white/72 px-4 py-4 shadow-[0_22px_55px_-42px_rgba(37,37,37,0.36)] dark:border-white/10 dark:bg-white/[0.06]"
                    >
                        <p class="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">Session</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight">{{ props.sessionId ? '#' + props.sessionId : '\u2014' }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">Current operational window</p>
                    </div>
                    <div
                        class="rounded-[22px] border border-black/8 bg-white/72 px-4 py-4 shadow-[0_22px_55px_-42px_rgba(37,37,37,0.36)] dark:border-white/10 dark:bg-white/[0.06]"
                    >
                        <p class="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">Open tables</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight">{{ props.openOrders?.length ?? 0 }}</p>
                        <p v-if="props.openOrders?.length" class="mt-1 text-sm text-muted-foreground">Tables with active ordering activity</p>
                        <p v-else class="mt-1 text-sm text-muted-foreground">No open orders detected</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="text-sm text-muted-foreground">Reporting window</span>
                <div class="flex gap-2">
                    <Button
                        v-for="(label, key) in rangeLabels"
                        :key="key"
                        size="sm"
                        :variant="statsRange === key ? 'default' : 'outline'"
                        :disabled="statsLoading"
                        @click="statsRange = key as StatsRange"
                    >
                        {{ label }}
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card
                    v-for="dashCard in dashCards"
                    :key="dashCard.title"
                    class="gap-4 px-1 transition-transform duration-200 hover:-translate-y-1 hover:shadow-[0_30px_70px_-46px_rgba(37,37,37,0.42)]"
                >
                    <CardHeader class="flex flex-row items-start justify-between p-5 pb-1">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            {{ dashCard.title }}
                        </CardTitle>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-accent/12 text-primary dark:text-accent">
                            <component :is="dashCard.icon" class="size-5 shrink-0" />
                        </div>
                    </CardHeader>
                    <CardContent class="p-5 pt-0">
                        <div class="text-3xl font-semibold tracking-tight tabular-nums">
                            {{ dashCard.value }}
                        </div>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ dashCard.helpText }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader class="p-5 pb-1">
                        <CardTitle class="text-lg font-semibold">Sales Trend</CardTitle>
                        <p class="text-sm text-muted-foreground">Revenue movement across the current reporting window.</p>
                    </CardHeader>
                    <CardContent class="p-5 pt-0">
                        <LineChart />
                    </CardContent>
                </Card>
                <Card>
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

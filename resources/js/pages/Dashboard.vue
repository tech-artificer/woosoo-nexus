<script setup lang="ts">
import { onMounted } from 'vue'
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
    sessionId: number,
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

const dashCards: DashCards[] = [
    {
        title: 'Total Sales Today',
        value: new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(props.totalSales)),
        icon: ChartSpline,
        helpText: `${props.totalOrders} Transactions`,
    },
    {
        title: `Today's Orders`,
        value: props.totalOrders,
        icon: ArrowUp10,
        helpText: 'Completed orders today',
    },
    {
        title: `Total Guests`,
        value: props.guestCount,
        icon: Contact,
        helpText: 'Guests served today',
    },
    {
        title: `Monthly Sales`,
        value: '₱' + props.monthlySales,
        icon: ChartPie,
        helpText: new Date().toLocaleString('default', { month: 'long', year: 'numeric' }),
    },
];




onMounted(() => {
  
});


</script>

<template>

    <Head :title="props.title" :description="props.description" />

    <AppLayout :breadcrumbs="breadcrumbs">

        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Overview</h1>
                <p class="text-muted-foreground">Welcome to the main dashboard</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card v-for="dashCard in dashCards" :key="dashCard.title" class="border-0 transition-shadow hover:shadow-md">
                    <CardHeader class="flex flex-row items-center justify-between p-4 pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                        {{ dashCard.title }}
                        </CardTitle>
                        <component :is="dashCard.icon" class="size-5 text-woosoo-green shrink-0" />   
                    </CardHeader>
                    <CardContent class="p-4 pt-0">
                        <div class="text-2xl font-bold tabular-nums tracking-tight">
                        {{ dashCard.value }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                        {{ dashCard.helpText }}
                        </p>
                    </CardContent>
                </Card>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card class="border-0">
                    <CardHeader class="p-4 pb-2">
                        <CardTitle class="text-sm font-medium">Sales Trend</CardTitle>
                    </CardHeader>
                    <CardContent class="p-4 pt-0">
                        <LineChart />
                    </CardContent>
                </Card>
                <Card class="border-0">
                    <CardHeader class="p-4 pb-2">
                        <CardTitle class="text-sm font-medium">Distribution</CardTitle>
                    </CardHeader>
                    <CardContent class="p-4 pt-0 flex justify-center items-center">
                        <DonutChart />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

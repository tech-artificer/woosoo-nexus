<script setup lang="ts">
import { onMounted, defineProps } from 'vue'
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

// import Overview from '@/pages/dashboard/components/Overview.vue';
// import TopItems from '@/pages/dashboard/components/TopItems.vue';
// import TopSales from '@/pages/dashboard/components/TopSales.vue';

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
    monthlySales: string | number
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
        value: '₱' + props.totalSales,
        icon: ChartSpline,
        helpText: '50 Transactions',
    },
    {
        title: `Today's Orders`,
        value: props.totalOrders,
        icon: ArrowUp10,
        helpText: 'Total Orders',
    },
    {
        title: `Total Guests`,
        value: props.guestCount,
        icon: Contact,
        helpText: 'Total Orders',
    },
    {
        title: `Monthly Sales`,
        value: props.monthlySales,
        icon: ChartPie,
        helpText: 'Sales for the month',
    },
];




onMounted(() => {
  
});


</script>

<template>

    <Head :title="props.title" :description="props.description" />

    <AppLayout :breadcrumbs="breadcrumbs">

        <div class="flex h-full flex-1 flex-col gap-4 rounded p-6">
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-bold font-header text-woosoo-dark-gray">Overview</h1>
                <p class=" font-body font-light text-woosoo-dark-gray">Welcome to the main dashboard</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card v-for="dashCard in dashCards" :key="dashCard.title" class="border-0">
                    <CardHeader class="flex flex-row items-center justify-between p-4 pb-2">
                        <CardTitle class="text-sm font-medium">
                        {{ dashCard.title }}
                        </CardTitle>
                        <component :is="dashCard.icon" class="text-woosoo-green " />   
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                        {{ dashCard.value }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                        {{ dashCard.helpText }}
                        </p>
                    </CardContent>
                </Card>
            </div>
            
             <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <LineChart />
                </div>
                  <div class="flex justify-center items-center">
                    <DonutChart />
                </div>
            </div>
    
        </div>
    </AppLayout>
</template>

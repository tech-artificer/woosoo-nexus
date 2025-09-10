<script setup lang="ts">
import { onMounted, defineProps } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'
// import PlaceholderPattern from '../components/PlaceholderPattern.vue'
import { Card, CardContent } from '@/components/ui/card';
// import { Badge } from '@/components/ui/badge';
import { 
    type LucideIcon, 
    ChartSpline,
    Contact,
    ArrowUp10,
    ChartPie,
} from 'lucide-vue-next';

// import Overview from '@/pages/dashboard/components/Overview.vue';
import TopItems from '@/pages/dashboard/components/TopItems.vue';
import TopSales from '@/pages/dashboard/components/TopSales.vue';


// const page = usePage();
// const session = page.props.session as { id?: any } || {};
// const terminalSession = page.props.terminalSession as { id?: any } || {};
// const cashTraySession = page.props.cashTraySession as { id?: any } || {};
// const employeeLogs = page.props.employeeLog as { id?: any } || {};
// const flag = page.props.sessionFlag as boolean || false;

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
        title: `Void/Cancelled Orders`,
        value: props.monthlySales,
        icon: ChartPie,
        helpText: 'Sales for the month',
    },
];

// const activeSessions: ActiveSession[] = [
//     {
//         id: session?.id,
//         title: 'Session #',
//         icon: LockOpen,
//         is_active: flag == true
//     },
//     {
//         id: terminalSession?.id,
//         title: 'Terminal Session #',
//         icon: Terminal,
//         is_active: flag == true
//     },
//     {
//         id: employeeLogs?.id,
//         title: 'Log #',
//         icon: Fingerprint,
//         is_active: flag == true
//     },
//     {
//         id: cashTraySession?.id,
//         title: 'Cash Tray Session #',
//         icon: Fingerprint,
//         is_active: flag == true
//     },

// ];

// const services = ref([
//     { key: 'reverb', name: 'Reverb', description: 'Broadcast server', status: 'checking', loading: false },
//     // { key: 'deviceCodes', name: 'Device Codes', description: 'Generates device codes', status: 'checking', loading: false },
//     // { key: 'paymentTrigger', name: 'Payment Trigger', description: 'Order payment logs', status: 'checking', loading: false },
//     { key: 'scheduler', name: 'Scheduled Jobs', description: 'Background tasks', status: 'checking', loading: false }
// ]);

// const fetchStatuses = async () => {
//   const { data } = await axios.get('/api/service-status');
//   services.value.forEach(service => {
//     service.status = data[service.key] || 'unknown';
//   });
// };

// const runService = async (serviceKey: string) => {
//     const service = services.value.find(s => s.key === serviceKey);
//     if (!service) return;
//     service.loading = true;
//     try {
//         await axios.post(`/api/run-service`, { service: serviceKey });
//         await fetchStatuses();
//     } catch (e) {
//         console.error(e);
//     } finally {
//         service.loading = false;
//     }
// };

onMounted(() => {
    //   fetchStatuses();
    //   setInterval(fetchStatuses, 5000); // Poll every 5 seconds
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
                <Card class="w-full" v-for="dashCard in dashCards" :key="dashCard.title">
                    <CardContent class="flex  items-start justify-between">
                        <div class="flex flex-col gap-2">
                            <p class="text-sm font-body font-light text-gray-700">{{ dashCard.title }}</p>
                            <h1 class="text-2xl font-semibold font-body text-woosoo-dark-gray">{{ dashCard.value }}</h1>
                            <p class="text-xs text-muted-foreground"> {{ dashCard.helpText }} </p>
                        </div>
                        <!-- <div class="flex flex-col items-center gap-2"> -->
                            <component :is="dashCard.icon" class="text-woosoo-green align-top" />
                            <!-- <Badge v-if="openOrders.length" class="bg-success text-woosoo-green">
                               {{ sessionId }}
                            </Badge> -->
                        <!-- </div> -->

                    </CardContent>
                </Card>
         
                
              
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <!-- <Card>
                    <CardContent class="w-full h-full">
                        <div class="text-lg font-semibold flex justify-center font-header text-woosoo-dark-gray mb-6" > Category Distributions </div>    
                        <Overview />
                    </CardContent>
                </Card> -->

                <Card class="w-full">
                    <CardContent>
                        <div class="text-lg font-semibold flex justify-center font-header text-woosoo-dark-gray mb-6" > Package Distributions </div>    
                        <TopItems />
                    </CardContent>
                </Card>

                <Card class="w-full">
                    <CardContent>
                        <div class="text-lg font-semibold flex justify-center font-header text-woosoo-dark-gray mb-6" > Top Packages </div>    
                        <TopSales />
                    </CardContent>
                </Card>

            </div>

        </div>
    </AppLayout>
</template>

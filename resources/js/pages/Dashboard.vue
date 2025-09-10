<script setup lang="ts">
import { onMounted, defineProps } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'
// import PlaceholderPattern from '../components/PlaceholderPattern.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
// import { Badge } from '@/components/ui/badge';
import Register from '@/pages/auth/Register.vue';
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
        title: `Monthly Sales`,
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

        
  

        </div>
    </AppLayout>
</template>

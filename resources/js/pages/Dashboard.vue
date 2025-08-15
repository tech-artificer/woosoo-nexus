<script setup lang="ts">
import { onMounted, defineProps } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'
// import PlaceholderPattern from '../components/PlaceholderPattern.vue'
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ListOrdered } from 'lucide-vue-next';


// const page = usePage();
// const session = page.props.session as { id?: any } || {};
// const terminalSession = page.props.terminalSession as { id?: any } || {};
// const cashTraySession = page.props.cashTraySession as { id?: any } || {};
// const employeeLogs = page.props.employeeLog as { id?: any } || {};
// const flag = page.props.sessionFlag as boolean || false;

// interface ActiveSession {
//   id?: any;
//   title?: string;
//   icon?: LucideIcon;
//   is_active?: boolean;
// }

const props = defineProps<{
    title?: string
    description?: string
    tableOrders: any
    openOrders: any,
    sessionId: number
}>()

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
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

        <div class="flex h-full flex-1 flex-col gap-4 rounded p-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card  class="w-full">
                    <CardContent class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-body text-woosoo-dark-gray">Active Orders</p>
                            <h1 class="text-3xl font-semibold font-body text-woosoo-blue">{{ openOrders.length }}</h1>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <component :is="ListOrdered"/>
                            <Badge v-if="openOrders.length" class="bg-success text-woosoo-green">
                               {{ sessionId }}
                            </Badge>
                        </div>
                      
                    </CardContent>
                </Card> 
               <!-- <pre> {{ tableOrders }} </pre> -->
                <!-- <Card v-for="activeSession in activeSessions" :key="activeSession.id" class="w-full">
                    <CardContent class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-body text-woosoo-dark-gray">{{ activeSession.title }}</p>
                            <h2 class="text-xl font-semibold font-body text-woosoo-blue">{{ activeSession.id }}</h2>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <component :is="activeSession.icon"/>
                            <Badge v-if="activeSession.is_active" class="bg-success text-woosoo-green">
                               Current
                            </Badge>
                             <Badge v-else class="bg-red text-woosoo-red">
                               Previous
                            </Badge>
                        </div>
                    </CardContent>
                </Card> -->
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                <!-- <Card v-for="service in services" :key="service.key" class="w-full">
                    <CardContent class="flex items-center justify-between">
                        <div>
                            <h3 class="text-l font-semibold font-header text-woosoo-dark-gray">{{ service.name }}</h3>
                            <p class="text-sm font-body text-muted-foreground">{{ service.description }}</p>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <Button :disabled="service.loading" @click="runService(service.key)">
                                <Loader2 v-if="service.loading" class="animate-spin mr-2" />
                                {{ service.loading ? 'Running...' : 'Run Now' }}
                            </Button>
                            <Badge class="bg-success text-woosoo-green"
                                :variant="service.status === 'running' ? 'success' : service.status === 'stopped' ? 'destructive' : 'destructive'">
                                {{ service.status }}
                            </Badge>
                        </div>
                    </CardContent>
                </Card> -->
            </div>
        </div>
    </AppLayout>
</template>

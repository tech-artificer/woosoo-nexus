<script setup lang="ts">
import { onMounted, defineProps, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, usePage } from '@inertiajs/vue3'
// import PlaceholderPattern from '../components/PlaceholderPattern.vue'
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Loader2, LockOpen, Terminal, Fingerprint, LucideIcon } from 'lucide-vue-next';
import axios from 'axios';


const page = usePage();
// const terminal = page.props.terminal as { id?: any } || {};
const session = page.props.session as { id?: any } || {};
const terminalSession = page.props.terminalSession as { id?: any } || {};
// const terminalSession = page.props.terminalSession as { id?: any } || {};
const cashTraySession = page.props.cashTraySession as { id?: any } || {};
const employeeLogs = page.props.employeeLog as { id?: any } || {};

interface ActiveSession {
  id?: any;
  title?: string;
  icon?: LucideIcon;
  is_active?: boolean;
}

const props = defineProps<{
    title?: string
    description?: string
}>()

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const activeSessions: ActiveSession[] = [
    // {
    //     title: 'Github Repo',
    //     href: 'https://github.com/laravel/vue-starter-kit',
    //     icon: Circle,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits#vue',
    //     icon: Circle,
    // },
    // {
    //     id: terminal?.id,
    //     title: 'Terminal #',
    //     icon: LockOpen,
    // },
    {
        id: session?.id,
        title: 'Session #',
        icon: LockOpen,
        is_active: true
    },
    {
        id: terminalSession?.id,
        title: 'Terminal Session #',
        icon: Terminal,
        is_active: true
    },
    {
        id: employeeLogs?.id,
        title: 'Log #',
        icon: Fingerprint,
        is_active: true
    },
    {
        id: cashTraySession?.id,
        title: 'Cash Tray Session #',
        icon: Fingerprint,
        is_active: false
    },
    
];

const services = ref([
    { key: 'reverb', name: 'Reverb', description: 'Broadcast server', status: 'checking', loading: false },
    { key: 'deviceCodes', name: 'Device Codes', description: 'Generates device codes', status: 'checking', loading: false },
    { key: 'paymentTrigger', name: 'Payment Trigger', description: 'Order payment logs', status: 'checking', loading: false },
    { key: 'scheduler', name: 'Scheduled Jobs', description: 'Background tasks', status: 'checking', loading: false }
]);

const fetchStatuses = async () => {
  const { data } = await axios.get('/api/service-status');
  services.value.forEach(service => {
    service.status = data[service.key] || 'unknown';
  });
};

const runService = async (serviceKey: string) => {
    const service = services.value.find(s => s.key === serviceKey);
    if (!service) return;
    service.loading = true;
    try {
        await axios.post(`/api/run-service`, { service: serviceKey });
        await fetchStatuses();
    } catch (e) {
        console.error(e);
    } finally {
        service.loading = false;
    }
};

onMounted(() => {
      fetchStatuses();
      setInterval(fetchStatuses, 5000); // Poll every 5 seconds
});


</script>

<template>

    <Head :title="props.title" :description="props.description" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
           
                <div class="relative p-4">
                    <h2 class="text-lg font-semibold">Service Workers</h2>
                    <ol class="flex flex-col gap-3 text-sm leading-normal mt-2 w-full">
                        <li>Start Reverb 
                            <code class="block">app:reverb-start</code>
                        </li>
                        <li>
                            Generate Device Codes 
                            <code class="block">devices:generate-codes</code>
                        </li>
                        <li>
                            Setup Payment Trigger 
                            <small class="block">Creates a log table and trigger to capture order updates</small>
                            <code class="block">pos:setup-payment-trigger</code>
                        </li>
                        <li>
                            Run Scheduled Jobs
                            <code class="block">schedule:work</code>
                        </li>
                    </ol>
                </div>
           
               <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      
                    </div>
                </div> 
            </div> -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
                <Card v-for="activeSession in activeSessions" :key="activeSession.id" class="w-full">
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
                </Card>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                <Card v-for="service in services" :key="service.key" class="w-full">
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
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

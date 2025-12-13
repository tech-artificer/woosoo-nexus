<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { columns } from '@/components/ServiceRequests/columns';
import DataTable from '@/components/ServiceRequests/DataTable.vue';
import StatsCards from '@/components/ServiceRequests/StatsCards.vue';
import DataTableToolbar from '@/components/ui/DataTableToolbar.vue';
import { ServiceRequest } from '@/types/models';
import { toast } from 'vue-sonner';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Service Requests',
        href: route('service-requests.index'),
    },
];

interface Props {
    title: string;
    description: string;
    serviceRequests: ServiceRequest[];
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    stats: {
        total_pending: number;
        total_active: number;
        total_today: number;
        avg_response_time: number;
    };
    tableServices: any[];
    filters: {
        status: string;
        priority: string;
        from_date?: string;
        to_date?: string;
        show_all: boolean;
    };
}

const props = defineProps<Props>();

const localServiceRequests = ref<ServiceRequest[]>([...props.serviceRequests]);
const localStats = ref({ ...props.stats });

const search = ref('')
const statusFilter = ref(props.filters?.status ?? '')
const priorityFilter = ref(props.filters?.priority ?? '')
const fromDate = ref(props.filters?.from_date ?? '')
const toDate = ref(props.filters?.to_date ?? '')
const showAll = ref(props.filters?.show_all ?? false)

import { computed } from 'vue'
const originalServiceRequests = computed(() => props.serviceRequests ?? [])
const filteredServiceRequests = computed(() => {
    return originalServiceRequests.value.filter((sr: any) => {
        if (search.value) {
            const q = search.value.toLowerCase()
            if (!((sr.table_name || '').toLowerCase().includes(q) || (sr.table_service_name || '').toLowerCase().includes(q))) return false
        }
        if (statusFilter.value && sr.status !== statusFilter.value) return false
        if (priorityFilter.value && sr.priority !== priorityFilter.value) return false
        if (fromDate.value) {
            const created = new Date(sr.created_at)
            if (created < new Date(fromDate.value)) return false
        }
        if (toDate.value) {
            const created = new Date(sr.created_at)
            const end = new Date(toDate.value)
            end.setHours(23,59,59,999)
            if (created > end) return false
        }
        if (!showAll.value && sr.is_archived) return false
        return true
    })
})

function submitSearch() { /* client-side */ }
function submitFilters() { /* reactive */ }
function resetFilters() { search.value=''; statusFilter.value=''; priorityFilter.value=''; fromDate.value=''; toDate.value=''; showAll.value=false }

// Real-time updates
onMounted(() => {
    if (!window.Echo) {
        console.warn('Echo not initialized');
        return;
    }

    console.log('ServiceRequests Index mounted. Joining admin.service-requests channel.');

    window.Echo.channel('admin.service-requests')
        .listen('.service-request.notification', (event: any) => {
            console.log('Service request event received:', event);
            const serviceRequest = event.service_request;

            // Update or add to list
            const index = localServiceRequests.value.findIndex(sr => sr.id === serviceRequest.id);

            if (index !== -1) {
                // Update existing
                localServiceRequests.value[index] = serviceRequest;
                toast.info(`Service request #${serviceRequest.id} updated`);
            } else {
                // Add new (prepend)
                localServiceRequests.value.unshift(serviceRequest);

                // Show notification for new requests
                if (serviceRequest.status === 'pending') {
                    toast.warning(`New service request from ${serviceRequest.table_name}`, {
                        description: serviceRequest.table_service_name,
                        duration: 5000,
                    });

                    // Play sound for urgent/high priority
                    if (serviceRequest.priority === 'urgent' || serviceRequest.priority === 'high') {
                        playNotificationSound();
                    }
                }
            }

            // Update stats
            updateStats();
        })
        .error((error: unknown) => {
            console.error('Error connecting to admin.service-requests channel:', error);
        });
});

onUnmounted(() => {
    if (window.Echo) {
        console.log('ServiceRequests Index unmounted. Leaving channels.');
        window.Echo.leave('admin.service-requests');
    }
});

const updateStats = () => {
    // Recalculate stats from local data
    localStats.value = {
        total_pending: localServiceRequests.value.filter(sr => sr.status === 'pending').length,
        total_active: localServiceRequests.value.filter(sr => sr.is_active).length,
        total_today: localServiceRequests.value.filter(sr => {
            const createdDate = new Date(sr.created_at);
            const today = new Date();
            return createdDate.toDateString() === today.toDateString();
        }).length,
        avg_response_time: props.stats.avg_response_time, // Keep server calculation
    };
};

const playNotificationSound = () => {
    const audio = new Audio('/sounds/notification.mp3');
    audio.volume = 0.5;
    audio.play().catch(err => console.log('Audio play failed:', err));
};

const refreshData = () => {
    router.reload({ only: ['serviceRequests', 'stats'] });
};
</script>

<template>
    <Head :title="props.title" :description="props.description" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Service Requests</h1>
                    <p class="text-muted-foreground">Manage and track customer service requests in real-time</p>
                </div>
            </div>

            <DataTableToolbar
                v-model:search="search"
                v-model:status="statusFilter"
                v-model:priority="priorityFilter"
                v-model:fromDate="fromDate"
                v-model:toDate="toDate"
                v-model:showAll="showAll"
            />

            <StatsCards :stats="localStats" />

            <DataTable 
                :data="filteredServiceRequests" 
                :columns="columns"
                :pagination="props.pagination"
                :filters="props.filters"
                :table-services="props.tableServices"
                @refresh="refreshData"
            />
        </div>
    </AppLayout>
</template>

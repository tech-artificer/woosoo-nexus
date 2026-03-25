<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { 
    Radio,
    CheckCircle,
    XCircle,
    AlertCircle,
} from 'lucide-vue-next'

interface ServiceInfo {
    name: string
    label: string
    status: 'running' | 'stopped' | 'paused' | 'not_installed' | 'unknown' | 'error' | 'N/A'
    message: string
}

const props = defineProps<{
    service: ServiceInfo
    isWindows: boolean
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reverb Service', href: '/reverb' },
]

const pollingInterval = ref<ReturnType<typeof setInterval> | null>(null)
const liveStatus = ref<ServiceInfo>(props.service)

const statusConfig = {
    running: { color: 'bg-green-500', icon: CheckCircle, label: 'Running' },
    stopped: { color: 'bg-red-500', icon: XCircle, label: 'Stopped' },
    paused: { color: 'bg-yellow-500', icon: AlertCircle, label: 'Paused' },
    not_installed: { color: 'bg-gray-500', icon: AlertCircle, label: 'Not Installed' },
    unknown: { color: 'bg-gray-400', icon: AlertCircle, label: 'Unknown' },
    error: { color: 'bg-red-600', icon: XCircle, label: 'Error' },
    'N/A': { color: 'bg-gray-400', icon: AlertCircle, label: 'N/A' },
}

const normalizeStatus = (status: string): ServiceInfo['status'] => {
    const normalized = status.trim().toLowerCase().replace(/\s+/g, '_')

    if (normalized.includes('service_running')) return 'running'
    if (normalized.includes('service_stopped')) return 'stopped'
    if (normalized.includes('service_paused')) return 'paused'
    if (normalized === 'running' || normalized === 'stopped' || normalized === 'paused' || normalized === 'not_installed' || normalized === 'unknown' || normalized === 'error' || normalized === 'n/a') {
        return normalized === 'n/a' ? 'N/A' : normalized as ServiceInfo['status']
    }

    return 'unknown'
}

const getStatusConfig = (status: string) => {
    return statusConfig[normalizeStatus(status) as keyof typeof statusConfig] || statusConfig.unknown
}

const fetchStatus = async () => {
    try {
        const response = await fetch('/reverb/status')
        const data = await response.json()
        liveStatus.value = {
            ...liveStatus.value,
            status: normalizeStatus(data.status),
            message: data.message,
        }
    } catch (e) {
        console.error('Failed to fetch status:', e)
    }
}

onMounted(() => {
    pollingInterval.value = setInterval(fetchStatus, 5000)
})

onUnmounted(() => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value)
    }
})
</script>

<template>
    <Head title="Reverb Service" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight flex items-center gap-2">
                    <Radio class="h-6 w-6" />
                    Reverb WebSocket Service
                </h1>
                <p class="text-muted-foreground">
                    WebSocket server status (super-admin access required)
                </p>
            </div>

            <!-- Status Card -->
            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle class="flex items-center justify-between">
                        <span>{{ liveStatus.label }}</span>
                        <Badge 
                            :class="[getStatusConfig(liveStatus.status).color, 'text-white']"
                        >
                            <component 
                                :is="getStatusConfig(liveStatus.status).icon" 
                                class="h-3 w-3 mr-1" 
                            />
                            {{ getStatusConfig(liveStatus.status).label }}
                        </Badge>
                    </CardTitle>
                    <CardDescription>
                        Service: <code class="bg-muted px-1 rounded">{{ liveStatus.name }}</code>
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">
                        {{ liveStatus.message }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

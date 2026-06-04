<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { 
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
    running: { color: 'bg-woosoo-green', icon: CheckCircle, label: 'Running' },
    stopped: { color: 'bg-destructive', icon: XCircle, label: 'Stopped' },
    paused: { color: 'bg-woosoo-accent text-woosoo-dark-gray', icon: AlertCircle, label: 'Paused' },
    not_installed: { color: 'bg-muted-foreground', icon: AlertCircle, label: 'Not Installed' },
    unknown: { color: 'bg-muted-foreground', icon: AlertCircle, label: 'Unknown' },
    error: { color: 'bg-destructive', icon: XCircle, label: 'Error' },
    'N/A': { color: 'bg-muted-foreground', icon: AlertCircle, label: 'N/A' },
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
        <div class="space-y-5">
            <div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
                <div class="relative space-y-3">
                    <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                        WebSocket service
                    </span>
                    <div>
                        <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">Reverb Service</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">Monitor the WebSocket service status used by live admin updates.</p>
                    </div>
                </div>
            </div>

            <!-- Status Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center justify-between">
                        <span>{{ liveStatus.label }}</span>
                        <Badge 
                            :class="[getStatusConfig(liveStatus.status).color, getStatusConfig(liveStatus.status).color.includes('text-') ? '' : 'text-white']"
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

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { 
    Radio,
    Copy,
    CheckCircle,
    XCircle,
    AlertCircle,
    Terminal,
} from 'lucide-vue-next'
import { useToast } from '@/composables/useToast'

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

const { toast } = useToast()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reverb Service', href: '/reverb' },
]

const pollingInterval = ref<ReturnType<typeof setInterval> | null>(null)
const liveStatus = ref<ServiceInfo>(props.service)

// Config-driven paths; fall back to generic commands
const nssmPath = import.meta.env.VITE_NSSM_PATH ?? 'nssm'
const serviceName = import.meta.env.VITE_REVERB_SERVICE_NAME ?? 'woosoo-reverb'
const phpPath = import.meta.env.VITE_PHP_BIN_PATH ?? 'php'
const projectPath = import.meta.env.VITE_PROJECT_PATH ?? '.'
const reverbPort = import.meta.env.VITE_REVERB_PORT ?? 6001
const reverbHost = import.meta.env.VITE_REVERB_HOST ?? '127.0.0.1'
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http'

const commands = {
    status: `${nssmPath} status ${serviceName}`,
    start: `${nssmPath} start ${serviceName}`,
    stop: `${nssmPath} stop ${serviceName}`,
    restart: `${nssmPath} restart ${serviceName}`,
    install: `${nssmPath} install ${serviceName} "${phpPath}" "${projectPath}\\artisan reverb:start" && ${nssmPath} set ${serviceName} AppDirectory "${projectPath}"`,
    remove: `${nssmPath} remove ${serviceName} confirm`,
}

const statusConfig = {
    running: { color: 'bg-green-500', icon: CheckCircle, label: 'Running' },
    stopped: { color: 'bg-red-500', icon: XCircle, label: 'Stopped' },
    paused: { color: 'bg-yellow-500', icon: AlertCircle, label: 'Paused' },
    not_installed: { color: 'bg-gray-500', icon: AlertCircle, label: 'Not Installed' },
    unknown: { color: 'bg-gray-400', icon: AlertCircle, label: 'Unknown' },
    error: { color: 'bg-red-600', icon: XCircle, label: 'Error' },
    'N/A': { color: 'bg-gray-400', icon: AlertCircle, label: 'N/A' },
}

const getStatusConfig = (status: string) => {
    return statusConfig[status as keyof typeof statusConfig] || statusConfig.unknown
}

const fetchStatus = async () => {
    try {
        const response = await fetch('/reverb/status')
        const data = await response.json()
        liveStatus.value = {
            ...liveStatus.value,
            status: data.status,
            message: data.message,
        }
    } catch (e) {
        console.error('Failed to fetch status:', e)
    }
}

const copyCommand = async (command: string, label: string) => {
    try {
        await navigator.clipboard.writeText(command)
        toast({
            title: 'Copied!',
            description: `${label} command copied to clipboard`,
        })
    } catch {
        // Fallback for older browsers
        const textArea = document.createElement('textarea')
        textArea.value = command
        document.body.appendChild(textArea)
        textArea.select()
        document.execCommand('copy')
        document.body.removeChild(textArea)
        toast({
            title: 'Copied!',
            description: `${label} command copied to clipboard`,
        })
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
                    Manage the Laravel Reverb WebSocket server via PowerShell commands
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

            <!-- Commands Card -->
            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Terminal class="h-5 w-5" />
                        PowerShell Commands
                    </CardTitle>
                    <CardDescription>
                        Run these commands in PowerShell <strong>as Administrator</strong>
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Start -->
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-green-600">Start Service</label>
                        <div class="flex gap-2">
                            <code class="flex-1 bg-muted p-2 rounded text-xs overflow-x-auto">{{ commands.start }}</code>
                            <Button size="sm" variant="outline" @click="copyCommand(commands.start, 'Start')">
                                <Copy class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Stop -->
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-red-600">Stop Service</label>
                        <div class="flex gap-2">
                            <code class="flex-1 bg-muted p-2 rounded text-xs overflow-x-auto">{{ commands.stop }}</code>
                            <Button size="sm" variant="outline" @click="copyCommand(commands.stop, 'Stop')">
                                <Copy class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Restart -->
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-blue-600">Restart Service</label>
                        <div class="flex gap-2">
                            <code class="flex-1 bg-muted p-2 rounded text-xs overflow-x-auto">{{ commands.restart }}</code>
                            <Button size="sm" variant="outline" @click="copyCommand(commands.restart, 'Restart')">
                                <Copy class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="space-y-1">
                        <label class="text-sm font-medium">Check Status</label>
                        <div class="flex gap-2">
                            <code class="flex-1 bg-muted p-2 rounded text-xs overflow-x-auto">{{ commands.status }}</code>
                            <Button size="sm" variant="outline" @click="copyCommand(commands.status, 'Status')">
                                <Copy class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <hr class="my-4" />

                    <!-- Install -->
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-purple-600">Install Service (first time)</label>
                        <div class="flex gap-2">
                            <code class="flex-1 bg-muted p-2 rounded text-xs overflow-x-auto break-all">{{ commands.install }}</code>
                            <Button size="sm" variant="outline" @click="copyCommand(commands.install, 'Install')">
                                <Copy class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Remove -->
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-orange-600">Remove Service</label>
                        <div class="flex gap-2">
                            <code class="flex-1 bg-muted p-2 rounded text-xs overflow-x-auto">{{ commands.remove }}</code>
                            <Button size="sm" variant="outline" @click="copyCommand(commands.remove, 'Remove')">
                                <Copy class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Info Card -->
            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>Service Information</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Port:</span>
                        <span>6001</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Protocol:</span>
                        <span>WebSocket</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">NSSM Path:</span>
                        <code class="bg-muted px-1 rounded text-xs">{{ nssmPath }}</code>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">PHP Path:</span>
                        <code class="bg-muted px-1 rounded text-xs">{{ phpPath }}</code>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

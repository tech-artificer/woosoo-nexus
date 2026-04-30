<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import type { Device } from '@/types/models';
import axios from 'axios';
import { Clock3, Landmark, Monitor, RefreshCw, Wifi } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface DeviceDetailSheetProps {
    open: boolean;
    device: Device | null;
}

const props = defineProps<DeviceDetailSheetProps>();
const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const regenerating = ref(false);
const localPlainCode = ref<string | null>(null);
const localPlainCodeDeviceId = ref<Device['id'] | null>(null);

const regenerateCode = async () => {
    if (!props.device) return;
    const deviceId = props.device.id;
    regenerating.value = true;
    try {
        const response = await axios.post(
            route('devices.security-code.regenerate', deviceId),
            {},
            {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            },
        );
        const code = response?.data?.security_code;
        if (!code) {
            toast.error('Failed to regenerate code');
            return;
        }
        if (props.device?.id !== deviceId) return;
        localPlainCode.value = String(code);
        localPlainCodeDeviceId.value = deviceId;
        await navigator.clipboard.writeText(String(code)).catch(() => {});
        toast.success('Security code regenerated and copied to clipboard');
    } catch (e: any) {
        toast.error(e?.response?.data?.message ?? 'Failed to regenerate code');
    } finally {
        regenerating.value = false;
    }
};

watch(
    () => props.device?.id,
    () => {
        localPlainCode.value = null;
        localPlainCodeDeviceId.value = null;
        regenerating.value = false;
    },
);

const openState = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

const rawDevice = computed(() => (props.device ?? null) as any);
const displayCode = computed(() => {
    if (localPlainCode.value && localPlainCodeDeviceId.value === props.device?.id) {
        return localPlainCode.value;
    }

    return null;
});

const safeText = (value: any, fallback = '-') => ((value ?? value === 0) ? String(value) : fallback);

const statusVariant = computed(() => {
    if (!props.device) return 'secondary';
    if (props.device.deleted_at) return 'destructive';
    if (props.device.is_active) return 'success';
    return 'secondary';
});
</script>

<template>
    <Sheet v-model:open="openState">
        <SheetContent side="right" class="w-full sm:max-w-3xl">
            <SheetHeader>
                <SheetTitle class="text-xl">{{ safeText(device?.name, 'Device') }}</SheetTitle>
                <SheetDescription> Device details and assignment overview </SheetDescription>
            </SheetHeader>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm text-muted-foreground">Device Status</CardTitle>
                        <CardDescription class="flex items-center gap-2">
                            <Badge :variant="statusVariant">{{
                                device?.deleted_at ? 'deactivated' : device?.is_active ? 'active' : 'inactive'
                            }}</Badge>
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Name</span>
                            <span class="font-medium">{{ safeText(device?.name) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Serial</span>
                            <span class="font-medium">{{ safeText(rawDevice?.serial_no) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Type</span>
                            <span class="font-medium">{{ safeText(rawDevice?.type) }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm text-muted-foreground">Network</CardTitle>
                        <CardDescription>Current and recent connectivity</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-muted-foreground"><Wifi class="size-4" /> IP</span>
                            <span class="font-medium">{{ safeText(device?.ip_address) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-muted-foreground"><Monitor class="size-4" /> Port</span>
                            <span class="font-medium">{{ safeText(device?.port) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Last IP</span>
                            <span class="font-medium">{{ safeText(rawDevice?.last_ip_address) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-muted-foreground"><Clock3 class="size-4" /> Last Seen</span>
                            <span class="font-medium">{{ safeText(rawDevice?.last_seen_at) }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card class="md:col-span-2">
                    <CardHeader>
                        <CardTitle class="text-sm text-muted-foreground">Assignment & Security</CardTitle>
                        <CardDescription>Branch mapping and security-code readiness</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-3 text-sm md:grid-cols-2">
                        <div class="rounded-md border p-3">
                            <div class="mb-2 flex items-center gap-2 text-muted-foreground"><Landmark class="size-4" /> Branch</div>
                            <div class="font-medium">{{ safeText(device?.branch?.name) }}</div>
                        </div>
                        <div class="rounded-md border p-3">
                            <div class="mb-2 text-muted-foreground">Table</div>
                            <div class="font-medium">{{ safeText(device?.table?.name) }}</div>
                        </div>
                        <Separator class="md:col-span-2" />
                        <div class="rounded-md border p-3 md:col-span-2">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-muted-foreground">Security Status</span>
                                <Button variant="outline" size="sm" :disabled="regenerating" @click="regenerateCode">
                                    <RefreshCw class="mr-1.5 size-3.5" :class="{ 'animate-spin': regenerating }" />
                                    {{ regenerating ? 'Regenerating…' : 'Regenerate Code' }}
                                </Button>
                            </div>
                            <template v-if="displayCode">
                                <div class="font-mono text-2xl font-bold tracking-widest">{{ displayCode }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    Awaiting registration · Generated: {{ safeText(rawDevice?.security_code_generated_at, 'Unknown') }}
                                </div>
                            </template>
                            <template v-else-if="rawDevice?.security_code_generated_at">
                                <div class="font-semibold">Awaiting registration</div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    Code hidden for security — regenerate to issue a new code · Generated:
                                    {{ safeText(rawDevice?.security_code_generated_at, 'Unknown') }}
                                </div>
                            </template>
                            <template v-else-if="device?.is_active">
                                <div class="font-semibold">Registered</div>
                                <div class="mt-1 text-xs text-muted-foreground">Device has connected and consumed the registration code</div>
                            </template>
                            <template v-else>
                                <div class="font-semibold text-muted-foreground">Not Set</div>
                            </template>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SheetContent>
    </Sheet>
</template>

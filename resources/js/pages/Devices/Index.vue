<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3'
import { type BreadcrumbItem } from '@/types';

import { columns } from '@/components/Devices/columns';
import DataTable from '@/components/Devices/DataTable.vue'
import DeviceDetailSheet from '@/components/Devices/DeviceDetailSheet.vue'
import StatsCards from '@/components/Stats/StatsCards.vue'
import { ref, computed, toRefs } from 'vue'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import type { Device } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Devices',
        href: route('devices.index'),
    },
];

const props = defineProps<{
    title: string;
    description: string;
    devices: Device[];
    stats?: any;
}>()

const { devices, stats } = toRefs(props)

const selectedDevice = ref<Device | null>(null)
const isDeviceDetailOpen = ref(false)
const page = usePage()

const revealedSecurityCode = computed(() => String((page.props as any)?.flash?.security_code_reveal || ''))
const showSecurityCodeReveal = ref(Boolean(revealedSecurityCode.value))
const originalDevices = computed(() => devices.value ?? [])
const securityReadyCount = computed(() =>
    originalDevices.value.filter((device: any) => Boolean(device.security_code_generated_at)).length
)

const openDeviceDetail = (device: Device) => {
    selectedDevice.value = device
    isDeviceDetailOpen.value = true
}

</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 px-1 sm:px-2">
            <div class="pt-3 px-1 sm:px-2 space-y-4">
                <StatsCards
                    :cards="(stats ?? [
                        { title: 'Total Devices', value: (devices || []).length, subtitle: 'Registered devices', variant: 'primary' },
                        { title: 'Security Ready', value: securityReadyCount, subtitle: 'Devices with security code', variant: 'accent' },
                    ])"
                />
                <DataTable :data="devices" :columns="columns" @row-click="openDeviceDetail" />
            </div>

            <DeviceDetailSheet
                v-model:open="isDeviceDetailOpen"
                :device="selectedDevice"
            />

            <Dialog v-model:open="showSecurityCodeReveal">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Security Code Created</DialogTitle>
                        <DialogDescription>
                            This code is shown once. Save it now for tablet registration.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="rounded-md bg-muted p-4 text-center font-mono text-xl tracking-widest">
                        {{ revealedSecurityCode }}
                    </div>

                    <DialogFooter>
                        <Button type="button" @click="showSecurityCodeReveal = false">Close</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
 
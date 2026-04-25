<script setup lang="ts">
 
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
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
        <div class="mx-auto flex w-full max-w-[1600px] flex-col gap-8 px-4 pb-8 pt-6 sm:px-6 lg:px-8 lg:pt-8">
            <section class="rounded-[28px] border border-border/60 bg-card/95 shadow-sm shadow-black/5 backdrop-blur-sm dark:bg-card/80">
                <div class="flex flex-col gap-6 p-5 sm:p-6 lg:p-8">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-2xl space-y-2">
                            <h2 class="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                                Devices
                            </h2>
                            <p class="max-w-xl text-sm leading-6 text-muted-foreground sm:text-base">
                                Register a new device, review the devices that are already online, and rotate security codes when a tablet needs to be re-issued.
                            </p>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <Button as-child size="lg" class="w-full sm:w-auto">
                                <Link :href="route('devices.create')">
                                    Create Device
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <StatsCards
                        :cards="(stats ?? [
                            { title: 'Total Devices', value: (devices || []).length, subtitle: 'Registered devices', variant: 'primary' },
                            { title: 'Security Ready', value: securityReadyCount, subtitle: 'Devices with security code', variant: 'accent' },
                        ])"
                    />
                </div>
            </section>

            <section class="rounded-[28px] border border-border/60 bg-card/95 shadow-sm shadow-black/5 backdrop-blur-sm dark:bg-card/80">
                <div class="p-4 sm:p-6 lg:p-8">
                    <DataTable
                        :data="devices"
                        :columns="columns"
                        :empty-action-href="route('devices.create')"
                        empty-action-label="Create Device"
                        @row-click="openDeviceDetail"
                    />
                </div>
            </section>

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
 
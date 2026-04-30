<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { SheetClose, SheetFooter } from '@/components/ui/sheet';
import type { Device, Table } from '@/types/models';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    device: Device;
    unassignedTables: Table[];
    formType?: 'create' | 'edit';
    inSheet?: boolean;
}>();

const form = useForm({
    name: props.device?.name ?? '',
    ip_address: props.device?.ip_address ?? '',
    port: props.device?.port ?? undefined,
    table_id: props.device?.table_id ?? undefined,
    type: (props.device as any)?.type ?? null,
    security_code: '',
});

const computedTables = computed(() => {
    const byId = new Map<number, Table>();

    if (props.device?.table) {
        byId.set(Number(props.device.table.id), props.device.table);
    }

    props.unassignedTables.forEach((table) => {
        byId.set(Number(table.id), table);
    });

    return Array.from(byId.values());
});

const branchContextError = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;
    return errors.branch;
});

const selectedTableName = computed(() => {
    const selectedTableId = form.table_id;
    const selectedTable = computedTables.value.find((t) => String(t.id) === String(selectedTableId));

    return selectedTable?.name ?? props.device?.table?.name ?? '';
});

function handleTableChange(value: unknown) {
    if (!value) {
        form.table_id = undefined;
        return;
    }

    form.table_id = value as number;
}

const submit = () => {
    if (props.formType === 'create') {
        form.post(route('devices.store'), {
            preserveScroll: true,
            onSuccess: () => {
                toast('Device Created:', {
                    description: 'A new device has been created.',
                    duration: 5000,
                    position: 'top-right',
                });
                form.reset();
            },
        });
        return;
    }

    form.put(route('devices.update', props.device?.id), {
        preserveScroll: true,
        onSuccess: (response: any) => {
            console.log(response);
            toast('Device Updated:', {
                description: 'Device information have been updated.',
                action: {
                    label: 'Ok',
                    variant: 'success',
                },
                duration: 5000,
                position: 'top-right',
            });
        },
    });
};

const isEdit = computed(() => props.formType === 'edit');
const showTokenDialog = ref(false);
const generatedToken = ref('');

async function createToken() {
    try {
        const url = route('devices.create.token', props.device?.id);
        const res = await axios.post(url, {}, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (res?.data?.success && res?.data?.token) {
            const token = res.data.token;
            // copy to clipboard then prompt the admin
            await navigator.clipboard.writeText(token).catch(() => {});
            // Show success via toast + token in dialog for easy copying
            toast('Device token copied to clipboard', { description: 'Token issued and ready to paste into the device app.' });
            generatedToken.value = token;
            showTokenDialog.value = true;
        } else {
            toast('Failed to create device token', { description: res?.data?.message ?? 'Unknown error' });
        }
    } catch (err: any) {
        console.error('Token creation failed', err);
        toast('Failed to create token', { description: err?.response?.data?.message ?? err?.message ?? 'Unknown' });
    }
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <Separator class="my-0" />
        <!-- Branch context error — server-resolved, shown when install has no unambiguous branch -->
        <div v-if="branchContextError" class="mx-4 mt-3 rounded-md border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
            {{ branchContextError }}
        </div>
        <form class="flex flex-col gap-4 p-4">
            <div class="flex flex-col gap-3">
                <Label for="name">Name</Label>
                <Input id="name" type="text" v-model="form.name" placeholder="John Doe" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="flex flex-col gap-3">
                <Label for="ip_address">IP Address</Label>
                <Input id="ip_address" type="text" inputmode="numeric" v-model="form.ip_address" placeholder="127.0.0.1" />
                <InputError :message="form.errors.ip_address" />
            </div>

            <div class="flex flex-col gap-3">
                <Label for="port">Port</Label>
                <Input id="port" type="number" min="1" max="65535" v-model="form.port" placeholder="3000" />
                <p class="text-xs text-muted-foreground">
                    Optional. Leave blank unless this device listens on a custom app port (valid range: 1-65535).
                </p>
                <InputError :message="form.errors.port" />
            </div>

            <div class="flex flex-col gap-3">
                <Label for="type">Device Type</Label>
                <Select :model-value="form.type ?? ''" @update:model-value="(v: unknown) => ((form as any).type = v || null)">
                    <SelectTrigger id="type" class="w-full">
                        <SelectValue placeholder="Select type…" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="tablet">Tablet</SelectItem>
                        <SelectItem value="printer_relay">Print Bridge</SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">Optional — helps filter devices by role.</p>
                <InputError :message="(form.errors as any).type" />
            </div>

            <div v-if="!isEdit" class="flex flex-col gap-3">
                <Label for="security_code">Security Code</Label>
                <Input id="security_code" type="text" value="Auto-generated on save" readonly />
                <p class="text-xs text-muted-foreground">
                    A unique 6-digit security code will be generated automatically when you create the device.
                </p>
                <InputError :message="form.errors.security_code" />
            </div>

            <div class="flex flex-col gap-3">
                <Label for="assigned_table">Assigned Table</Label>
                <Input id="assigned_table" type="text" :value="selectedTableName" class="w-25" readonly />
                <InputError :message="form.errors.table_id" />
            </div>

            <div class="flex flex-col">
                <Label for="table_id" class="mb-1">Change Table Assignment</Label>

                <Select :model-value="form.table_id" @update:model-value="handleTableChange">
                    <SelectTrigger id="table_id" class="w-45">
                        <SelectValue placeholder="Assign a Table" />
                    </SelectTrigger>
                    <SelectContent>
                        <!-- <SelectGroup> -->
                        <SelectLabel>Tables</SelectLabel>
                        <SelectItem v-for="table in computedTables" :key="table.id" :value="table.id">
                            {{ table.name }}
                        </SelectItem>
                        <!-- </SelectGroup> -->
                    </SelectContent>
                </Select>

                <div v-if="form.errors.table_id" class="mt-1 text-sm text-red-500">{{ form.errors.table_id }}</div>
            </div>
        </form>
    </div>
    <div class="border-t bg-muted/30">
        <div class="flex flex-row items-start gap-2 p-4">
            <SheetFooter v-if="inSheet">
                <SheetClose as-child>
                    <Button type="button" variant="destructive" class="cursor-pointer"> Cancel </Button>
                </SheetClose>
            </SheetFooter>
            <Button v-else type="button" variant="destructive" class="cursor-pointer" @click="router.get(route('devices.index'))"> Cancel </Button>
            <Button v-if="isEdit" type="button" variant="default" @click.prevent="createToken" class="text-sm"> Generate Token </Button>
            <Button
                type="button"
                @click.prevent="submit"
                variant="outline"
                class="cursor-pointer text-woosoo-primary-dark hover:bg-woosoo-primary-light hover:text-woosoo-primary-dark"
                :disabled="form.processing"
            >
                Save Changes
            </Button>
        </div>
    </div>

    <Dialog v-model:open="showTokenDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Device Token Generated</DialogTitle>
                <DialogDescription> This token has been copied to your clipboard. Store it securely. </DialogDescription>
            </DialogHeader>
            <div class="rounded-md bg-muted p-4 font-mono text-sm break-all">
                {{ generatedToken }}
            </div>
            <DialogFooter>
                <Button @click="showTokenDialog = false">Close</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

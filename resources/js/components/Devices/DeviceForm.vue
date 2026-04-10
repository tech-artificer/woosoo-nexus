<script setup lang="ts">
/* eslint-disable vue/valid-v-for */
import { computed } from 'vue'
import axios from 'axios'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from "@/components/ui/input"
import InputError from '@/components/InputError.vue';
import { toast } from 'vue-sonner'
import { Separator } from '@/components/ui/separator';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select"
import {
    SheetFooter,
    SheetClose
} from '@/components/ui/sheet'
import type { Device, Table } from '@/types/models'

const props = defineProps<{
    device: Device
    unassignedTables: Table[]
    formType?: 'create' | 'edit'
}>()

const form = useForm({
    name: props.device?.name ?? '',
    ip_address: props.device?.ip_address ?? '',
    port: props.device?.port ?? undefined,
    table_id: props.device?.table_id ?? undefined
})

const computedTables = computed(() => props.unassignedTables) // unassigned tables

const selectedTableName = computed(() => {
    // Always show the device's currently assigned table name
    // The table_id will be present in unassignedTables when in edit mode
    if (!form.table_id && !props.device.table) {
        return ''
    }

    // If form has a table_id, look it up in computedTables
    if (form.table_id) {
        const found = computedTables.value.find(t => String(t.id) === String(form.table_id))
        if (found) {
            return found.name
        }
    }

    // Fall back to the device's loaded table relationship
    const fallbackName = props.device.table?.name ?? ''
    return fallbackName
})

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
            }
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

}

const isEdit = computed(() => props.formType === 'edit')
async function createToken() {
    try {
        const url = route('devices.create.token', props.device?.id)
        const res = await axios.post(url, {}, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        if (res?.data?.success && res?.data?.token) {
            const token = res.data.token
            // copy to clipboard then prompt the admin
            await navigator.clipboard.writeText(token).catch(() => { })
            // Show success via toast + token in alert for easy copying
            toast('Device token copied to clipboard', { description: 'Token issued and ready to paste into the device app.' })
            alert('Device token:\n' + token + '\n\nThis token was also copied to your clipboard.');
        } else {
            toast('Failed to create device token', { description: res?.data?.message ?? 'Unknown error' })
        }
    } catch (err: any) {
        console.error('Token creation failed', err)
        toast('Failed to create token', { description: err?.response?.data?.message ?? err?.message ?? 'Unknown' })
    }
}
</script>

<template>
    <div class="flex flex-col">
        <Separator class="my-0" />
        <form class="px-4 sm:px-6 py-6 flex flex-col gap-6">
            <div class="space-y-2">
                <Label for="name">Name <span class="text-destructive">*</span></Label>
                <Input id="name" type="text" v-model="form.name" placeholder="Print Bridge 01" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="space-y-2">
                <Label for="ip_address">IP Address <span class="text-destructive">*</span></Label>
                <Input id="ip_address" type="text" v-model="form.ip_address" placeholder="192.168.1.100" />
                <InputError :message="form.errors.ip_address" />
            </div>

            <div class="space-y-2">
                <Label for="port">Port</Label>
                <Input id="port" type="text" v-model="form.port" placeholder="3000" />
                <InputError :message="form.errors.port" />
            </div>


            <div class="space-y-2">
                <Label for="assigned_table">Assigned Table</Label>
                <Input id="assigned_table" type="text" :model-value="selectedTableName" readonly
                    :key="`table-${form.table_id}`" class="bg-muted" />
                <InputError :message="form.errors.table_id" />
            </div>

            <div class="space-y-2">
                <Label for="table_assignment">Change Table Assignment</Label>
                <Select v-model="form.table_id">
                    <SelectTrigger id="table_assignment">
                        <SelectValue placeholder="Assign a Table" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectLabel>Tables</SelectLabel>
                        <SelectItem v-for="table in computedTables" :key="table.id" :value="table.id">
                            {{ table.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.table_id" />
            </div>




        </form>
        <SheetFooter class="px-4 sm:px-6 py-4 border-t bg-muted/30">
            <div class="flex items-center gap-3 w-full">
                <SheetClose as-child>
                    <Button type="button" variant="outline">
                        Cancel
                    </Button>
                </SheetClose>
                <div class="flex-1"></div>
                <Button v-if="isEdit" type="button" variant="secondary" @click.prevent="createToken">
                    Generate Token
                </Button>
                <Button type="button" @click.prevent="submit" :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : (formType === 'create' ? 'Create Device' : 'Save Changes') }}
                </Button>
            </div>
        </SheetFooter>
    </div>
</template>

<script setup lang="ts">
/* eslint-disable vue/valid-v-for */
import { computed, onMounted, reactive, ref, watch } from 'vue'
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
    SelectGroup,
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

// Debug logging
console.log('DeviceForm Props:', {
    device: props.device,
    table_id: props.device?.table_id,
    table: props.device?.table,
    unassignedTables: props.unassignedTables
})

const computedTables = computed(() => props.unassignedTables) // unassigned tables

const selectedTableName = computed(() => {
    console.log('Computing selectedTableName:', {
        formTableId: form.table_id,
        deviceTable: props.device.table,
        computedTablesCount: computedTables.value?.length
    })

    // Always show the device's currently assigned table name
    // The table_id will be present in unassignedTables when in edit mode
    if (!form.table_id && !props.device.table) {
        return ''
    }

    // If form has a table_id, look it up in computedTables
    if (form.table_id) {
        const found = computedTables.value.find(t => String(t.id) === String(form.table_id))
        if (found) {
            console.log('Found table in computedTables:', found.name)
            return found.name
        }
    }

    // Fall back to the device's loaded table relationship
    const fallbackName = props.device.table?.name ?? ''
    console.log('Using fallback name:', fallbackName)
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

console.log(selectedTableName)
</script>

<template>
    <div class="flex flex-col gap-3">
        <Separator class="my-0" />
        <form class="p-4 flex flex-col gap-4">
            <div class="flex flex-col gap-3">
                <Label for="name">Name</Label>
                <Input type="text" v-model="form.name" placeholder="John Doe" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="flex flex-col gap-3">
                <Label for="ip_address">IP Address</Label>
                <Input type="text" v-model="form.ip_address" placeholder="127.0.0.1" />
                <InputError :message="form.errors.ip_address" />
            </div>

            <div class="flex flex-col gap-3">
                <Label for="port">Port</Label>
                <Input type="text" v-model="form.port" placeholder="3000" />
                <InputError :message="form.errors.port" />
            </div>


            <div class="flex flex-col gap-3">
                <Label for="assigned_table">Assigned Table</Label>
                <Input type="text" :model-value="selectedTableName" class="w-[100px]" readonly
                    :key="`table-${form.table_id}`" />
                <InputError :message="form.errors.port" />
            </div>


            <div class="flex flex-col">
                <label class="mb-1 text-sm font-medium">Change Table Assignment</label>

                <Select v-model="form.table_id">
                    <SelectTrigger class="w-[180px]">
                        <SelectValue placeholder="Assign a Table" />
                    </SelectTrigger>
                    <SelectContent>
                        <!-- <SelectGroup> -->
                        <SelectLabel>Tables</SelectLabel>
                        <SelectItem v-for="table in computedTables" :value="table.id">
                            {{ table.name }}
                        </SelectItem>
                        <!-- </SelectGroup> -->
                    </SelectContent>
                </Select>

                <div v-if="form.errors.table_id" class="text-sm text-red-500 mt-1">{{ form.errors.table_id }}</div>
            </div>




        </form>
    </div>
    <SheetFooter>
        <div class="flex items-start flex-row gap-2 p-4">
            <SheetClose as-child>
                <Button type="button" variant="destructive" class="cursor-pointer ">
                    Cancel
                </Button>
            </SheetClose>
            <Button v-if="isEdit" type="button" variant="default" @click.prevent="createToken" class="text-sm">
                Generate Token
            </Button>
            <Button type="button" @click.prevent="submit" variant="outline"
                class="hover:bg-woosoo-primary-light hover:text-woosoo-primary-dark text-woosoo-primary-dark cursor-pointer"
                :disabled="form.processing">
                Save Changes
            </Button>
        </div>
    </SheetFooter>

</template>
font-size: 0.8em;

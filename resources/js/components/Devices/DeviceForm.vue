<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars, vue/valid-v-for */
import { computed, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { router, useForm } from '@inertiajs/vue3'
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import type { Device, Table } from '@/types/models'

const props = defineProps<{
    device: Device
    unassignedTables: Table[]
    formType?: 'create' | 'edit'
    inSheet?: boolean
}>()

const form = useForm({
    name: props.device?.name ?? '',
    ip_address: props.device?.ip_address ?? '',
    port: props.device?.port ?? undefined,
    table_id: props.device?.table_id ?? undefined,
    security_code: '',
})

const computedTables = computed(() => props.unassignedTables ) // unassigned tables

const selectedTableName = computed(() => {
  // if nothing selected yet, show the originally assigned table name
  if (!form.table_id) {
    return props.device.table?.name ?? ''
  }

  // if something is selected, look it up from computedTables
  const found = computedTables.value.find(t => String(t.id) === String(form.table_id))
  return found ? found.name : props.device.table?.name ?? ''
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
const showTokenDialog = ref(false)
const generatedToken = ref('')

async function createToken() {
    try {
        const url = route('devices.create.token', props.device?.id)
        const res = await axios.post(url, {}, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        if (res?.data?.success && res?.data?.token) {
            const token = res.data.token
            // copy to clipboard then prompt the admin
            await navigator.clipboard.writeText(token).catch(() => {})
            // Show success via toast + token in dialog for easy copying
            toast('Device token copied to clipboard', { description: 'Token issued and ready to paste into the device app.' })
            generatedToken.value = token
            showTokenDialog.value = true
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
    <div class="flex flex-col gap-3">
        <Separator class="my-0" />
        <!-- Branch context error — server-resolved, shown when install has no unambiguous branch -->
        <div v-if="form.errors.branch" class="mx-4 mt-3 rounded-md border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
            {{ form.errors.branch }}
        </div>
        <form  class="p-4 flex flex-col gap-4">
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

            <div v-if="!isEdit" class="flex flex-col gap-3">
                <Label for="security_code">Security Code</Label>
                <Input id="security_code" type="text" value="Auto-generated on save" readonly />
                <p class="text-xs text-muted-foreground">A unique 6-digit security code will be generated automatically when you create the device.</p>
                <InputError :message="form.errors.security_code" />
            </div>


            <div class="flex flex-col gap-3">
                <Label for="assigned_table">Assigned Table</Label>
                <Input id="assigned_table" type="text" :value="selectedTableName" class="w-25" readonly/>
                <InputError :message="form.errors.port" />
            </div>


            <div class="flex flex-col">
                <Label for="table_id" class="mb-1">Change Table Assignment</Label>
        
                <Select v-model="form.table_id">
                    <SelectTrigger id="table_id" class="w-45">
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
    <div class="border-t bg-muted/30">
        <div class="flex items-start flex-row gap-2 p-4">
            <SheetFooter v-if="inSheet">
                <SheetClose as-child>
                    <Button type="button" variant="destructive" class="cursor-pointer ">
                        Cancel
                    </Button>
                </SheetClose>
            </SheetFooter>
            <Button
                v-else
                type="button"
                variant="destructive"
                class="cursor-pointer"
                @click="router.get(route('devices.index'))"
            >
                Cancel
            </Button>
            <Button v-if="isEdit" type="button" variant="default" @click.prevent="createToken" class="text-sm">
                Generate Token
            </Button>
            <Button
                type="button"
                @click.prevent="submit"
                variant="outline"
                class="hover:bg-woosoo-primary-light hover:text-woosoo-primary-dark text-woosoo-primary-dark cursor-pointer"
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
                <DialogDescription>
                    This token has been copied to your clipboard. Store it securely.
                </DialogDescription>
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

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
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
}>()

const form = useForm({
    name: props.device.name as string,
    ip_address: props.device.ip_address as string,
    port: props.device.port as number,
    table_id: props.device.table_id as number
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

console.log(selectedTableName)
</script>

<template>
    <div class="flex flex-col gap-3">
        <Separator class="my-0" />
        <form  class="p-4 flex flex-col gap-4">
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
                <Input type="text" v-model="selectedTableName" class="w-[100px]" disabled/>
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
            <Button type="button" @click.prevent="submit" variant="outline"
                class="hover:bg-woosoo-primary-light hover:text-woosoo-primary-dark text-woosoo-primary-dark cursor-pointer"
                :disabled="form.processing">
                Save Changes
            </Button>
        </div>
    </SheetFooter>

</template>

<style scoped>
.error {
    color: red;
    font-size: 0.8em;
}
</style>

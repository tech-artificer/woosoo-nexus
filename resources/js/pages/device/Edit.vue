<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Pencil, Link, Trash } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
// import { Select } from '@/components/forms'

import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'

import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  // DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'

import type { Device, Table } from '@/types/models'

const props = defineProps<{
  device: Device
  unassignedTables: Table[]
}>()

const showDialog = ref(false)

const form = useForm({
  table_id: props.device.table_id,
})

const openDialog = () => {
  showDialog.value = true
  form.reset()
  form.table_id = props.device.table_id // this updates the value shown in Select
}

const submit = () => {
  form.post(`/devices/${props.device.id}/assign-table`, {
    forceFormData: true, // <-- THIS is the secret ingredient
    preserveScroll: true,
    // preserveState: true,

    onSuccess: () => {
      toast('Table Assigned:', {
        description: 'The table has been assigned to the device.',
        action: {
            label: 'Ok',
            variant: 'success',
            onClick: () => console.log('Ok'),
        },
        duration: 5000,
        position: 'top-right',
      });
      showDialog.value = false
    },

    onError: (errors) => {
      console.error('❌ Validation error', errors)
    },
  })
}


onMounted(() => {
  console.log(props.device)
});
</script>

<template>
  <Dialog v-model:open="showDialog">
    <TooltipProvider :delay-duration="100">
      <Tooltip>
        <TooltipTrigger as-child>
          <DialogTrigger as-child>

            <Button v-if="form.table_id" variant="ghost" class="cursor-pointer p-0" disabled>
              <Link  />
            </Button>

            <Button v-else variant="ghost" class="cursor-pointer p-0" @click="openDialog">
              <Link :class="{ 'pointer-events-none cursor-none': form.table_id != null }" />
            </Button>

          </DialogTrigger>
        </TooltipTrigger>
        <TooltipContent>
          <p>Assign Table</p>
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
    <TooltipProvider :delay-duration="100">
      <Tooltip>
        <TooltipTrigger as-child>
          <DialogTrigger as-child>
            <Button variant="ghost" class="cursor-pointer p-0" disabled>
              <Pencil />
            </Button>
          </DialogTrigger>
        </TooltipTrigger>
        <TooltipContent>
          <p>Click to View/Modify</p>
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>

    <TooltipProvider :delay-duration="100">
      <Tooltip>
        <TooltipTrigger as-child>
          <DialogTrigger as-child>
            <Button variant="ghost" class="cursor-pointer p-0" disabled>
              <Trash />
            </Button>
          </DialogTrigger>
        </TooltipTrigger>
        <TooltipContent>
          <p>Click to View/Modify</p>
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>

    <DialogContent class="sm:max-w-[600px]">
      <DialogHeader>
        <DialogTitle>{{ props.device.name }}</DialogTitle>
        <DialogDescription>
          <p>Assign a table to this device.</p>
        </DialogDescription>
      </DialogHeader>

      <form @submit.prevent="submit" class="grid gap-4">
        <div class="flex flex-col">
          <label class="mb-1 text-sm font-medium">List of Unassigned Tables</label>
          <Select v-model="form.table_id" :items="props.unassignedTables" />
          <div v-if="form.errors.table_id" class="text-sm text-red-500 mt-1">{{ form.errors.table_id }}</div>
        </div>

        <div class="flex items-center justify-between gap-2 flex-row-reverse mt-2">
          <Button type="submit"
            class="hover:bg-woosoo-primary-light hover:text-woosoo-primary-dark bg-woosoo-accent cursor-pointer text-gray-100 w-50"
            :disabled="form.processing">
            Save Changes
          </Button>

          <DialogClose as-child>
            <Button type="button" variant="secondary" class="cursor-pointer w-50">Close</Button>
          </DialogClose>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>

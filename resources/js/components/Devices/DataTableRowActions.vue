<script setup lang="ts">
import type { Row } from '@tanstack/vue-table'
import { Ellipsis } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  // AlertDialogTrigger,
} from "@/components/ui/alert-dialog"
import { ref, computed } from 'vue'
import type { Device, Table } from '@/types/models';
import DeviceForm from '@/components/Devices/DeviceForm.vue';
import { toast } from 'vue-sonner'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

import { usePage } from '@inertiajs/vue3'

const page = usePage()
const unassignedTables = page.props.unassignedTables as Table[]

interface DataTableRowActionsProps {
  row: Row<Device>
}
const props = defineProps<DataTableRowActionsProps>()
const computedDevice = computed(() => {
  const parsed = props.row.original
  return {
    ...parsed as Device
  }
})
// control sheet open state
const isSheetOpen = ref(false)
// const isRestoring = ref(null)
const openSheet = () => {
  isSheetOpen.value = true
}

const showDialog = ref(false);
const showSecurityCodeDialog = ref(false)
const rotatedSecurityCode = ref('')
const openDialog = () => {
  showDialog.value = true
}

const rotateSecurityCode = async (device: Device) => {
  try {
    const response = await axios.post(`/api/v2/devices/${device.id}/security-code`, {}, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
      },
    })

    const securityCode = response?.data?.security_code
    if (!securityCode) {
      toast.error('Security code rotation failed')
      return
    }

    rotatedSecurityCode.value = String(securityCode)
    showSecurityCodeDialog.value = true
    await navigator.clipboard.writeText(rotatedSecurityCode.value).catch(() => {})
    toast.success('Security code rotated and copied to clipboard')
  } catch (error: any) {
    toast.error(error?.response?.data?.message ?? 'Security code rotation failed')
  }
}

const deactivateAccount = (computedDevice: Device) => {
  router.visit(route('devices.destroy', computedDevice), {
    method: 'delete',
    onSuccess: () => {
     toast.warning('Account Deactivated')
    }
  })
}


const restore = (computedDevice: Device) => {
  // isRestoring.value = computedDevice.id
  
  router.patch(route('devices.restore', computedDevice.id), {}, {
    onSuccess: (page) => {
      // isRestoring.value = null
      // showNotification('success', `${computedDevice.name} has been restored successfully.`)
      console.log(page)
    },
    onError: (errors) => {
      console.log(errors)
      // isRestoring.value = null
      // showNotification('error', 'Failed to restore user. Please try again.')
    }
  })
}

// console.log(route('devices.restore', 3))
</script>

<template>
  <div @click.stop>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="ghost" class="flex h-8 w-8 p-0 data-[state=open]:bg-muted">
        <Ellipsis class="h-4 w-4" />
        <span class="sr-only">Open menu</span>
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-56">

      <DropdownMenuItem class="cursor-pointer" @click="openSheet">Edit Device</DropdownMenuItem>
      <DropdownMenuItem class="cursor-pointer" @click="rotateSecurityCode(computedDevice)">Regenerate Security Code</DropdownMenuItem>
      
        
      <DropdownMenuItem 
        @click="openDialog"
        v-if="!computedDevice.deleted_at" 
        class="text-orange cursor-pointer"
        >
        Deactivate 
      </DropdownMenuItem>
      <DropdownMenuItem 
        @click="restore(computedDevice)"
        v-else-if="computedDevice.deleted_at"
        class="text-green cursor-pointer text-sm">
        Activate
        <DropdownMenuSeparator />
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>

  <AlertDialog v-model:open="showDialog">
    <!-- <AlertDialogTrigger as-child>
      <Button variant="outline">
        Show Dialog
      </Button>
    </AlertDialogTrigger> -->
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
        <AlertDialogDescription>
          This action cannot be undone. This will permanently delete this
          account.
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel>Cancel</AlertDialogCancel>
        <AlertDialogAction class="bg-red-600 hover:bg-red-500" @click="deactivateAccount(computedDevice)">Continue</AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>


  <Sheet v-model:open="isSheetOpen">
    <SheetContent>
      <SheetHeader>
        <SheetTitle>Edit User</SheetTitle>
        <SheetDescription>
          Edit the user's information.
        </SheetDescription>
      </SheetHeader>
      <DeviceForm :device="computedDevice" :unassignedTables="unassignedTables" :in-sheet="true" />
    </SheetContent>
  </Sheet>

  <Dialog v-model:open="showSecurityCodeDialog">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>New Security Code</DialogTitle>
        <DialogDescription>
          This code is shown once. It was copied to your clipboard for immediate assignment.
        </DialogDescription>
      </DialogHeader>

      <div class="rounded-md bg-muted p-4 text-center font-mono text-xl tracking-widest">
        {{ rotatedSecurityCode }}
      </div>

      <DialogFooter>
        <Button type="button" @click="showSecurityCodeDialog = false">Close</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>

  </div>


</template>

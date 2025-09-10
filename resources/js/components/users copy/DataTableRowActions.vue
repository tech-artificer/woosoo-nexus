<script setup lang="ts">
import type { Row } from '@tanstack/vue-table'
import { Ellipsis } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { ref, computed } from 'vue'
import type { User } from '@/types';

import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'

interface DataTableRowActionsProps {
  row: Row<User>
}
const props = defineProps<DataTableRowActionsProps>()
const computedUser = computed(() => {
  const parsed = props.row.original
  return {
    ...parsed as User
  }
})
// control sheet open state
const isSheetOpen = ref(false)

const openSheet = () => {
  isSheetOpen.value = true
}

import UserForm from '@/components/users/UserForm.vue';

</script>

<template>

  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="ghost" class="flex h-8 w-8 p-0 data-[state=open]:bg-muted">
        <Ellipsis class="h-4 w-4" />
        <span class="sr-only">Open menu</span>
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-[160px]">

      <DropdownMenuItem class="cursor-pointer" @click="openSheet">Edit User</DropdownMenuItem>
      
      <DropdownMenuItem :disabled="computedUser.role as any === 'Owner'"
          v-if="computedUser.is_active"
        class="text-orange cursor-pointer">
        Deactivate account 
      </DropdownMenuItem>
      
      <DropdownMenuItem v-else-if="!computedUser.is_active && computedUser.role?.name !== 'Administrator'"
        class="text-green cursor-pointer">
        Activate account
      </DropdownMenuItem>

  
    </DropdownMenuContent>
  </DropdownMenu>


  <Sheet v-model:open="isSheetOpen">
    <SheetContent>
      <SheetHeader>
        <SheetTitle>Edit User</SheetTitle>
        <SheetDescription>
          Edit the user's information.
        </SheetDescription>
      </SheetHeader>
      <UserForm :user="computedUser" form-type="edit" />
    </SheetContent>
  </Sheet>


</template>

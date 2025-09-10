<script setup lang="ts">
import type { Row } from '@tanstack/vue-table'
import type { Device } from '@/types/models'
// import { computed } from 'vue'
import { Ellipsis } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  // DropdownMenuItem,
  // DropdownMenuRadioGroup,
  // DropdownMenuRadioItem,
  // DropdownMenuSeparator,
  // DropdownMenuShortcut,
  // DropdownMenuSub,
  // DropdownMenuSubContent,
  // DropdownMenuSubTrigger,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { onMounted } from 'vue'

// import { roles } from '@/components/users/data/data'
// import { userSchema } from '@/components/users/data/schema'

interface DataTableRowActionsProps {
  row: Row<Device>
}
const props = defineProps<DataTableRowActionsProps>()
// const user = computed(() => userSchema.parse(props.row.original))

onMounted(() => {
  console.log(props.row.original)
})


</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button
        variant="ghost"
        class="flex h-8 w-8 p-0 data-[state=open]:bg-muted"
      >
        <Ellipsis class="h-4 w-4" />
        <span class="sr-only">Open menu</span>
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-[160px]">
      <!-- <DropdownMenuItem @click="console.log(user)">View</DropdownMenuItem> -->
      <!-- <DropdownMenuItem>Modify Role</DropdownMenuItem>
      <DropdownMenuSeparator />
      <DropdownMenuItem class="text-woosoo-red">Deactivate</DropdownMenuItem> -->
    </DropdownMenuContent>
  </DropdownMenu>
</template>

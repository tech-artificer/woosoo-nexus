<script setup lang="ts">
import type { Table } from '@tanstack/vue-table'
import type { DeviceOrder } from '@/types/models'
import { RefreshCcw } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { computed } from 'vue'
// import {  statuses } from '@/composables/useData'
// import DataTableFacetedFilter from '@/components/Orders/DataTableFacetedFilter.vue'
// import DataTableViewOptions from '@/components/users/components//DataTableViewOptions.vue'
// import AddUser from '@/components/Orders/Register.vue'


// import { usePage } from '@inertiajs/vue3'


// const page = usePage()

// Safe access

interface DataTableToolbarProps {
  table: Table<DeviceOrder>
}

const props = defineProps<DataTableToolbarProps>()

const isFiltered = computed(() => props.table.getState().columnFilters.length > 0)
</script>

<template>
  <div class="flex flex-row flex-wrap justify-between gap-2">

    <div class="flex flex-row flex-wrap gap-2">
        <Input
        placeholder="Filter Orders..."
        :model-value="(table.getColumn('order_number')?.getFilterValue() as string) ?? ''"
        class="h-8 w-[150px] lg:w-[250px]"
        @input="table.getColumn('name')?.setFilterValue($event.target.value)"
      />

      <Button
      v-if="isFiltered"
      variant="outline"
      class="h-8 px-2 lg:px-3 flex justify-between items-center"
      @click="table.resetColumnFilters()"
    >
      <RefreshCcw class="text-green h-3 w-3" />
    </Button>

    <!-- Total -->
    </div>

    <div class="flex flex-row flex-wrap gap-2">
        
      <!-- <Button variant="outline" class="h-8 px-2 lg:px-3 ">
      <HardDriveDownload class="h-4 w-4" />
    </Button>
         <AddUser />
    -->
    </div>
    <!-- <DataTableViewOptions :table="table" /> -->
  </div>
</template>

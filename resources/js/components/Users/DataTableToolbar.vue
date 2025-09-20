<script setup lang="ts">
import type { Table } from '@tanstack/vue-table'
import type { Role, Branch, User } from '@/types/models'
import { RefreshCcw, HardDriveDownload } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { computed } from 'vue'
// import {  statuses } from '@/composables/useData'
// import DataTableFacetedFilter from '@/components/users/DataTableFacetedFilter.vue'
// import DataTableViewOptions from '@/components/users/components//DataTableViewOptions.vue'
// import AddUser from '@/components/users/Register.vue'


import { usePage } from '@inertiajs/vue3'


const page = usePage()

// Safe access
const backendRoles = (page.props.roles ?? []) as Role[]
const backendBranches = (page.props.branches ?? []) as Branch[]

const roleOptions = backendRoles.map(role => ({
  value: role.name,
  label: role.name,
}))

const branchOptions = backendBranches.map(branch => ({
  value: branch.name,
  label: branch.name.charAt(0).toUpperCase() + branch.name.slice(1),
}))

interface DataTableToolbarProps {
  table: Table<User>
}

const props = defineProps<DataTableToolbarProps>()

const isFiltered = computed(() => props.table.getState().columnFilters.length > 0)
</script>

<template>
  <div class="flex flex-row flex-wrap justify-between gap-2">

   

    <div class="flex flex-row flex-wrap gap-2">
        <Input
        placeholder="Filter Users..."
        :model-value="(table.getColumn('name')?.getFilterValue() as string) ?? ''"
        class="h-8 w-[150px] lg:w-[250px]"
        @input="table.getColumn('name')?.setFilterValue($event.target.value)"
      />
     <DataTableFacetedFilter
        v-if="table.getColumn('branches')"
        :column="table.getColumn('branches')"
        title="Branches"
        :options="branchOptions"
      />
      <DataTableFacetedFilter
        v-if="table.getColumn('roles')"
        :column="table.getColumn('roles')"
        title="Roles"
        :options="roleOptions"
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
        
      <Button variant="outline" class="h-8 px-2 lg:px-3 ">
      <HardDriveDownload class="h-4 w-4" />
    </Button>
         <AddUser />
   
    </div>
    <!-- <DataTableViewOptions :table="table" /> -->
  </div>
</template>

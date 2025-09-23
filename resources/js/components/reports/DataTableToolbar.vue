<script setup lang="ts">
import type { Table } from '@tanstack/vue-table'
import type { User } from '@/types/models';
import { computed } from 'vue'

import { Plus } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

import { roles, statuses } from '../data/data'
import DataTableFacetedFilter from '@/components/users/components/DataTableFacetedFilter.vue'
// import DataTableViewOptions from '@/components/users/components//DataTableViewOptions.vue'
import DataTableAddUser from '@/components/users/components//DataTableAddUser.vue'
import AddUser from '@/components/users/components/Register.vue'

interface DataTableToolbarProps {
  table: Table<User>
}

const props = defineProps<DataTableToolbarProps>()

const isFiltered = computed(() => props.table.getState().columnFilters.length > 0)
</script>

<template>
  <div class="flex items-center justify-between">
    <div class="flex flex-1 items-center space-x-2">
      <Input
        placeholder="Filter Users..."
        :model-value="(table.getColumn('name')?.getFilterValue() as string) ?? ''"
        class="h-8 w-[150px] lg:w-[250px]"
        @input="table.getColumn('name')?.setFilterValue($event.target.value)"
      />
      <DataTableFacetedFilter
        v-if="table.getColumn('status')"
        :column="table.getColumn('status')"
        title="Statuses"
        :options="statuses"
      /> 
      <DataTableFacetedFilter
        v-if="table.getColumn('role')"
        :column="table.getColumn('role')"
        title="Roles"
        :options="roles"
      />

      <Button
        v-if="isFiltered"
        variant="ghost"
        class="h-8 px-2 lg:px-3"
        @click="table.resetColumnFilters()"
      >
        Reset
        <Plus class="ml-2 h-4 w-4" />
      </Button> 
    </div>
    <!-- <DataTableAddUser/> -->
    <AddUser class="px-4"/>
    <!-- <DataTableViewOptions :table="table" /> -->
  </div>
</template>

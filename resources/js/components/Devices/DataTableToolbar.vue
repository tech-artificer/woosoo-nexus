<script setup lang="ts">
import type { Table } from '@tanstack/vue-table'
import type { Device } from '@/types/models';
import { RefreshCcw } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { computed } from 'vue'

interface DataTableToolbarProps {
  table: Table<Device>
}

const props = defineProps<DataTableToolbarProps>()

const isFiltered = computed(() => props.table.getState().columnFilters.length > 0)
</script>

<template>

  <div class="flex flex-row flex-wrap justify-between gap-2">

    <div class="flex flex-row flex-wrap gap-2">
        <Input
        placeholder="Filter Device..."
        :model-value="(table.getColumn('name')?.getFilterValue() as string) ?? ''"
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
    </div>

  </div>
</template>

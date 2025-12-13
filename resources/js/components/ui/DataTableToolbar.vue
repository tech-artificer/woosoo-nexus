<script setup lang="ts">
import type { Table } from '@tanstack/vue-table'
import { RefreshCcw } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { computed } from 'vue'
import DataTableFacetedFilter from '@/components/ui/DataTableFacetedFilter.vue'

interface DataTableToolbarProps {
  table: Table<any>
}

const props = defineProps<DataTableToolbarProps>()

const table = props.table

const isFiltered = computed(() => table.getState().columnFilters.length > 0)

function hasColumn(id: string) {
  return table.getAllColumns().some((c: any) => c.id === id)
}

const courseOptions = computed(() => {
  if (!hasColumn('course')) return []
  const col = table.getColumn('course')
  const facets = col.getFacetedUniqueValues?.()
  if (!facets) return []
  return Array.from(facets.keys()).map((v: any) => ({ label: String(v), value: String(v) }))
})

const categoryOptions = computed(() => {
  if (!hasColumn('category')) return []
  const col = table.getColumn('category')
  const facets = col.getFacetedUniqueValues?.()
  if (!facets) return []
  return Array.from(facets.keys()).map((v: any) => ({ label: String(v), value: String(v) }))
})

const groupOptions = computed(() => {
  if (!hasColumn('group')) return []
  const col = table.getColumn('group')
  const facets = col.getFacetedUniqueValues?.()
  if (!facets) return []
  return Array.from(facets.keys()).map((v: any) => ({ label: String(v), value: String(v) }))
})

const isAvailableFiltered = computed(() => {
  if (!hasColumn('is_available')) return false
  return !!table.getColumn('is_available')?.getFilterValue()
})
</script>

<template>
  <div class="flex flex-row flex-wrap justify-between gap-2">

    <div class="flex flex-row flex-wrap gap-2">
        <Input
          placeholder="Filter..."
          :model-value="hasColumn('name') ? ((table.getColumn('name')?.getFilterValue() as string) ?? '') : ''"
          class="h-8 w-[150px] lg:w-[250px]"
          @input="(e) => { if (hasColumn('name')) table.getColumn('name')?.setFilterValue(e.target.value) }"
        />
      <DataTableFacetedFilter
        v-if="hasColumn('course')"
        :column="table.getColumn('course')"
        title="Course"
        :options="courseOptions"
      />

      <DataTableFacetedFilter
        v-if="hasColumn('category')"
        :column="table.getColumn('category')"
        title="Category"
        :options="categoryOptions"
      />

      <DataTableFacetedFilter
        v-if="hasColumn('group')"
        :column="table.getColumn('group')"
        title="Group"
        :options="groupOptions"
      />

      <Button
        variant="outline"
        size="sm"
        class="h-8 border-dashed text-xs"
        @click="() => {
          if (!hasColumn('is_available')) return
          const col = table.getColumn('is_available')
          const current = col.getFilterValue()
          col.setFilterValue(current ? undefined : true)
        }"
      >
        {{ isAvailableFiltered ? 'Showing Available' : 'All' }}
      </Button>

      <Button
      v-if="isFiltered"
      variant="outline"
      class="h-8 px-2 lg:px-3 flex justify-between items-center"
      @click="table.resetColumnFilters()"
    >
      <RefreshCcw class="text-green h-3 w-3" />
    </Button>
    </div>

    <div class="flex flex-row flex-wrap gap-2">
    </div>
  </div>
</template>

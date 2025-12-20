import { computed } from 'vue'
import type { Table } from '@tanstack/vue-table'

interface FacetedFilterOption {
  label: string
  value: string
}

/**
 * Create faceted filter options for a table column.
 * Extracts unique values from column facets and formats them as filter options.
 */
export function useFacetedOptions(
  table: Table<any>,
  columnId: string
): { options: typeof computed<FacetedFilterOption[]>; hasColumn: boolean } {
  const hasColumn = table.getAllColumns().some((c: any) => c.id === columnId)

  const options = computed(() => {
    if (!hasColumn) return []
    const col = table.getColumn(columnId)
    if (!col) return []
    const facets = col.getFacetedUniqueValues?.()
    if (!facets) return []
    return Array.from(facets.keys()).map((v: any) => ({
      label: String(v),
      value: String(v),
    }))
  })

  return { options, hasColumn }
}

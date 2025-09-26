import type { ColumnDef } from '@tanstack/vue-table'
import type { Menu } from '@/types/models';
import { h } from 'vue'
// import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import DataTableColumnHeader from '@/components/Menus/DataTableColumnHeader.vue'
import DataTableRowActions from '@/components/Menus/DataTableRowActions.vue'
import MenuName from '@/components/Menus/MenuName.vue'
import { Check, X } from 'lucide-vue-next'

export const columns: ColumnDef<Menu, any>[] = [
  {
    id: 'select',
    header: ({ table }) => h(Checkbox, {
      'modelValue': table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate'),
      'onUpdate:modelValue': value => table.toggleAllPageRowsSelected(!!value),
      'ariaLabel': 'Select all',
      'class': 'translate-y-0.5 flex space-x-2',
    }),
    cell: ({ row }) => h(Checkbox, { 'modelValue': row.getIsSelected(), 'onUpdate:modelValue': value => row.toggleSelected(!!value), 'ariaLabel': 'Select row', 'class': 'translate-y-0.5' }),
  },
  {
    accessorKey: 'name',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Name', class: 'w-[150px]' }),

    cell: ({ row }) => {
       return h(MenuName, { menu: row.original });
    },
  },
   {
      accessorKey: 'is_taxable',
      header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Taxable', class: 'w-[150px]' }),
     enableColumnFilter: false,
    enableSorting: false,
      cell: ({ row }) => {
        const is_taxable = row.getValue('is_taxable')
        return h('div', { class: 'flex space-x-2' }, [
            is_taxable ? 
            h(Check, { class: 'text-green-500' })
            : h(X, { class: 'text-red-500' }),
        ])
      },
    },
    {
      accessorKey: 'is_modifier_only',
      header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Modifier only', class: 'w-[150px]' }),
       enableColumnFilter: false,
    enableSorting: false,
      cell: ({ row }) => {
        const is_modifier_only = row.getValue('is_modifier_only')

        return h('div', { class: 'flex space-x-2' }, [
            is_modifier_only ? 
            h(Check, { class: 'text-green-500' })
            : h(X, { class: 'text-red-500' } ),
        ])
      },
    },
  {
    accessorKey: 'price',
    // enableColumnFilter: false,
    // enableSorting: false,
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Price', class: 'w-[200px]' }),
    cell: ({ row }) => h('div', { class: 'w-20 flex space-x-2' }, row.getValue('price')),
  },
  {
      accessorKey: 'id',
      header: ({ column }) => h(DataTableColumnHeader, { column, title: '' }),
      enableColumnFilter: false,
      enableSorting: false,
      cell: ({ row }) => {
        return h(DataTableRowActions, { row })
      }
    },
]

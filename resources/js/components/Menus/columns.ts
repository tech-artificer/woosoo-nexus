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
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Name' }),
    cell: ({ row }) => {
       return h(MenuName, { menu: row.original });
    },
  },
  {
    accessorKey: 'category',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Category' }),
    cell: ({ row }) => h('div', {}, row.getValue('category')),
  },
  {
    accessorKey: 'price',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Price' }),
    cell: ({ row }) => h('div', { class: 'font-medium' }, '₱' + row.getValue('price')),
  },
  {
    accessorKey: 'is_available',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Available' }),
    enableSorting: false,
    cell: ({ row }) => {
      const available = row.getValue('is_available')
      return h('div', { class: 'flex items-center justify-center' }, [
        available ? h(Check, { class: 'h-4 w-4 text-green-500' }) : h(X, { class: 'h-4 w-4 text-red-500' })
      ])
    },
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

import type { ColumnDef } from '@tanstack/vue-table'
import type { Menu } from '@/types/models';
import { h } from 'vue'
// import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import DataTableColumnHeader from '@/components/Menus/DataTableColumnHeader.vue'
// import DataTableRowActions from '@/components/Menus/DataTableRowActions.vue'
import MenuName from '@/components/Menus/MenuName.vue'

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
    accessorKey: 'price',
    enableColumnFilter: false,
    enableSorting: false,
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Price', class: 'w-[200px]' }),
    cell: ({ row }) => h('div', { class: 'w-20 flex space-x-2' }, row.getValue('price')),
  },
]

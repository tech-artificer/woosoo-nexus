import type { ColumnDef } from '@tanstack/vue-table'
import { h } from 'vue'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import DataTableColumnHeader from './DataTableColumnHeader.vue'
import DataTableRowActions from './DataTableRowActions.vue'

export interface Role {
  id: number
  name: string
  guard_name: string
  permissions_count: number
  users_count: number
  created_at: string
  updated_at: string
}

export const columns: ColumnDef<Role>[] = [
  {
    id: 'select',
    header: ({ table }) => h(Checkbox, {
      'checked': table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate'),
      'onUpdate:checked': (value: boolean) => table.toggleAllPageRowsSelected(!!value),
      'ariaLabel': 'Select all',
    }),
    cell: ({ row }) => h(Checkbox, {
      'checked': row.getIsSelected(),
      'onUpdate:checked': (value: boolean) => row.toggleSelected(!!value),
      'ariaLabel': 'Select row',
    }),
    enableSorting: false,
    enableHiding: false,
  },
  {
    accessorKey: 'name',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Role Name' }),
    cell: ({ row }) => {
      return h('div', { class: 'font-medium' }, row.getValue('name'))
    },
    enableSorting: true,
    enableHiding: false,
  },
  {
    accessorKey: 'guard_name',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Guard' }),
    cell: ({ row }) => {
      const guard = row.getValue('guard_name') as string
      return h(Badge, { variant: 'outline' }, () => guard)
    },
    filterFn: (row, id, value) => {
      return value.includes(row.getValue(id))
    },
  },
  {
    accessorKey: 'permissions_count',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Permissions' }),
    cell: ({ row }) => {
      const count = row.getValue('permissions_count') as number
      return h('div', { class: 'text-center' }, 
        h(Badge, { variant: count > 0 ? 'default' : 'secondary' }, () => count)
      )
    },
    enableSorting: true,
  },
  {
    accessorKey: 'users_count',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Users' }),
    cell: ({ row }) => {
      const count = row.getValue('users_count') as number
      return h('div', { class: 'text-center' }, 
        h(Badge, { variant: count > 0 ? 'default' : 'secondary' }, () => count)
      )
    },
    enableSorting: true,
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Created' }),
    cell: ({ row }) => {
      const date = new Date(row.getValue('created_at'))
      return h('div', { class: 'text-sm text-muted-foreground' }, 
        date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
      )
    },
    enableSorting: true,
  },
  {
    id: 'actions',
    cell: ({ row }) => h(DataTableRowActions, { row }),
  },
]

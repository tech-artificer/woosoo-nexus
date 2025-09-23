import type { ColumnDef } from '@tanstack/vue-table'
import type { User } from '@/types/models';
import { h } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import DataTableColumnHeader from '@/components/Users/DataTableColumnHeader.vue'
import DataTableRowActions from '@/components/Users/DataTableRowActions.vue'

export const columns: ColumnDef<User, any>[] = [
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

      return h('div', { class: 'flex space-x-2' }, [
        h('span', { class: ' font-medium' }, row.getValue('name'))
      ])
    },
  },
  {
    accessorKey: 'email',
    enableColumnFilter: false,
    enableSorting: false,
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Email', class: 'w-[200px]' }),
    cell: ({ row }) => h('div', { class: 'w-20 flex space-x-2' }, row.getValue('email')),
  },
  {
    accessorKey: 'roles',
    enableColumnFilter: false,
    enableSorting: false,
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Role' }),
     cell: ({ row }) => {
      // 
      const roles = row.original.roles
      return h('div', { class: 'flex gap-1' }, roles.map(role => h(Badge, { variant: 'outline', class: 'text-xs capitalize' },() => role.name)))
      //   [
      //   h(Badge, { variant: 'outline' }, roles.map(role => role.name).join(', '))
      // ]
    // )
    },
     accessorFn: (row) => row.roles?.map((r: any) => r.name) ?? [],
      filterFn: (row, columnId, filterValues) => {
        const roleNames = row.getValue(columnId) as string[]
        return filterValues.some((val: string) => roleNames.includes(val))
      }
    // cell: ({ row }) => h(Badge, { variant: 'outline' }, () => row.getValue('role') ?? '-'),
  },
  {
    accessorKey: 'deleted_at',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Status' }),
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) =>
      h('div', { class: 'w-auto text-sm' }, h(Badge, { variant: 'outline' }, () => !row.getValue('deleted_at') ? 'active' : 'inactive')),
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

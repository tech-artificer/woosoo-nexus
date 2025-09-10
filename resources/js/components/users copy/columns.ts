import type { ColumnDef } from '@tanstack/vue-table'
import type { User } from '@/types';
import { h } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import DataTableColumnHeader from '@/components/users/DataTableColumnHeader.vue'
import DataTableRowActions from '@/components/users/DataTableRowActions.vue'

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
    accessorKey: 'branches',
    enableSorting: false,
    header: ({ column }) =>
      h(DataTableColumnHeader, { column, title: 'Branch' }),
    cell: ({ row }) => {
      const branches = row.getValue('branches') as { name: string }[]
      console.log(branches);
      return h('div', { class: 'flex space-x-2' }, [
        h('span', { class: 'max-w-[500px] truncate font-semibold text-xs' }, row.original.branches?.map(branch => branch.name).join(', '))
      ])
    },

    accessorFn: (row) => row.branches?.map((b: any) => b.name) ?? [],
    filterFn: (row, columnId, filterValues) => {
      const branchNames = row.getValue(columnId) as string[]
      return filterValues.some((val: string) => branchNames.includes(val))
    }
  },
  {
    accessorKey: 'role',
    enableColumnFilter: false,
    enableSorting: false,
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Role' }),
    cell: ({ row }) => h(Badge, { variant: 'outline' }, () => row.getValue('role') ?? '-'),
  },
  {
    accessorKey: 'is_active',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Status' }),
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) =>
      h('div', { class: 'w-auto text-sm' }, h(Badge, { variant: row.getValue('is_active') ? 'active' : 'destructive', class: '' }, () => row.getValue('is_active') ? 'active' : 'inactive')),
  },
  {
    accessorKey: 'id',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: '' }),
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) => {

      const user = row.original as User

      // if( user.role as any == 'Owner' || user.role as any == 'Admin' ) {
      //   return h('div', { row })
      // }

      return h(DataTableRowActions, { row })
    }
  },
]

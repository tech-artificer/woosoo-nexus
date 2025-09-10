import { h } from 'vue';
import { ColumnDef } from '@tanstack/vue-table';
import { Device, Table, DeviceRegistrationCode } from '@/types/models';
import DataTableColumnHeader from '@/components/devices/components/DataTableColumnHeader.vue'
import DataTableRowActions from '@/components/devices/components/DataTableRowActions.vue'
import DeviceUnassignedBadge from '@/pages/device/UnassignedBadge.vue';
import ActiveBadge from '@/pages/device/ActiveBadge.vue';
import DeviceEditDialog from '@/pages/device/Edit.vue';
// import { Minus, Check } from 'lucide-vue-next';

export const columns: ColumnDef<User>[] = [
  {
    accessorKey: 'name',
    header: 'Device',
    cell: ({ row }) => h('div', { class: 'capitalize w-25' }, row.getValue('name')),
  },
  {
    accessorKey: '',
    header: 'Branch',
    cell: ({ row }) => {
      const branchName = row.original.branch?.name;
      return h('div', { class: 'capitalize w-25' }, branchName);
    },
  },
  {
    accessorKey: 'table_id',
    header: () => h('div', { }, 'Table'),
    cell: ({ row }) => h(DeviceUnassignedBadge, { row: row.original }),
  },
  {
    accessorKey: 'ip_address',
    header: 'IP',
    cell: ({ row }) => h('div', { class: 'capitalize' }, row.getValue('ip_address')),   
  },
  {
    accessorKey: 'last_ip_address',
    header: 'Last IP',
    cell: ({ row }) => h('div', { class: 'capitalize' }, row.getValue('last_ip_address')),   
  },
  {
    accessorKey: 'last_seen_at',
    header: 'Last Seen At',
    cell: ({ row }) => h('div', { class: 'capitalize' }, row.getValue('last_seen_at')),   
  },
  {
    accessorKey: 'is_active',
    header: () => h('div', { class: 'text-center w-15' }, 'Status'),
    cell: ({ row }) => {
        return h(ActiveBadge, { row: row.original, class: 'text-center w-15' })
    } 
  },
  {
    accessorKey: 'id',
    header: '',
    cell: ({ row }) => {
      const device = row.original
      return h(DeviceEditDialog, { class : 'justify-end w-15',
        device,
        unassignedTables,
      })
    },
  }
];

export const getDeviceRegistrationCodeColumns = (): ColumnDef<DeviceRegistrationCode>[] => [
  {
    accessorKey: 'code',
    header: 'Code',
    cell: ({ row }) => h('div', { class: 'capitalize' }, row.getValue('code')),
  },
];


import type { ColumnDef } from '@tanstack/vue-table'
import type { User } from '@/types/models';

import { h } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
// import { roles, statuses } from '../data/data'
import DataTableColumnHeader from '@/components/users/components/DataTableColumnHeader.vue'
import DataTableRowActions from '@/components/users/components/DataTableRowActions.vue'

export const columns: ColumnDef<User>[] = [
  {
    id: 'select',
    header: ({ table }) => h(Checkbox, {
      'modelValue': table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate'),
      'onUpdate:modelValue': value => table.toggleAllPageRowsSelected(!!value),
      'ariaLabel': 'Select all',
      'class': 'translate-y-0.5',
    }),
    cell: ({ row }) => h(Checkbox, { 'modelValue': row.getIsSelected(), 'onUpdate:modelValue': value => row.toggleSelected(!!value), 'ariaLabel': 'Select row', 'class': 'translate-y-0.5' }),
    enableSorting: false,
    enableHiding: false,
  },
  // {
  //   accessorKey: 'name',
  //   header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Name' }),
  //   cell: ({ row }) => h('div', { class: 'w-20' }, row.getValue('name')),
    
  //   enableSorting: false,
  //   enableHiding: false,
  // },
  // {
  //   accessorKey: 'id',
  //   header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Role' }),
  //   cell: ({ row }) => h('div', { class: 'w-20' }, row.original.roles?.map(role => role.name).join(', ')),
    
  //   enableSorting: false,
  //   enableHiding: false,
  // },
  {
    accessorKey: 'name',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Name' }),

    cell: ({ row }) => {

      return h('div', { class: 'flex space-x-2' }, [
        h('span', { class: 'max-w-[500px] truncate font-medium' }, row.getValue('name'))
      ])
    },
  },
  {
    accessorKey: 'email',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Email' }),
    cell: ({ row }) => h('div', { class: 'w-20' }, row.getValue('email')),
    
    enableSorting: false,
    enableHiding: false,
  },
  {
    accessorKey: 'role',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Role' }),
    cell: ({ row }) => h(Badge, { variant: 'outline' }, () => row.getValue('role') ?? '-' ),
    
    enableSorting: false,
    enableHiding: false,
  },
  {
    accessorKey: 'status',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Status' }),
    cell: ({ row }) => 
    h('div', { class: 'w-20' }, h(Badge, { variant: row.getValue('status') ? 'success' : 'outline' }, () => row.getValue('status') ?? '-' ) ),
    enableSorting: false,
    enableHiding: false,
  },
  // {
  //   accessorKey: 'role',
  //   header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Role' }),

  //   cell: ({ row }) => {
  //     console.log(row.original.roles);
  //     const roles = row.original.roles?.find(
  //       role => role.name === row.getValue('role'),
  //     )

  //     if (!status)
  //       return null
  //     console.log()
  //     return h('div', { class: 'flex w-[100px] items-center' }, [
  //       status.icon && h(status.icon, { class: 'mr-2 h-4 w-4 text-muted-foreground' }),
  //       h('span', status.label),
  //     ])
  //   },
  //   filterFn: (row, id, value) => {
  //     return value.includes(row.getValue(id))
  //   },
  // },
  // {
  //   accessorKey: 'priority',
  //   header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Priority' }),
  //   cell: ({ row }) => {
  //     const priority = priorities.find(
  //       priority => priority.value === row.getValue('priority'),
  //     )

  //     if (!priority)
  //       return null

  //     return h('div', { class: 'flex items-center' }, [
  //       priority.icon && h(priority.icon, { class: 'mr-2 h-4 w-4 text-muted-foreground' }),
  //       h('span', {}, priority.label),
  //     ])
  //   },
  //   filterFn: (row, id, value) => {
  //     return value.includes(row.getValue(id))
  //   },
  // },
  {
    id: 'actions',
    cell: ({ row }) => h(DataTableRowActions, { row }),
  },
]

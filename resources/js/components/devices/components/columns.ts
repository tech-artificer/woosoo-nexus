import type { ColumnDef } from '@tanstack/vue-table'
import { h } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import DataTableColumnHeader from '@/components/devices/components/DataTableColumnHeader.vue'
import DataTableRowActions from '@/components/devices/components/DataTableRowActions.vue'
import { Device, DeviceRegistrationCode, Table } from '@/types/models';
import DeviceUnassignedBadge from '@/pages/device/UnassignedBadge.vue';
// import ActiveBadge from '@/pages/device/ActiveBadge.vue';
import DeviceEditDialog from '@/components/devices/Edit.vue';


export const getDeviceColumns = (unassignedTables: Table[]): ColumnDef<Device>[] => [
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
  //     accessorKey: 'id',
  //     header: '',
  //     cell: ({ row }) => {
  //       const device = row.original
  //       return h(DeviceEditDialog, { class : 'justify-end w-15',
  //         device,
  //         unassignedTables,
  //       })
  //     },
  //   },
  // {
  //   accessorKey: 'name',
  //   header: 'Device',
  //   cell: ({ row }) => h('div', { class: 'capitalize w-25' }, row.getValue('name')),
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
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Status' }),
    cell: ({ row }) => 
    h('div', { class: 'w-20' }, h(Badge, { variant: row.getValue('is_active') ? 'success' : 'outline' }, () => row.getValue('is_active') ? 'Active' : 'Inactive' ) ),
    enableSorting: false,
    enableHiding: false,
  },
  // {
  //   accessorKey: 'is_active',
  //   header: () => h('div', { class: 'text-center w-15' }, 'Status'),
  //   cell: ({ row }) => {
  //       return h(ActiveBadge, { row: row.original, class: 'text-center w-15' })
  //   } 
  // },
  // {
  //   accessorKey: 'id',
  //   header: '',
  //   cell: ({ row }) => {
  //     const device = row.original
  //     return h(DeviceEditDialog, { class : 'justify-end w-15',
  //       device,
  //       unassignedTables,
  //     })
  //   },
  // },
  {
    id: 'actions',
    cell: ({ row }) => h(DataTableRowActions, { row }),
  },
];



export const getDeviceRegistrationCodeColumns = (): ColumnDef<DeviceRegistrationCode>[] => [
  {
    accessorKey: 'code',
    header: 'Code',
    cell: ({ row }) => h('div', { class: 'capitalize' }, row.getValue('code')),
  },
    {
      accessorKey: 'is_active',
      header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Active' }),
      cell: ({ row }) => 
      h('div', { class: 'w-20' }, h(Badge, { variant: row.getValue('is_active') ? 'success' : 'outline' }, () => row.getValue('is_active') ?? '-' ) ),
      enableSorting: false,
      enableHiding: false,
    },
//   {
//     accessorKey: '',
//     header: 'Branch',
//     cell: ({ row }) => {
//       const branchName = row.original.branch?.name;
//       return h('div', { class: 'capitalize' }, branchName);
//     },
//   },
//   {
//     accessorKey: 'table_id',
//     header: () => h('div', { }, 'Table'),
//     cell: ({ row }) => h(DeviceUnassignedBadge, { row: row.original }),
//   },
//   {
//     accessorKey: 'ip_address',
//     header: 'IP',
//     cell: ({ row }) => h('div', { class: 'capitalize' }, row.getValue('ip_address')),   
//   },
//   {
//     accessorKey: 'last_ip_address',
//     header: 'Last IP',
//     cell: ({ row }) => h('div', { class: 'capitalize' }, row.getValue('last_ip_address')),   
//   },
//   {
//     accessorKey: 'last_seen_at',
//     header: 'Last Seen At',
//     cell: ({ row }) => h('div', { class: 'capitalize' }, row.getValue('last_seen_at')),   
//   },
//   {
//     accessorKey: 'is_active',
//     header: () => h('div', { class: 'text-center w-15' }, 'Active'),
//     cell: ({ row }) => {
//         return h(ActiveBadge, { row: row.original, class: 'text-center w-15' })
//     } 
//   },
//   {
//     accessorKey: 'id',
//     header: '',
//     cell: ({ row }) => {
//       const device = row.original
//       return h(DeviceEditDialog, {
//         device,
//         unassignedTables,
//       })
//     },
//   },
];



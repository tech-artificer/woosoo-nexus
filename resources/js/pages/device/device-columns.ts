import { h } from 'vue';
import { ColumnDef } from '@tanstack/vue-table';
import { Device, Table, DeviceRegistrationCode } from '@/types/models';
import DeviceUnassignedBadge from '@/pages/device/UnassignedBadge.vue';
import ActiveBadge from '@/pages/device/ActiveBadge.vue';
import DeviceEditDialog from '@/pages/device/Edit.vue';
// import { Minus, Check } from 'lucide-vue-next';

export const getDeviceColumns = (unassignedTables: Table[]): ColumnDef<Device>[] => [
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
  {
    accessorKey: 'id',
    header: '',
    cell: ({ row }) => {
      const device = row.original
      return h(DeviceEditDialog, {
        device,
        unassignedTables,
      })
    },
  }
];


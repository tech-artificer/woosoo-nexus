import { h } from 'vue';
import { ColumnDef } from '@tanstack/vue-table';
import { Device } from '@/types/models';
import DeviceUnassignedBadge from '@/pages/device/DeviceUnassignedBadge.vue';
import DeviceEdit from '@/pages/device/DeviceEdit.vue';
import { Circle } from 'lucide-vue-next';

export const deviceColumns: ColumnDef<Device>[] = [
  {
    accessorKey: 'name',
    header: 'Device',
    cell: ({ row }) => h('div', { class: 'capitalize' }, row.getValue('name')),
  },
  {
    accessorKey: '',
    header: 'Branch',
    cell: ({ row }) => {
      const branchName = row.original.branch?.name;
      return h('div', { class: 'capitalize' }, branchName);
    },
  },
  {
    accessorKey: '',
    header: 'Assigned Table',
    cell: ({ row }) => h(DeviceUnassignedBadge, { row: row.original }),
  },
  {
    accessorKey: 'is_active',
    header: 'Status',
    cell: ({ row }) => {
        const isActive = row.original.is_active;
        return h(Circle, { class: isActive ? 'text-woosoo-green' : 'text-red-500' })
    } 
  },
  {
    accessorKey: 'id',
    header: 'Actions',
    cell: ({ row }) => {
        return h( DeviceEdit, { device: row.original })
    },
    }
];

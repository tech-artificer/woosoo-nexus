import type { ColumnDef } from '@tanstack/vue-table'
import type { DeviceOrder } from '@/types/models';
import { h } from 'vue'
import { Checkbox } from '@/components/ui/checkbox'
import DataTableColumnHeader from '@/components/Orders/DataTableColumnHeader.vue'
import DataTableRowActions from '@/components/Orders/DataTableRowActions.vue'

export const columns: ColumnDef<DeviceOrder, any>[] = [
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
    accessorKey: 'order_number',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Order #', class: 'w-[100px]' }),

    cell: ({ row }) => {
      return h('div', { class: 'flex space-x-2' }, [
        h('span', { class: ' font-medium' }, row.getValue('order_number')),
      ])
    },
  },
  {
    accessorKey: 'device',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Device', class: 'max-w-[100px]' }),
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) => {
      const deviceName = row.original.device?.name;
      return h('div', { class: 'w-20 flex space-x-2' }, [
        h('span', { class: ' font-medium' }, deviceName),
      ])
    }
  },  
  {
    accessorKey: 'table',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Table', class: 'max-w-[200px]' }),
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) => {
      const tableName = row.original.table?.name;
      return h('div', { class: 'w-20 flex space-x-2' }, [
        h('span', { class: ' font-medium' }, tableName),
      ])
    }
  },  
  {
    accessorKey: 'guest_count',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Guests', class: 'max-w-[200px]' }),
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) => {
      return h('div', { class: 'w-20 flex space-x-2' }, [
        h('span', { class: ' font-medium' }, row.getValue('guest_count')),
      ])
    }
  },  
  {
    accessorKey: 'total_amount',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Total', class: 'max-w-[200px]' }),
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) => {
      return h('div', { class: 'w-20 flex space-x-2' }, [
        h('span', { class: ' font-medium' }, row.getValue('total_amount')),
      ])
    }
  }, 
  {
    accessorKey: 'status',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Status', class: 'max-w-[200px]' }),
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) => {
      return h('div', { class: 'w-20 flex space-x-2' }, [
        h('span', { class: ' font-medium' }, row.getValue('status')),
      ])
    }
  },  
  {
    accessorKey: 'id',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: '' }),
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) => {

      // if( user.role as any == 'Owner' || user.role as any == 'Admin' ) {
      //   return h('div', { row })
      // }
      return h(DataTableRowActions, { row })
    }
  },
]

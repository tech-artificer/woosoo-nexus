import type { ColumnDef } from '@tanstack/vue-table'
import type { DeviceOrder } from '@/types/models';
import { h } from 'vue'
import { Checkbox } from '@/components/ui/checkbox'
import DataTableColumnHeader from '@/components/Orders/DataTableColumnHeader.vue'
import DataTableRowActions from '@/components/Orders/DataTableRowActions.vue'
import OrderStatusBadge from '@/components/Orders/OrderStatusBadge.vue'
import { formatCurrency } from '@/lib/utils';

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
      const isRefill = row.original && (row.original.__is_refill === true || (Array.isArray(row.original.items) && row.original.items.some((it: any) => it.is_refill || (it.name && String(it.name).toLowerCase().includes('refill')))) )
      return h('div', { class: 'flex items-center space-x-2' }, [
        h('span', { class: ' font-medium' }, row.getValue('order_number')),
        isRefill ? h('span', { class: 'text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full' }, 'Refill') : null,
      ])
    },
  },
  {
    accessorKey: 'device',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Device', class: 'max-w-[100px]' }),
    enableColumnFilter: true,
    enableSorting: false,
    filterFn: (row, columnId, filterValue) => {
      if (!filterValue?.length) return true
      const deviceName = row.original.device?.name
      return filterValue.includes(deviceName)
    },
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
    enableColumnFilter: true,
    enableSorting: false,
    filterFn: (row, columnId, filterValue) => {
      if (!filterValue?.length) return true
      const tableName = row.original.table?.name
      return filterValue.includes(tableName)
    },
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
      
      const total = row.original.total || 0;
      return h('div', { class: 'w-20 flex space-x-2' }, [
        h('span', { class: ' font-medium' }, formatCurrency(total) ),
      ])
    }
  }, 
  {
    accessorKey: 'status',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Status', class: 'max-w-[140px]' }),
    enableColumnFilter: true,
    enableSorting: true,
    filterFn: (row, columnId, filterValue) => {
      if (!filterValue?.length) return true
      return filterValue.includes(row.getValue(columnId))
    },
    cell: ({ row }) => {
      return h(OrderStatusBadge, { status: row.getValue('status') as string })
    }
  },  
  {
    accessorKey: 'created_at',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Created', class: 'max-w-[140px]' }),
    enableColumnFilter: true,
    enableSorting: false,
    filterFn: (row: any, columnId: string, filterValue: any) => {
      if (!filterValue) return true
      // expected filterValue: 'from|to'
      const [from, to] = String(filterValue).split('|')
      const created = new Date(row.getValue(columnId))
      if (from) {
        const f = new Date(from)
        if (created < f) return false
      }
      if (to) {
        const t = new Date(to)
        t.setHours(23,59,59,999)
        if (created > t) return false
      }
      return true
    },
    cell: ({ row }) => {
      const d = row.original.created_at ? new Date(row.original.created_at).toLocaleString() : ''
      return h('div', { class: 'w-32' }, [ h('span', { class: 'text-sm' }, d) ])
    }
  },
  {
    accessorKey: 'is_printed',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Printed', class: 'max-w-[200px]' }),
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) => {
      return h('div', { class: 'w-20 flex space-x-2' }, [
        h('span', { class: ' font-medium' }, row.getValue('is_printed') ? 'Yes' : 'No'),
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

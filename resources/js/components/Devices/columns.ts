import type { ColumnDef } from '@tanstack/vue-table'
import type { Device } from '@/types/models';
import { h } from 'vue'
import { Checkbox } from '@/components/ui/checkbox'
import DataTableColumnHeader from '@/components/Devices/DataTableColumnHeader.vue'
import DataTableRowActions from '@/components/Devices/DataTableRowActions.vue'

export const columns: ColumnDef<Device, any>[] = [
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
        h('span', { class: ' font-medium' }, row.getValue('name')),
      ])
    },
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
    accessorKey: 'ip_address',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'IP', class: 'max-w-[200px]' }),
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) => {
      return h('div', { class: 'w-20 flex space-x-2' }, [
        h('span', { class: ' font-medium' }, row.getValue('ip_address')),
      ])
    }
  },  
  {
    accessorKey: 'last_ip_address',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Last IP', class: 'max-w-[200px]' }),
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) => {
      return h('div', { class: 'w-20 flex space-x-2' }, [
        h('span', { class: ' font-medium' }, row.getValue('last_ip_address')),
      ])
    }
  }, 
  {
    accessorKey: 'last_seen_at',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Last Seen', class: 'max-w-[200px]' }),
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) => {
      return h('div', { class: 'w-20 flex space-x-2' }, [
        h('span', { class: ' font-medium' }, row.getValue('last_seen_at')),
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

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
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Name', class: 'w-[100px]' }),
    filterFn: (row, columnId, filterValue) => {
      const query = String(filterValue ?? '').trim().toLowerCase()
      if (!query) return true

      const name = String(row.getValue(columnId) ?? '').toLowerCase()
      const ipAddress = String(row.original.ip_address ?? '').toLowerCase()

      return name.includes(query) || ipAddress.includes(query)
    },

    cell: ({ row }) => {
      const isDeactivated = Boolean(row.original.deleted_at)
      return h('div', { class: 'flex space-x-2' }, [
        h('span', { class: ' font-medium capitalize' }, row.getValue('name')),
        isDeactivated
          ? h(
              'span',
              {
                class: 'inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800',
              },
              'Deactivated'
            )
          : null,
      ])
    },
  },
  {
    accessorKey: 'table',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Table', class: 'max-w-[100px]' }),
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
    accessorKey: 'port',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Port', class: 'max-w-[200px]' }),
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) => {
      return h('div', { class: 'w-20 flex space-x-2' }, [
        h('span', { class: ' font-medium' }, row.getValue('port')),
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
        h('span', { class: ' font-medium truncate' }, row.getValue('last_ip_address')),
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
        h('span', { class: ' font-medium truncate' }, row.getValue('last_seen_at')),
      ])
    }
  }, 
  {
    id: 'security_status',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Security', class: 'max-w-[200px]' }),
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) => {
      const hasSecurityCode = Boolean(row.original.security_code_generated_at)
      return h('div', { class: 'w-20 flex space-x-2' }, [
        h(
          'span',
          {
            class: hasSecurityCode
              ? 'inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700'
              : 'inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700',
          },
          hasSecurityCode ? 'Set' : 'Not Set'
        ),
      ])
    }
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

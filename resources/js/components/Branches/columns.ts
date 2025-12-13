import { h } from 'vue'
import { ColumnDef } from '@tanstack/vue-table'
import { Checkbox } from '@/components/ui/checkbox'
import DataTableColumnHeader from './DataTableColumnHeader.vue'
import DataTableRowActions from './DataTableRowActions.vue'
import { Badge } from '@/components/ui/badge'

export interface Branch {
    id: number
    branch_uuid: string
    name: string
    location: string | null
    devices_count?: number
    users_count?: number
    created_at: string
    updated_at: string
    deleted_at: string | null
}

export const columns: ColumnDef<Branch>[] = [
    {
        id: 'select',
        header: ({ table }) => h(Checkbox, {
            'checked': table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate'),
            'onUpdate:checked': (value: boolean) => table.toggleAllPageRowsSelected(!!value),
            'ariaLabel': 'Select all',
        }),
        cell: ({ row }) => h(Checkbox, {
            'checked': row.getIsSelected(),
            'onUpdate:checked': (value: boolean) => row.toggleSelected(!!value),
            'ariaLabel': 'Select row',
        }),
        enableSorting: false,
        enableHiding: false,
    },
    {
        accessorKey: 'name',
        header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Name' }),
        cell: ({ row }) => {
            const isDeleted = row.original.deleted_at
            return h('div', { class: 'flex items-center gap-2' }, [
                h('span', { class: 'font-medium' }, row.getValue('name')),
                isDeleted ? h(Badge, { variant: 'secondary', class: 'text-xs' }, () => 'Inactive') : null
            ])
        },
        enableSorting: true,
        enableHiding: false,
    },
    {
        accessorKey: 'location',
        header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Location' }),
        cell: ({ row }) => h('div', { class: 'max-w-[300px] truncate' }, row.getValue('location') || '—'),
    },
    {
        accessorKey: 'devices_count',
        header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Devices' }),
        cell: ({ row }) => h('div', { class: 'text-center' }, row.getValue('devices_count') || 0),
    },
    {
        accessorKey: 'users_count',
        header: ({ column }) => h(DataTableColumnHeader, { column, title: 'Users' }),
        cell: ({ row }) => h('div', { class: 'text-center' }, row.getValue('users_count') || 0),
    },
    {
        id: 'actions',
        cell: ({ row }) => h(DataTableRowActions, { row }),
    },
]

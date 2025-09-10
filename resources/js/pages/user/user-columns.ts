import { h } from 'vue';
import { ColumnDef } from '@tanstack/vue-table';
import type { User } from '@/types/models';
import { ShieldPlus } from 'lucide-vue-next';
import ActionNav from '@/pages/user/Actions.vue'

export const getUserColumns = (): ColumnDef<User>[] => [
    {
        accessorKey: 'name',  
        header: 'Name',
        cell: ({ row }) => {
           return h( 'div', { class: '' }, row.original.name ?? 'N/A')
        },          
    },
    {
        accessorKey: 'email',
        header: 'Email',
        cell: ({ row }) => {
            return h( 'div', { class: '' }, row.getValue('email'))
        },
    },
    {
        accessorKey: 'id',
        header: 'Role',
        cell: ({ row }) => {

            if (!row.original.roles?.length) {
                return h( ActionNav, { class: '' }, 'N/A')
            }

            return h( 'div', { class: '' }, row.original.roles?.map(role => role.name).join(', '))
        },
    },
]   
import { h } from 'vue';
import { ColumnDef } from '@tanstack/vue-table';
import { Branch } from '@/types/models';


export const getBranchColumns = (): ColumnDef<Branch>[] => [
    {
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => {
            return h('div', { class: 'capitalize' }, row.getValue('name'));
        },
    }, 

]
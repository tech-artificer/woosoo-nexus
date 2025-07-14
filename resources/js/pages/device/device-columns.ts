import { h } from 'vue';
import { ColumnDef } from '@tanstack/vue-table';
import { Device } from '@/types/models';

export const devicecolumns: ColumnDef<Device>[] = [
    {
        accessorKey: 'name',  
        header: 'Device',
        cell: ({ row }) => {
            return h( 'div', { class: 'capitalize' }, row.getValue('name'))
        },          
    },
    {
        accessorKey: '',
        header: 'Branch',
        cell: ({ row }) => {
            const branchName = row.original.branch?.name;
            return h( 'div', { class: 'capitalize' },  `${branchName}`);
        },
    },
    {
        accessorKey: '',  
        header: 'Assigned Table',
        cell: ({ row }) => {
            const tableName = row.original.table?.name;
            return h( 'div', { class: 'capitalize' },  `${tableName}`);
        },          
    },
    // {
    //     accessorKey: '',  
    //     header: 'Registration Code',
    //     cell: ({ row }) => {
    //         const deviceCode = row.original.registration_code?.code ?? row.original.registrationCode?.code;
    //         return h( 'div', { class: 'capitalize' },  `${deviceCode}`);
    //     },          
    // },
    
    // {
    //     accessorKey: 'id',
    //     header: 'Status',
    //     cell: ({ row }) => {
    //         let status = row.original.deviceOrder?.status
           
    //         return h(OrderStatusBadge, { status: status })
    //     },
    // },
]
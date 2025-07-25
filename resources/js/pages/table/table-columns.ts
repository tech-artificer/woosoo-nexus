import { h } from 'vue';
import { ColumnDef } from '@tanstack/vue-table';
import { Table } from '@/types/models';

export const getTableColumns = (): ColumnDef<Table>[] => [
    {
        accessorKey: 'id',  
        header: 'name',
        cell: ({ row }) => {
            return h( 'div', { class: 'capitalize' }, row.original.name ?? 'N/A')
        },          
    },
    {
        accessorKey: 'id',
        header: 'Available',
        cell: ({ row }) => {
            const is_available = row.original.is_available;
            return h( 'div', { class: 'capitalize' },  is_available);
        },
    },
//     {
//         accessorKey: 'id',  
//         header: 'Locked',
//         cell: ({ row }) => {
//             return h( 'div', { class: 'capitalize' }, row.original.device?.name ?? 'N/A')
//         },          
//     },
//     {
//         accessorKey: 'id',
//         header: 'Table',
//         cell: ({ row }) => {
//             return h( 'div', { class: 'capitalize' }, row.original.table?.name ?? 'N/A')
//         },
//     },
//     {
//         accessorKey: 'guest_count',
//         header: 'Guest',
//         cell: ({ row }) => {
//             return h( 'div', { class: 'capitalize' }, row.original.order?.guest_count ?? 'N/A')
//         },
//     },
//    {
//         // Use an accessor function to extract the nested value for sorting/filtering
//         accessorKey: '',
//         header: 'Total',
//         // The cell function now just receives the extracted value from accessorFn
//         cell: ({ row }) => {
//             return h(
//                 'div',
//                 { class: 'capitalize flex items-center' }, // Added 'items-center' for alignment
//                 '₱ ' + 0.00
//             );  
//         },
//     },
//     {
//         accessorKey: 'id',
//         header: 'Status',
//         cell: ({ row }) => {
//             let status = row.original.status
           
//             return h(OrderStatusBadge, { status: status })
//         },
//     },
]
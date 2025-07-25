import { h } from 'vue';
import { ColumnDef } from '@tanstack/vue-table';
import { DeviceOrder } from '@/types/models';
import  OrderStatusBadge from '@/pages/order/OrderStatusBadge.vue'

export const getOrderColumns = (): ColumnDef<DeviceOrder>[] => [
    {
        accessorKey: 'id',  
        header: 'Date',
        cell: ({ row }) => {
            return h( 'div', { class: 'capitalize' }, row.original.order?.date_time_opened ?? 'N/A')
        },          
    },
    {
        accessorKey: 'id',
        header: 'Order Number | ID',
        cell: ({ row }) => {
            const orderNumber = row.original.order_number ?? 'N/A';
            return h( 'div', { class: 'capitalize' },  `${orderNumber} | ${row.original.id}`);
        },
    },
    {
        accessorKey: 'id',  
        header: 'Device',
        cell: ({ row }) => {
            console.log(row.original);
            return h( 'div', { class: 'capitalize' }, row.original.device?.name ?? 'N/A')
        },          
    },
    {
        accessorKey: 'id',
        header: 'Table',
        cell: ({ row }) => {
            return h( 'div', { class: 'capitalize' }, row.original.table?.name ?? 'N/A')
        },
    },
    {
        accessorKey: 'guest_count',
        header: 'Guest',
        cell: ({ row }) => {
            return h( 'div', { class: 'capitalize' }, row.original.order?.guest_count ?? 'N/A')
        },
    },
   {
        // Use an accessor function to extract the nested value for sorting/filtering
        accessorKey: '',
        header: 'Total',
        // The cell function now just receives the extracted value from accessorFn
        cell: ({ row }) => {
            return h(
                'div',
                { class: 'capitalize flex items-center' }, // Added 'items-center' for alignment
                '₱ ' + 0.00
            );  
        },
    },
    {
        accessorKey: 'id',
        header: 'Status',
        cell: ({ row }) => {
            let status = row.original.status
           
            return h(OrderStatusBadge, { status: status })
        },
    },
]
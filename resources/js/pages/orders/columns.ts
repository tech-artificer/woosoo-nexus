import { h } from 'vue';
import { ColumnDef } from '@tanstack/vue-table';
import { Order } from '@/types/models';
import { OrderStatus } from '@/types/enums';
import  OrderStatusBadge from '@/pages/orders/OrderStatusBadge.vue'

export const ordercolumns: ColumnDef<Order>[] = [
    {
        accessorKey: 'date_time_opened',  
        header: 'Date',
        cell: ({ row }) => {
            return h( 'div', { class: 'capitalize' }, row.getValue('date_time_opened'))
        },          
    },
    {
        accessorKey: 'id',
        header: 'Order Number',
        cell: ({ row }) => {
            return h( 'div', { class: 'capitalize' }, row.original.deviceOrder?.order_number ?? 'N/A')
        },
    },
    {
        accessorKey: 'id',  
        header: 'Device',
        cell: ({ row }) => {
            return h( 'div', { class: 'capitalize' }, row.original.device?.name ?? 'N/A')
        },          
    },
    {
        accessorKey: 'id',
        header: 'Assigned Table',
        cell: ({ row }) => {
            return h( 'div', { class: 'capitalize' }, row.original.table?.name ?? 'N/A')
        },
    },
    {
        accessorKey: 'guest_count',
        header: 'Guest',
        cell: ({ row }) => {
            return h( 'div', { class: 'capitalize' }, row.getValue('guest_count'))
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
                '₱ ' +  row.original.order_check?.total_amount
            );  
        },
    },
    {
        accessorKey: 'id',
        header: 'Status',
        cell: ({ row }) => {
            let status = row.original.deviceOrder?.status
           
            return h(OrderStatusBadge, { status: status })
        },
    },
]
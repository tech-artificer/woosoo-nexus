import { h } from 'vue';
import { ColumnDef } from '@tanstack/vue-table';
import { DeviceOrder } from '@/types/models';
import  OrderStatusBadge from '@/pages/order/OrderStatusBadge.vue'
import ServiceRequestIcons from '@/pages/order/ServiceRequestIcons.vue';

export const getOrderColumns = (): ColumnDef<DeviceOrder>[] => [
    // {
    //     accessorKey: 'id',  
    //     header: 'Date',
    //     cell: ({ row }) => {
    //         return h( 'div', { class: 'capitalize' }, row.original.order?.date_time_opened ?? 'N/A')
    //     },          
    // },
    {
        accessorKey: '',  
        header: 'Device',
        cell: ({ row }) => {
            return h( 'div', { class: 'capitalize' }, row.original.device?.name ?? 'N/A')
        },          
    },
    {
        accessorKey: '',
        header: 'Table',
        cell: ({ row }) => {
            return h( 'div', { class: 'font-semibold' }, row.original.table?.name ?? 'N/A')
        },
    },
    {
        accessorKey: '',
        header: 'Order Number',
        cell: ({ row }) => {
            const orderNumber = row.original.order_number ?? '';
            // const orderId = row.original.order?.id ?? '';
            return h( 'div', { class: 'capitalize' },  `${orderNumber}`); //| ${row.original.id}
        },
    },
    // {
    //     accessorKey: 'package',
    //     header: 'Package',
    //     cell: ({ row }) => {
    //         const setMeal = row.original.items[0];
    //         // console.log(JSON.stringify(row.original.items[0]?.receipt_name));
    //         return h( 'div', { class: 'capitalize' },  `${setMeal?.receipt_name} `); //| ${row.original.id}
    //     },
    // },
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
            const total = row.original.meta.order_check.total_amount;
            return h(
                'div',
                { class: 'capitalize flex items-center' }, // Added 'items-center' for alignment
                '₱ ' + total.toLocaleString('locale', { minimumFractionDigits: 2 })
            );  
        },
    },
    {
        accessorKey: 'id',
        header: 'Status',
        cell: ({ row }) => {
            const status = row.original.status
           
            return h(OrderStatusBadge, { status: status })
        },
    },
    {
        accessorKey: '',
        header: 'Service Request',
        cell: () => {

            // createIcons({ icons });
          
            // const serviceRequests = row.original.service_requests.map((serviceRequest) => serviceRequest.table_service_name);
            //  serviceRequests.join(', ')
            return h(ServiceRequestIcons, { request: 'clean' })
            // return <Bubbles></Bubbles>
        },
    },
]   
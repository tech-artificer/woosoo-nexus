import { h } from 'vue';
import { ColumnDef } from '@tanstack/vue-table';
import { DeviceOrder } from '@/types/models';
import OrderStatusBadge from '@/pages/order/OrderStatusBadge.vue'
// import ServiceRequestIcons from '@/pages/order/ServiceRequestIcons.vue';
// import OrderCompleteAction from '@/pages/order/actions/Complete.vue';
import OrderActions from '@/pages/order/actions/Index.vue';

export const getOrderColumns = (): ColumnDef<DeviceOrder>[] => [
    
    {
        accessorKey: '',  
        header: 'Order #',
        cell: ({ row }) => {
             const orderNumber = row.original.order?.id ?? '';
    //         // const orderId = row.original.order?.id ?? '';
            return h('div', { class: 'capitalize' }, `${orderNumber}`); //| ${row.original.id}
        },          
    },
    {
        accessorKey: '',
        header: 'Device',
        cell: ({ row }) => {
            return h('div', { class: 'capitalize' }, row.original.device?.name ?? 'N/A')
        },
    },
    {
        accessorKey: '',
        header: 'Table',
        cell: ({ row }) => {
            return h('div', { class: 'capitalize' }, row.original.table?.name ?? 'N/A')
        },
    },
    {
        accessorKey: '',
        header: 'Time',
        cell: ({ row }) => {
            const orderNumber = row.original.order_number ?? '';
            // const orderId = row.original.order?.id ?? '';
            return h('div', { class: 'capitalize' }, `${orderNumber}`); //| ${row.original.id}
        },
    },
    {
        accessorKey: 'id',
        header: () => h('div', { class: 'text-right' }, 'Guest'),
        cell: ({ row }) => {
            console.log(row.original);
            return h('div', { class: 'capitalize text-right' }, row.original.order?.guest_count);
        },
    },
    {
        accessorKey: 'id',
        header: () => h('div', { class: 'text-right' }, 'Items'),
        cell: ({ row }) => {
            return h('div', { class: 'capitalize text-right' }, row.original.items.length);
        },
    },
    {
        // Use an accessor function to extract the nested value for sorting/filtering
        accessorKey: 'id',
        header: () => h('div', { class: 'text-right' }, 'Total'),
        // The cell function now just receives the extracted value from accessorFn
        cell: ({ row }) => {
            const total = row.original.meta.order_check.total_amount;
            return h(
                'div',
                { class: 'capitalize text-right' }, // Added 'items-center' for alignment
                '₱ ' + total.toLocaleString('locale', { minimumFractionDigits: 2 })
            );
        },
    },
    {
        accessorKey: 'id',
        header: () => h('div', { class: 'text-center' }, 'Status'),
        cell: ({ row }) => {
            const status = row.original.status

            return h('div', { class: 'text-center' }, [
                h(OrderStatusBadge, { status: status })
            ]);
        },
    },
    // {
    //     accessorKey: '',
    //     header: 'Service Request',
    //     cell: () => {

    //         // createIcons({ icons });

    //         // const serviceRequests = row.original.service_requests.map((serviceRequest) => serviceRequest.table_service_name);
    //         //  serviceRequests.join(', ')
    //         return h(ServiceRequestIcons, { request: 'clean' })
    //         // return <Bubbles></Bubbles>
    //     },
    // },
    {
        accessorKey: 'id',
        header: ' ',
        cell: ({ row }) => {

            return h('div', { class: 'text-right' }, [
                h(OrderActions, { row: row.original })
            ]);
          
        },
    },
]   
import { h } from 'vue';
import { ColumnDef } from '@tanstack/vue-table';
import { Menu } from '@/types/models';
import MenuDisplayName from '@/pages/menus/DisplayMenuName.vue';
import EditMenu from '@/pages/menus/EditMenu.vue';


export const menucolumns: ColumnDef<Menu>[] = [
    {
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => {
            return h(MenuDisplayName, { menu: row.original });
        },
    },  {
        accessorKey: 'category',
        header: 'Category',
        cell: ({ row }) => h('div', { class: 'capitalize' }, row.original.category?.name ?? 'N/A'),
    },  {
        accessorKey: 'group',
        header: 'Group',
        cell: ({ row }) => h('div', { class: 'capitalize' }, row.original.group?.name ?? 'N/A'),
    },{
        accessorKey: 'course',
        header: 'Course',
        cell: ({ row }) => h('div', { class: 'capitalize' }, row.original.course?.name ?? 'N/A'),
    },{
        accessorKey: 'price',
        header: 'Price',
        cell: ({ row }) => h('div', { class: '' }, row.getValue('price')),
    },{
        accessorKey: 'id',
        header: 'Actions',
        cell: ({ row }) => {
            return h( EditMenu, { menu: row.original })
        },
    }
  
  
]
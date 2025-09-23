import { h } from 'vue';
import { ColumnDef } from '@tanstack/vue-table';
import { Menu } from '@/types/models';
import MenuDisplayName from '@/pages/menu/DisplayMenuName.vue';
import MenuEditDialog from '@/pages/menu/Edit.vue';
import AppIconExp from '@/components/AppIconExp.vue';

export const getMenuColumns = (): ColumnDef<Menu>[] => [
    {
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => {
            return h(MenuDisplayName, { menu: row.original });
        },
    }, 
    {
        // Use an accessor function to extract the nested value for sorting/filtering
        accessorKey: 'receipt_name',
        header: 'Receipt Name',
        // The cell function now just receives the extracted value from accessorFn
         cell: ({ row }) => h('div', { class: '' }, row.getValue('receipt_name')),
    },
    // {
    //     accessorKey: 'category',
    //     header: 'Category',
    //     cell: ({ row }) => h('div', { class: 'capitalize' }, row.original.category ?? ''),
    // }, {
    //     accessorKey: 'group',
    //     header: 'Group',
    //     cell: ({ row }) => h('div', { class: 'capitalize' }, row.original.group ?? ''),
    // }, {
    //     accessorKey: 'course',
    //     header: 'Course',
    //     cell: ({ row }) => h('div', { class: 'capitalize' }, row.original.course ?? ''),
    // }, 
    {
        accessorKey: 'price',
        header: 'Price',
        cell: ({ row }) => h('div', { class: '' }, row.getValue('price')),
    }, 
    {
        accessorKey: '',
        header: 'Modifier',
        cell: ({ row }) => {
            return h(AppIconExp, { class: 'size-4 text-green-500 align-self-center', isTrue: row.original.is_modifier });
        }
    },
    {
        // Use an accessor function to extract the nested value for sorting/filtering
        accessorKey: '',
        header: 'Available',
        // The cell function now just receives the extracted value from accessorFn
        cell: ({ row }) => {
            return h(AppIconExp, { class: 'size-4 text-green-500 align-self-center', isTrue: row.original.is_available });
        },
    },
    {
        // Use an accessor function to extract the nested value for sorting/filtering
        accessorKey: 'is_taxable',
        header: 'Taxable',
        // The cell function now just receives the extracted value from accessorFn
        cell: ({ row }) => {
            return h(AppIconExp, { class: 'size-4 text-green-500 align-self-center', isTrue: row.original.is_taxable });
        },
    },
    {
        accessorKey: 'id',
        header: 'Actions',
        cell: ({ row }) => {
            const menu = row.original
            return h(MenuEditDialog, {
                menu
            })
        },
    },
    // ,{
    //    // Use an accessor function to extract the nested value for sorting/filtering
    //     accessorKey: 'img_url',
    //     header: 'Featured Image',
    //     // The cell function now just receives the extracted value from accessorFn
    //     cell: ({ row }) => h('div', { class: '' }, row.getValue('img_url')),
    // }



]
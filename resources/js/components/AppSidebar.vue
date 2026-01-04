<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import NavMain from '@/components/NavMain.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    ListOrdered,
    UserCog,
    MonitorSmartphone,
    UtensilsCrossed,
    TrendingUp,
    Split,
    Lock,
    BookOpen,
    BarChart3
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';


const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: route('dashboard'),
        isActive: false,
        hasSubItems: false,
        icon: LayoutDashboard,
    },
    {
        title: 'Orders',
        href: '/orders',
        icon: ListOrdered,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Menus',
        href: '/menus',
        icon: UtensilsCrossed,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'User Management',
        href: '/users',
        icon: UserCog,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Devices',
        href: '/devices',
        icon: MonitorSmartphone,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Manual',
        href: '/manual',
        icon: BookOpen,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Reports',
        href: '#',
        icon: BarChart3,
        isActive: false,
        hasSubItems: true,
        subItems: [
            { title: 'Daily Sales', href: route('reports.daily-sales') },
            { title: 'Menu Items', href: route('reports.menu-items') },
            { title: 'Hourly Sales', href: route('reports.hourly-sales') },
            { title: 'Guest Count', href: route('reports.guest-count') },
            { title: 'Print Audit', href: route('reports.print-audit') },
            { title: 'Order Status', href: route('reports.order-status') },
            { title: 'Discount & Tax', href: route('reports.discount-tax') },
        ],
    },

];


const configNavItems: NavItem[] = [
    {
        title: 'Roles & Permissions',
        href: route('roles.index'),
        icon: Lock,
        isActive: false,
        hasSubItems: false,
    },
];

</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" title="Main" />
        </SidebarContent>
        <SidebarFooter>
            <NavMain :items="configNavItems" title="Configuration" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

<script setup lang="ts">
// import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutDashboard, ListOrdered, UserCog, MonitorSmartphone, UtensilsCrossed, Table } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutDashboard,
    },
    {
        title: 'Orders',
        href: '/orders',
        icon: ListOrdered,
        items: [
            {
                title: 'Live Orders',
                href: '/orders/live',
            },
            {
                title: 'KDS',
                href: '/orders/kds',
            },
        ]
    },
    {
        title: 'Menu Management',
        href: '/menus',
        icon: UtensilsCrossed,
    },
    {
        title: 'Users',
        href: '/users',
        icon: UserCog,
    },
    {
        title: 'Device Management',
        href: '/devices',
        icon: MonitorSmartphone,
        items: [
            {
                title: 'Devices',
                href: '/devices',
            },
            {
                title: 'Assign Tables',
                href: '/devices/assign-tables',
            },
        ]
    },
    {
        title: 'Settings',
        href: '/settings',
        icon: ListOrdered,
        items: [
            {
                title: 'Users',
                href: '/users',
            },
            {
                title: 'Roles & Permissions',
                href: '/roles-permissions',
            },
            {
                title: 'Terminal Session (POS)',
                href: '/terminal-session',
            },
        ]
    },

    
];

// const footerNavItems: NavItem[] = [
//     // {
//     //     title: 'Github Repo',
//     //     href: 'https://github.com/laravel/vue-starter-kit',
//     //     icon: Folder,
//     // },
//     // {
//     //     title: 'Documentation',
//     //     href: 'https://laravel.com/docs/starter-kits#vue',
//     //     icon: BookOpen,
//     // },
// ];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <!-- <NavFooter :items="footerNavItems" /> -->
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

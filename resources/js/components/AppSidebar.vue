<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Circle,LayoutDashboard, ListOrdered, UserCog, MonitorSmartphone, UtensilsCrossed, Fingerprint, Terminal, LockOpen, EllipsisVertical } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import type { LucideIcon } from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3'

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        isActive: false,
        hasSubItems: false,
        icon: LayoutDashboard,
    },
    {
        title: 'Orders',
        href: '/orders',
        icon: ListOrdered,
         isActive: false,
        hasSubItems: true,
        items: [
            {
                title: 'Live Orders',
                href: '/orders/live',
                icon: EllipsisVertical,
                isActive: false,
            },
            {
                title: 'Table Orders',
                href: '/orders/table-orders',
                icon: EllipsisVertical,
                isActive: false,
        
            },
        ]
    },
    {
        title: 'Menu Management',
        href: '/menus',
        icon: UtensilsCrossed,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Users',
        href: '/users',
        icon: UserCog,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Device Management',
        href: '/devices',
        icon: MonitorSmartphone,
        isActive: false,
        hasSubItems: false,
        // items: [
        //     {
        //         title: 'Devices',
        //         href: '/devices',
        //         icon: EllipsisVertical,
        //         isActive: false,
        //     },
        // ]
    },
    {
        title: 'Settings',
        href: '/settings',
        icon: ListOrdered,
        isActive: false,
        hasSubItems: false,
        items: [
            {
                title: 'Users',
                href: '/users',
                icon: EllipsisVertical,
                isActive: false,
            },
            {
                title: 'Roles & Permissions',
                href: '/roles-permissions',
                icon: EllipsisVertical,
                isActive: false,
            },
            {
                title: 'Terminal Session (POS)',
                href: '/terminal-session',
                icon: EllipsisVertical,
                isActive: false,
            },
        ]
    },

    
];

const page = usePage();
const session = page.props.session as { id?: any } || {};
const terminalSession = page.props.terminalSession as { id?: any } || {};
const employeeLogs = page.props.employeeLog as { id?: any } || {};

interface ActiveSession {
  id?: any;
  title?: string;
  icon?: LucideIcon;
}

const footerActiveSessions: ActiveSession[] = [
    // {
    //     title: 'Github Repo',
    //     href: 'https://github.com/laravel/vue-starter-kit',
    //     icon: Circle,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits#vue',
    //     icon: Circle,
    // },
    {
        id: session?.id,
        title: 'Session #',
        icon: LockOpen,
    },
    {
        id: terminalSession?.id,
        title: 'Terminal Session #',
        icon: Terminal
    },
    {
        id: employeeLogs?.id,
        title: 'Logs #',
        icon: Fingerprint,
    },
    
];
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
            <NavFooter :items="footerActiveSessions" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

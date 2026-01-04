<script setup lang="ts">
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

const hasZiggyRoute = (name: string) => {
    const routes = (typeof window !== 'undefined' ? (window as any)?.Ziggy?.routes : undefined) ?? {};
    return Object.prototype.hasOwnProperty.call(routes, name);
};

const routeOrFallback = (name: string, fallback: string) => {
    if (typeof route !== 'function') return fallback;
    if (!hasZiggyRoute(name)) return fallback;
    try {
        return route(name);
    } catch (error) {
        // Keep the sidebar functional even when Ziggy’s route list is stale.
        console.warn(`Ziggy route missing: ${name}`, error);
        return fallback;
    }
};


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
            { title: 'Daily Sales', href: routeOrFallback('reports.daily-sales', '/reports/daily-sales') },
            { title: 'Menu Items', href: routeOrFallback('reports.menu-items', '/reports/menu-items') },
            { title: 'Hourly Sales', href: routeOrFallback('reports.hourly-sales', '/reports/hourly-sales') },
            { title: 'Guest Count', href: routeOrFallback('reports.guest-count', '/reports/guest-count') },
            { title: 'Print Audit', href: routeOrFallback('reports.print-audit', '/reports/print-audit') },
            { title: 'Order Status', href: routeOrFallback('reports.order-status', '/reports/order-status') },
            { title: 'Discount & Tax', href: routeOrFallback('reports.discount-tax', '/reports/discount-tax') },
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

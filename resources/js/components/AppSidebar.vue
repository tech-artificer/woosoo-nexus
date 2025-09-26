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
    Lock 
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
    
];


const configNavItems: NavItem[] = [
    {
        title: 'Roles & Permissions',
        href: '/roles/permissions',
        icon: Lock,
        isActive: false,
        hasSubItems: false,
    }, 
];

// const reportNavItems: NavItem[] = [
//     {
//         title: 'Sales Report',
//         href: route('reports.sales'),
//         icon: TrendingUp,
//         isActive: false,
//         hasSubItems: false,
//     }, 
// ];

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
            <NavMain :items="mainNavItems" title="Main"  />
            <!-- <NavMain :items="reportNavItems" title="Reports" /> -->
        </SidebarContent>
        <SidebarFooter>
            <NavMain :items="configNavItems" title="Configuration" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

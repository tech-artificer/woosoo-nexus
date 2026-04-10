<!-- Audit Fix (2026-04-06): expose package management page in admin sidebar. -->
<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import { computed } from 'vue'
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { 
    LayoutDashboard, 
    ListOrdered, 
    UserCog, 
    MonitorSmartphone, 
    UtensilsCrossed,  
    Lock,
    ShieldCheck,
    Bell,
    Building2,
    Accessibility,
    FileText,
    Activity,
    Package,
    TrendingUp,
    BarChart2,
    Users,
    Clock,
    Printer,
    Tag,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const page = usePage()
const user = computed(() => (page.props.auth as any)?.user)
const isAdmin = computed(() => Boolean(user.value?.is_admin))

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
        href: route('orders.index'),
        icon: ListOrdered,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Menus',
        href: route('menus'),
        icon: UtensilsCrossed,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Packages',
        href: route('packages.index'),
        icon: Package,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'User Management',
        href: route('users.index'),
        icon: UserCog,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Devices',
        href: route('devices.index'),
        icon: MonitorSmartphone,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Service Requests',
        href: route('service-requests.index'),
        icon: Bell,
        isActive: false,
        hasSubItems: false,
    },
    
];


const configNavItems: NavItem[] = [
    {
        title: 'Branches',
        href: route('branches.index'),
        icon: Building2,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Roles',
        href: route('roles.index'),
        icon: Lock,
        isActive: false,
        hasSubItems: false,
    }, 
    {
        title: 'Permission Registry',
        href: route('permissions.index'),
        icon: ShieldCheck,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Accessibility',
        href: route('accessibility.index'),
        icon: Accessibility,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Event Logs',
        href: route('event-logs.index'),
        icon: FileText,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Reverb Service',
        href: route('reverb.index'),
        icon: Activity,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'System Monitoring',
        href: route('monitoring.index'),
        icon: MonitorSmartphone,
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

const reportNavItems: NavItem[] = [
    {
        title: 'Reports Hub',
        href: route('reports.index'),
        icon: TrendingUp,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Daily Sales',
        href: route('reports.daily-sales'),
        icon: BarChart2,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Menu Items',
        href: route('reports.menu-items'),
        icon: UtensilsCrossed,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Hourly Sales',
        href: route('reports.hourly-sales'),
        icon: Clock,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Guest Count',
        href: route('reports.guest-count'),
        icon: Users,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Order Status',
        href: route('reports.order-status'),
        icon: ListOrdered,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Print Audit',
        href: route('reports.print-audit'),
        icon: Printer,
        isActive: false,
        hasSubItems: false,
    },
    {
        title: 'Discount & Tax',
        href: route('reports.discount-tax'),
        icon: Tag,
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
            <NavMain :items="isAdmin ? mainNavItems : [mainNavItems[0]]" title="Main"  />
            <NavMain v-if="isAdmin" :items="reportNavItems" title="Reports" />
            <NavMain v-if="isAdmin" :items="configNavItems" title="Configuration" />
        </SidebarContent>
        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

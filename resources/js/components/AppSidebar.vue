<!-- Audit Fix (2026-04-06): expose package management page in admin sidebar. -->
<script setup lang="ts">
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
    ShieldCheck,
    Key,
    Lock,
    Bell,
    Building2,
    Accessibility,
    FileText,
    Activity,
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
        title: 'Access Control',
        href: route('roles.index'),
        icon: ShieldCheck,
        isActive: false,
        hasSubItems: true,
        items: [
            {
                title: 'Roles',
                href: route('roles.index'),
                icon: Lock,
            },
            {
                title: 'Permissions',
                href: route('permissions.index'),
                icon: Key,
            },
        ],
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
];

// Reports nav — routes not yet implemented on the backend
// const reportNavItems: NavItem[] = [ ... ];

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
            <NavMain :items="isAdmin ? mainNavItems : [mainNavItems[0]]" title="Main" />
            <!-- Reports section: enable once backend routes are ready -->
            <!-- <NavMain v-if="isAdmin" :items="reportNavItems" title="Reports" /> -->
        </SidebarContent>
        <SidebarFooter>
            <NavMain v-if="isAdmin" :items="configNavItems" title="Configuration" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

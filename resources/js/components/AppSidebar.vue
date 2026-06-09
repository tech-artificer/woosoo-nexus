<script setup lang="ts">
import { computed } from 'vue'
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    LayoutGrid,
    ListOrdered,
    UserCog,
    MonitorSmartphone,
    UtensilsCrossed,
    Package,
    Bell,
    Building2,
    Accessibility,
    FileText,
    Activity,
    ShieldCheck,
    Lock,
    Key,
    TrendingUp,
    BarChart2,
    Clock,
    Users,
    Printer,
    Tag,
    Monitor,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const page = usePage()
const user = computed(() => (page.props.auth as any)?.user)
const isAdmin = computed(() => Boolean(user.value?.is_admin))

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: route('dashboard'),
        icon: LayoutDashboard,
    },
    {
        title: 'Orders',
        href: route('orders.index'),
        icon: ListOrdered,
    },
    {
        title: 'POS',
        href: route('pos.index'),
        icon: Monitor,
    },
    {
        title: 'Menus',
        href: route('menus'),
        icon: UtensilsCrossed,
    },
    {
        title: 'Packages',
        href: route('package-configs.index'),
        icon: Package,
    },
    {
        title: 'Tablet Categories',
        href: route('tablet-categories.index'),
        icon: LayoutGrid,
    },
    {
        title: 'Devices',
        href: route('devices.index'),
        icon: MonitorSmartphone,
    },
    {
        title: 'User Management',
        href: route('users.index'),
        icon: UserCog,
    },
    {
        title: 'Service Requests',
        href: route('service-requests.index'),
        icon: Bell,
    },
];

// All report routes exist under /reports prefix
const analyticsNavItems: NavItem[] = [
    {
        title: 'Reports',
        href: route('reports.index'),
        icon: TrendingUp,
        hasSubItems: true,
        items: [
            { title: 'Overview',       href: route('reports.index'),        icon: TrendingUp },
            { title: 'Daily Sales',    href: route('reports.daily-sales'),  icon: BarChart2 },
            { title: 'Hourly Sales',   href: route('reports.hourly-sales'), icon: Clock },
            { title: 'Guest Count',    href: route('reports.guest-count'),  icon: Users },
            { title: 'Menu Items',     href: route('reports.menu-items'),   icon: UtensilsCrossed },
            { title: 'Order Status',   href: route('reports.order-status'), icon: ListOrdered },
            { title: 'Print Audit',    href: route('reports.print-audit'),  icon: Printer },
            { title: 'Discount & Tax', href: route('reports.discount-tax'), icon: Tag },
        ],
    },
];

const configNavItems: NavItem[] = [
    {
        title: 'Branches',
        href: route('branches.index'),
        icon: Building2,
    },
    {
        title: 'Access Control',
        href: route('roles.index'),
        icon: ShieldCheck,
        hasSubItems: true,
        items: [
            { title: 'Roles',        href: route('roles.index'),        icon: Lock },
            { title: 'Permissions',  href: route('permissions.index'),  icon: Key },
        ],
    },
    {
        title: 'Accessibility',
        href: route('accessibility.index'),
        icon: Accessibility,
    },
    {
        title: 'Event Logs',
        href: route('event-logs.index'),
        icon: FileText,
    },
    {
        title: 'Reverb Service',
        href: route('reverb.index'),
        icon: Activity,
    },
    {
        title: 'Monitoring',
        href: route('monitoring.index'),
        icon: Monitor,
    },
];
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
        class="border-none"
    >
        <!-- WOOSOO STEP 1: removed glassmorphism card wrapper, now flat logo row -->
        <SidebarHeader class="px-3 pt-3">
            <div class="pb-2 border-b border-white/8">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child size="lg" class="hover:bg-white/8">
                            <Link :href="route('dashboard')">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </div>
        </SidebarHeader>

        <SidebarContent class="px-2 pb-2 pt-3">
            <NavMain :items="isAdmin ? mainNavItems : [mainNavItems[0]]" title="Main" />
            <NavMain v-if="isAdmin" :items="analyticsNavItems" title="Analytics" />
        </SidebarContent>

        <SidebarFooter class="px-2 pb-3 pt-2">
            <NavMain v-if="isAdmin" :items="configNavItems" title="Configuration" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

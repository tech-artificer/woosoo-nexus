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

const currentPath = computed(() => normalizePath(page.url))

function normalizePath(value: string) {
    try {
        return new URL(value, 'http://localhost').pathname.replace(/\/+$/, '') || '/'
    } catch {
        return value.split('?')[0].replace(/\/+$/, '') || '/'
    }
}

function isActiveRoute(href?: string) {
    if (!href) {
        return false
    }

    const targetPath = normalizePath(href)
    return currentPath.value === targetPath || currentPath.value.startsWith(`${targetPath}/`)
}

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: route('dashboard'),
        icon: LayoutDashboard,
        isActive: isActiveRoute(route('dashboard')),
        hasSubItems: false,
    },
    {
        title: 'Orders',
        href: route('orders.index'),
        icon: ListOrdered,
        isActive: isActiveRoute(route('orders.index')),
        hasSubItems: false,
    },
    {
        title: 'Menus',
        href: route('menus'),
        icon: UtensilsCrossed,
        isActive: isActiveRoute(route('menus')),
        hasSubItems: false,
    },
    {
        title: 'Packages',
        href: route('packages.index'),
        icon: Package,
        isActive: isActiveRoute(route('packages.index')),
        hasSubItems: false,
    },
    {
        title: 'User Management',
        href: route('users.index'),
        icon: UserCog,
        isActive: isActiveRoute(route('users.index')),
        hasSubItems: false,
    },
    {
        title: 'Devices',
        href: route('devices.index'),
        icon: MonitorSmartphone,
        isActive: isActiveRoute(route('devices.index')),
        hasSubItems: false,
    },
    {
        title: 'Service Requests',
        href: route('service-requests.index'),
        icon: Bell,
        isActive: isActiveRoute(route('service-requests.index')),
        hasSubItems: false,
    },
];

// All report routes exist under /reports prefix
const analyticsNavItems: NavItem[] = [
    {
        title: 'Reports',
        href: route('reports.index'),
        icon: TrendingUp,
        isActive: isActiveRoute(route('reports.index')),
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
        isActive: isActiveRoute(route('branches.index')),
        hasSubItems: false,
    },
    {
        title: 'Access Control',
        href: route('roles.index'),
        icon: ShieldCheck,
        isActive: isActiveRoute(route('roles.index')),
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
        isActive: isActiveRoute(route('accessibility.index')),
        hasSubItems: false,
    },
    {
        title: 'Event Logs',
        href: route('event-logs.index'),
        icon: FileText,
        isActive: isActiveRoute(route('event-logs.index')),
        hasSubItems: false,
    },
    {
        title: 'Reverb Service',
        href: route('reverb.index'),
        icon: Activity,
        isActive: isActiveRoute(route('reverb.index')),
        hasSubItems: false,
    },
    {
        title: 'Monitoring',
        href: route('monitoring.index'),
        icon: Monitor,
        isActive: isActiveRoute(route('monitoring.index')),
        hasSubItems: false,
    },
];
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
        class="border-none"
    >
        <SidebarHeader class="px-3 pt-3">
            <div class="overflow-hidden rounded-[30px] border border-white/10 bg-[linear-gradient(180deg,rgba(255,255,255,0.09),rgba(255,255,255,0.04))] p-3 text-white shadow-[0_26px_65px_-42px_rgba(0,0,0,0.6)] backdrop-blur-xl">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child size="lg" class="rounded-[22px] bg-white/8 hover:bg-white/12 data-[active=true]:bg-white data-[active=true]:text-woosoo-dark-gray">
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

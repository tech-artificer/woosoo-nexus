import type { BreadcrumbItemType } from '@/types';

export interface ShellNavItem {
    key: string;
    label: string;
    routeName: string;
    icon: string;
    badge?: 'orders' | 'devices';
    dim?: boolean;
}

export interface ShellNavSection {
    key: string;
    label: string;
    items: ShellNavItem[];
    footer?: boolean;
}

export const NAV_SECTIONS: ShellNavSection[] = [
    {
        key: 'main',
        label: 'Main',
        items: [
            { key: 'dashboard',         label: 'Dashboard',        routeName: 'dashboard',               icon: 'dashboard' },
            { key: 'orders',            label: 'Orders',           routeName: 'orders.index',             icon: 'orders',           badge: 'orders' },
            { key: 'pos',               label: 'POS',              routeName: 'pos.index',                icon: 'pos' },
            { key: 'menus',             label: 'Menus',            routeName: 'menus',                    icon: 'menus' },
            { key: 'packages',          label: 'Packages',         routeName: 'packages.index',           icon: 'packages' },
            { key: 'tablet-categories', label: 'Tablet Categories',routeName: 'tablet-categories.index',  icon: 'tablet-categories' },
            { key: 'devices',           label: 'Devices',          routeName: 'devices.index',            icon: 'devices',          badge: 'devices' },
            { key: 'user-management',   label: 'User Management',  routeName: 'users.index',              icon: 'user-management' },
            { key: 'service-requests',  label: 'Service Requests', routeName: 'service-requests.index',   icon: 'service-requests' },
        ],
    },
    {
        key: 'analytics',
        label: 'Analytics',
        items: [
            { key: 'reports', label: 'Reports', routeName: 'reports.index', icon: 'reports' },
        ],
    },
    {
        key: 'configuration',
        label: 'Configuration',
        footer: true,
        items: [
            { key: 'branches',      label: 'Branches',      routeName: 'branches.index',      icon: 'branches',      dim: true },
            { key: 'access-control',label: 'Access Control',routeName: 'roles.index',          icon: 'access-control' },
            { key: 'configuration', label: 'Configuration', routeName: 'configuration.index',  icon: 'configuration' },
        ],
    },
];

export const ROUTE_CRUMBS: Record<string, BreadcrumbItemType[]> = {
    dashboard:               [{ title: 'Dashboard',         href: '/dashboard' }],
    'orders.index':          [{ title: 'Orders',            href: '/orders' }],
    'pos.index':             [{ title: 'POS',               href: '/pos' }],
    menus:                   [{ title: 'Menus',             href: '/menus' }],
    'packages.index':        [{ title: 'Packages',          href: '/packages' }],
    'tablet-categories.index':[{ title: 'Tablet Categories', href: '/tablet-categories' }],
    'devices.index':         [{ title: 'Devices',           href: '/devices' }],
    'users.index':           [{ title: 'User Management',   href: '/users' }],
    'service-requests.index':[{ title: 'Service Requests',  href: '/service-requests' }],
    'reports.index':         [{ title: 'Reports',           href: '/reports' }],
    'branches.index':        [{ title: 'Branches',          href: '/branches' }],
    'roles.index':           [{ title: 'Access Control',    href: '/roles' }],
    'configuration.index':   [{ title: 'Configuration',     href: '/configuration' }],
};

/** Returns true when `href` is the active destination for `currentUrl`. */
export function matchesRoute(href: string, currentUrl: string): boolean {
    if (href === '/dashboard') {
        return currentUrl === '/dashboard' || currentUrl.startsWith('/dashboard?');
    }
    return currentUrl === href || currentUrl.startsWith(`${href}/`) || currentUrl.startsWith(`${href}?`);
}

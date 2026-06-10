<script setup lang="ts">
import { computed, inject, type Ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import AdminSidebarContent from '@/components/shell/AdminSidebarContent.vue';
import { matchesRoute } from '@/config/admin-shell';
import { type User } from '@/types/models';

const page = usePage();
const user = computed(() => page.props.auth?.user as User);
const navBadges = computed(
    () => (page.props.navBadges ?? { orders: 0, devices: 0 }) as Record<string, number>,
);
const currentUrl = computed(() => page.url);

const mobileOpen = inject<Ref<boolean>>('shellMobileOpen');
const isMobileOpen = computed(() => mobileOpen?.value ?? false);

function setMobileOpen(v: boolean) {
    if (mobileOpen) mobileOpen.value = v;
}

function close() {
    setMobileOpen(false);
}

function isActive(routeName: string): boolean {
    try {
        return matchesRoute(route(routeName), currentUrl.value);
    } catch {
        return false;
    }
}
</script>

<template>
    <!-- Desktop: fixed 224px sidebar -->
    <aside
        class="fixed inset-y-0 left-0 z-30 hidden w-56 flex-col bg-[var(--shell-bg)] md:flex"
        style="border-right: 1px solid var(--shell-border)"
    >
        <AdminSidebarContent :is-active="isActive" :nav-badges="navBadges" :user="user" />
    </aside>

    <!-- Mobile: Sheet drawer -->
    <Sheet :open="isMobileOpen" @update:open="setMobileOpen">
        <SheetContent
            side="left"
            class="w-56 bg-[var(--shell-bg)] p-0"
            style="border-right: 1px solid var(--shell-border)"
        >
            <AdminSidebarContent :is-active="isActive" :nav-badges="navBadges" :user="user" @nav="close" />
        </SheetContent>
    </Sheet>
</template>

<script setup lang="ts">
import { provide, ref } from 'vue';
import type { BreadcrumbItemType } from '@/types';
import AdminSidebar from '@/components/shell/AdminSidebar.vue';
import AdminTopbar from '@/components/shell/AdminTopbar.vue';

withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItemType[] }>(), {
    breadcrumbs: () => [],
});

const mobileOpen = ref(false);
provide('shellMobileOpen', mobileOpen);
</script>

<template>
    <div class="flex min-h-screen">
        <AdminSidebar />
        <div class="flex flex-1 flex-col overflow-hidden md:ml-56">
            <AdminTopbar :breadcrumbs="breadcrumbs" />
            <main id="main-content" class="flex-1 overflow-auto">
                <slot />
            </main>
        </div>
    </div>
</template>

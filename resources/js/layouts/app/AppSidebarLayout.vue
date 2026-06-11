<script setup lang="ts">
import AdminShell from '@/components/shell/AdminShell.vue';
import type { BreadcrumbItemType } from '@/types';

withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItemType[] }>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AdminShell :breadcrumbs="breadcrumbs">
        <slot />
    </AdminShell>
</template>

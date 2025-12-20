<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import AppContentLayout from '@/layouts/AppContentLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Toaster } from '@/components/ui/sonner'
import 'vue-sonner/style.css' 

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
  
});

</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <AppContentLayout>
            <slot />
        </AppContentLayout>
        <Toaster />
    </AppLayout>
</template>


<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import AppContentLayout from '@/layouts/AppContentLayout.vue';
import type { BreadcrumbItemType, AppPageProps } from '@/types';
import { Toaster } from '@/components/ui/sonner'
import 'vue-sonner/style.css'
import { watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage<AppPageProps & { flash?: { warning?: string | null } }>()

watch(
    () => page.props.flash?.warning,
    (message) => {
        if (message) toast.warning(message)
    },
    { immediate: true }
)
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <AppContentLayout>
            <slot />
        </AppContentLayout>
        <Toaster />
    </AppLayout>
</template>


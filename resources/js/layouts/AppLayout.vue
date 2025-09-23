<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import AppContentLayout from '@/layouts/AppContentLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Toaster } from '@/components/ui/sonner'
import 'vue-sonner/style.css' 
// import { watch } from 'vue';
// import { usePage } from '@inertiajs/vue3';


interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}
// 🟢 Safe Typing for Page Props
// interface PageProps {
//     flash?: {
//         success?: string;
//     };
// }

// const page = usePage();


withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
  
});

//  Watch for Flash Messages (Typed + Reactive)
// watch(() => page.props.flash, (message) => {
//     if (message) {
//         toast.success(message, { duration: 3000 });
//     }
// });

</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <AppContentLayout>
            <slot />
        </AppContentLayout>
        <Toaster />
    </AppLayout>
</template>


<style scoped>
.logout-flash {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #1e293b;
    color: #fff;
    padding: 12px 20px;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
</style>

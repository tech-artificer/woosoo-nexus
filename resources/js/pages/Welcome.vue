<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const props = defineProps<{ auth: { user?: any } }>();

onMounted(() => {
    if (props.auth?.user) {
        router.visit(route('dashboard'));
    } else {
        router.visit(route('login'));
    }
});
</script>

<template>
    <Head title="Woosoo Admin" />
    <div class="flex min-h-svh items-center justify-center bg-woosoo-dark-gray">
        <div class="flex flex-col items-center gap-4 text-white/60">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-woosoo-accent/10 border border-woosoo-accent/20">
                <AppLogoIcon class="h-10 w-10 object-contain" />
            </div>
            <p class="text-sm">Loading Woosoo Admin...</p>
        </div>
    </div>
</template>

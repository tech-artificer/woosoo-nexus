<script setup lang="ts">
import PageHeader from '@/components/Layout/PageHeader.vue';
import { computed, useSlots } from 'vue';

const slots = useSlots();
const hasHeader = computed(() => Boolean(slots.header));
</script>

<template>
    <div class="mx-auto flex w-full max-w-[1680px] flex-1 flex-col px-3 pb-6 pt-4 md:px-5 md:pb-8 md:pt-5">
        <div class="relative flex min-h-0 flex-1 flex-col gap-5">
            <PageHeader v-if="hasHeader">
                <slot name="header" />
            </PageHeader>
            <slot />
        </div>
    </div>
</template>

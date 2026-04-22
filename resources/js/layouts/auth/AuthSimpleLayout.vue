<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh bg-background">
        <div class="flex w-full flex-1 items-center justify-center p-4 sm:p-6 md:p-8">
            <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <slot />
            </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
interface Props {
    title: string;
    description?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="mb-8 space-y-1">
        <h2 class="text-2xl font-semibold tracking-tight text-foreground md:text-[1.75rem]">{{ title }}</h2>
        <p v-if="description" class="max-w-2xl text-sm leading-6 text-muted-foreground md:text-[15px]">
            {{ description }}
        </p>
    </div>
</template>

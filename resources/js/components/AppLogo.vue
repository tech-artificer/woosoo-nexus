<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex items-center gap-2">
        <div class="flex h-8 w-8 items-center justify-center rounded-md overflow-hidden shrink-0">
            <AppLogoIcon class="h-8 w-8 object-contain" />
        </div>
        <div class="ml-1 grid flex-1 text-left text-sm">
            <span class="mb-0.5 truncate leading-tight font-header font-bold">Woosoo Admin</span>
        </div>
    </div>
</template>

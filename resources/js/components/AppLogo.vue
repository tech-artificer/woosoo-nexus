<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex items-center gap-3">
        <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-2xl border border-white/10 bg-white/12 shadow-[inset_0_1px_0_rgba(255,255,255,0.22)] shrink-0">
            <AppLogoIcon class="h-8 w-8 object-contain" />
        </div>
        <div class="grid flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden">
            <span class="truncate font-header text-sm font-bold tracking-[0.04em] text-white">Woosoo Nexus</span>
            <span class="truncate text-[11px] text-white/65">Admin operations hub</span>
        </div>
    </div>
</template>

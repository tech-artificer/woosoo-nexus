<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';
import { Moon, Sun } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const currentTitle = computed(() => props.breadcrumbs.at(-1)?.title ?? 'Workspace')
const isDark = ref(false)

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark')
})

function toggleDark() {
    isDark.value = !isDark.value
    document.documentElement.classList.toggle('dark', isDark.value)
    localStorage.setItem('appearance', isDark.value ? 'dark' : 'light')
}
</script>

<template>
    <!-- WOOSOO STEP 1: replaced floating glassmorphism pill with flat flush topbar -->
    <header class="shrink-0 px-0">
        <div class="flex h-[52px] items-center justify-between gap-4 border-b border-border/60 bg-background px-5 dark:border-white/[0.06]">
            <div class="flex min-w-0 items-center gap-3">
                <SidebarTrigger class="size-8 rounded-md text-foreground hover:bg-muted focus-visible:ring-2 focus-visible:ring-woosoo-accent/40" />
                <div class="min-w-0">
                    <h2 class="truncate font-header text-lg font-semibold tracking-tight text-foreground">
                        {{ currentTitle }}
                    </h2>
                    <div v-if="breadcrumbs && breadcrumbs.length > 0" class="mt-0.5 overflow-hidden text-xs text-muted-foreground">
                        <Breadcrumbs :breadcrumbs="breadcrumbs" />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-1.5">
                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-woosoo-accent/40 focus-visible:outline-none"
                    aria-label="Toggle dark mode"
                    @click="toggleDark"
                >
                    <Sun v-if="isDark" class="size-4" />
                    <Moon v-else class="size-4" />
                </button>
                <div class="hidden items-center gap-2 text-xs font-medium text-muted-foreground md:flex">
                    <span class="inline-flex h-2 w-2 rounded-full bg-woosoo-green"></span>
                    Secure workspace
                </div>
            </div>
        </div>
    </header>
</template>

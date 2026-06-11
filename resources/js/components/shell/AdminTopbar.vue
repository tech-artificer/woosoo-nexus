<script setup lang="ts">
import { computed, inject, onMounted, ref, type Ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { useNexusTheme } from '@/composables/useNexusTheme';
import { useInitials } from '@/composables/useInitials';
import type { BreadcrumbItemType } from '@/types';
import { type User } from '@/types/models';
import { Bell, Building2, Menu, Moon, RefreshCw, Search, Sun } from 'lucide-vue-next';

const props = withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItemType[] }>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const user = computed(() => page.props.auth?.user as User);
const { getInitials } = useInitials();
const { setTheme } = useNexusTheme();
const mobileOpen = inject<Ref<boolean>>('shellMobileOpen');

const isDark = ref(false);
const isRefreshing = ref(false);

onMounted(() => {
    isDark.value =
        document.documentElement.getAttribute('data-theme') === 'dark' ||
        document.documentElement.classList.contains('dark');
});

function toggleTheme() {
    isDark.value = !isDark.value;
    setTheme(isDark.value ? 'dark' : 'light');
}

function refresh() {
    isRefreshing.value = true;
    router.reload({ onFinish: () => (isRefreshing.value = false) });
}

function openMobile() {
    if (mobileOpen) mobileOpen.value = true;
}

const currentTitle = computed(() => props.breadcrumbs.at(-1)?.title ?? 'Workspace');
const showAvatar = computed(() => Boolean(user.value?.avatar));
</script>

<template>
    <header
        class="sticky top-0 z-20 flex h-[var(--topbar-h)] shrink-0 items-center justify-between gap-3 border-b px-4"
        style="background: var(--topbar-bg); border-color: var(--topbar-border)"
    >
        <!-- Left: hamburger (mobile) + title/crumbs -->
        <div class="flex min-w-0 items-center gap-3">
            <!-- Mobile hamburger -->
            <button
                type="button"
                class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none md:hidden"
                aria-label="Open sidebar"
                @click="openMobile"
            >
                <Menu class="size-4" />
            </button>

            <div class="min-w-0">
                <h2 class="truncate font-header text-lg font-semibold tracking-tight text-foreground">
                    {{ currentTitle }}
                </h2>
                <div v-if="breadcrumbs.length > 1" class="mt-0.5 overflow-hidden text-xs text-muted-foreground">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </div>
            </div>
        </div>

        <!-- Right cluster: HQ → Search → theme → refresh → bell → avatar -->
        <div class="flex shrink-0 items-center gap-1">
            <!-- HQ -->
            <Link
                :href="route('monitoring.index')"
                class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none"
                aria-label="HQ Console"
            >
                <Building2 class="size-4" />
            </Link>

            <!-- Search -->
            <button
                type="button"
                class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none"
                aria-label="Search"
            >
                <Search class="size-4" />
            </button>

            <!-- Theme toggle -->
            <button
                type="button"
                class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none"
                aria-label="Toggle theme"
                @click="toggleTheme"
            >
                <Sun v-if="isDark" class="size-4" />
                <Moon v-else class="size-4" />
            </button>

            <!-- Refresh -->
            <button
                type="button"
                class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none"
                aria-label="Refresh page"
                :disabled="isRefreshing"
                @click="refresh"
            >
                <RefreshCw class="size-4" :class="{ 'animate-spin': isRefreshing }" />
            </button>

            <!-- Bell -->
            <button
                type="button"
                class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none"
                aria-label="Notifications"
            >
                <Bell class="size-4" />
            </button>

            <!-- Avatar -->
            <Link
                :href="route('profile.edit')"
                class="ml-1 focus-visible:outline-none"
                aria-label="Profile"
            >
                <Avatar class="size-8 overflow-hidden rounded-lg border border-border">
                    <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
                    <AvatarFallback class="rounded-lg bg-muted text-xs font-medium text-foreground">
                        {{ getInitials(user.name) }}
                    </AvatarFallback>
                </Avatar>
            </Link>
        </div>
    </header>
</template>

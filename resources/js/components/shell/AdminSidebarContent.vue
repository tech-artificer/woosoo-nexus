<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import UserInfo from '@/components/UserInfo.vue';
import NexusNavIcon from '@/components/shell/NexusNavIcon.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { NAV_SECTIONS } from '@/config/admin-shell';
import { type User } from '@/types/models';
import { ChevronsUpDown } from 'lucide-vue-next';

const props = defineProps<{
    isActive: (routeName: string) => boolean;
    navBadges: Record<string, number>;
    user: User;
}>();

const emit = defineEmits<{ nav: [] }>();

const isAdmin = computed(() => Boolean(props.user?.is_admin));

// Mirror the pre-migration AppSidebar role gating: non-admins see only the
// Dashboard entry of the Main section — no other Main items, no Analytics, and
// no Configuration footer. The server still enforces authorization on every
// route; this only hides links that would otherwise 403.
const mainSections = computed(() => {
    const sections = NAV_SECTIONS.filter((s) => !s.footer);
    if (isAdmin.value) {
        return sections;
    }
    return sections
        .filter((s) => s.key === 'main')
        .map((s) => ({ ...s, items: s.items.filter((i) => i.key === 'dashboard') }));
});

const footerSections = computed(() =>
    isAdmin.value ? NAV_SECTIONS.filter((s) => s.footer) : [],
);
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Logo -->
        <div class="flex h-14 shrink-0 items-center gap-3 px-4" style="border-bottom: 1px solid var(--shell-border)">
            <Link :href="route('dashboard')" class="flex items-center gap-3" @click="emit('nav')">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-white/10 bg-white/10">
                    <AppLogoIcon class="h-6 w-6" />
                </div>
                <div class="flex flex-col leading-none">
                    <span class="font-header text-[13px] font-bold tracking-[0.06em] text-white">WOOSOO</span>
                    <span class="font-mono text-[9px] font-medium tracking-[0.14em] text-[var(--shell-dim)]">NEXUS</span>
                </div>
            </Link>
        </div>

        <!-- Scrollable nav -->
        <nav class="flex-1 overflow-y-auto px-2 py-3">
            <div v-for="section in mainSections" :key="section.key" class="mb-4">
                <p class="mb-1 px-2 text-[10px] font-semibold uppercase tracking-widest text-[var(--shell-dim)]">
                    {{ section.label }}
                </p>
                <ul>
                    <li v-for="item in section.items" :key="item.key">
                        <Link
                            :href="route(item.routeName)"
                            class="group relative flex items-center gap-2.5 rounded-md px-2 py-1.5 text-[13px] transition-colors"
                            :class="[
                                isActive(item.routeName)
                                    ? 'text-[var(--shell-active)]'
                                    : item.dim
                                      ? 'text-[var(--shell-dim)] hover:bg-[var(--shell-hover)] hover:text-[var(--shell-fg)]'
                                      : 'text-[var(--shell-fg)] hover:bg-[var(--shell-hover)]',
                            ]"
                            @click="emit('nav')"
                        >
                            <!-- Active left rail -->
                            <span
                                v-if="isActive(item.routeName)"
                                class="absolute top-[6px] bottom-[6px] w-0.5 rounded-r bg-[var(--shell-active)]"
                                style="left: -10px"
                                aria-hidden="true"
                            />
                            <NexusNavIcon :icon="item.icon" class="shrink-0" />
                            <span class="flex-1 truncate">{{ item.label }}</span>
                            <span
                                v-if="item.badge && navBadges[item.badge] > 0"
                                class="ml-auto min-w-[18px] rounded-full bg-[var(--shell-active)] px-1 text-center text-[10px] font-semibold leading-[18px] text-[#1a1816]"
                            >
                                {{ navBadges[item.badge] }}
                            </span>
                        </Link>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Footer sections + user -->
        <div class="shrink-0 px-2 py-3" style="border-top: 1px solid var(--shell-border)">
            <div v-for="section in footerSections" :key="section.key" class="mb-3">
                <p class="mb-1 px-2 text-[10px] font-semibold uppercase tracking-widest text-[var(--shell-dim)]">
                    {{ section.label }}
                </p>
                <ul>
                    <li v-for="item in section.items" :key="item.key">
                        <Link
                            :href="route(item.routeName)"
                            class="group relative flex items-center gap-2.5 rounded-md px-2 py-1.5 text-[13px] transition-colors"
                            :class="[
                                isActive(item.routeName)
                                    ? 'text-[var(--shell-active)]'
                                    : item.dim
                                      ? 'text-[var(--shell-dim)] hover:bg-[var(--shell-hover)] hover:text-[var(--shell-fg)]'
                                      : 'text-[var(--shell-fg)] hover:bg-[var(--shell-hover)]',
                            ]"
                            @click="emit('nav')"
                        >
                            <span
                                v-if="isActive(item.routeName)"
                                class="absolute top-[6px] bottom-[6px] w-0.5 rounded-r bg-[var(--shell-active)]"
                                style="left: -10px"
                                aria-hidden="true"
                            />
                            <NexusNavIcon :icon="item.icon" class="shrink-0" />
                            <span class="flex-1 truncate">{{ item.label }}</span>
                        </Link>
                    </li>
                </ul>
            </div>

            <!-- User menu -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="mt-1 flex w-full items-center gap-2 rounded-xl border border-white/10 bg-white/6 px-2 py-1.5 text-white transition-colors hover:bg-white/10"
                    >
                        <UserInfo :user="user" />
                        <ChevronsUpDown class="ml-auto size-4 shrink-0 text-white/40" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-52 rounded-2xl border border-white/10 bg-[#17120f]/96 p-1 text-white shadow-lg backdrop-blur-xl"
                    side="top"
                    align="start"
                    :side-offset="4"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </div>
</template>

<script setup lang="ts">
import { 
    SidebarGroup, 
    SidebarGroupLabel, 
    SidebarMenu, 
    SidebarMenuButton, 
    SidebarMenuItem ,
    // SidebarMenuSub,
    // SidebarMenuSubItem,
    SidebarGroupContent
} from '@/components/ui/sidebar';

import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'

import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

defineProps<{
    title: string;
    items: NavItem[];
}>();

const page = usePage();

function isActive(href?: string) {
    if (!href) {
        return false
    }

    return page.url === href || page.url.startsWith(`${href}/`)
}
</script>

<template>
    <SidebarGroup class="px-1 py-1.5">
        <SidebarGroupLabel class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-white/60">{{ title }}</SidebarGroupLabel>
        <SidebarMenu >
            <SidebarMenuItem v-for="item in items" :key="item.title">

                <Collapsible v-if="item.hasSubItems"
                :default-open="isActive(item.href)"
             
                class="group/collapsible"
            >
                <SidebarGroup class="p-0">
                <SidebarGroupLabel
                    as-child
                    class="group/label px-0 text-sm cursor-pointer"
                    :is-active="isActive(item.href)" 
                    :key="item.title"
                >  
                    <CollapsibleTrigger class="flex min-h-10 w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-white/78 transition-colors hover:bg-white/10 hover:text-white data-[active=true]:bg-white data-[active=true]:text-woosoo-dark-gray group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0">
                    <component :is="item.icon" class="m-0 h-4 w-4 border-transparent" />
                    <span class="font-semibold group-data-[collapsible=icon]:hidden">{{ item.title }}</span>
                    <ChevronRight class="ml-auto transition-transform group-data-[collapsible=icon]:hidden group-data-[state=open]/collapsible:rotate-90" />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <CollapsibleContent class="py-2 group-data-[collapsible=icon]:hidden">
                    <SidebarGroupContent>
                    <SidebarMenu class="space-y-1 px-2">
                        <SidebarMenuItem v-for="childItem in item.items" :key="childItem.title">
                        <SidebarMenuButton as-child :is-active="isActive(childItem?.href)" :tooltip="childItem.title" class="text-white/72 data-[active=true]:text-woosoo-dark-gray">
                            <Link :is-active="isActive(childItem?.href)" :href="childItem.href ?? '#'">
                                <component :is="childItem.icon" class="m-0 h-4 w-4 border-transparent" />
                                <span class="font-semibold">{{ childItem.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                    </SidebarGroupContent>
                </CollapsibleContent>
                </SidebarGroup>
            </Collapsible> 
                
                <SidebarMenuButton v-else as-child :is-active="isActive(item.href)" :tooltip="item.title" class="text-white/78 data-[active=true]:text-woosoo-dark-gray">
                    <Link :href="item.href" :is-active="isActive(item.href)">
                        <component :is="item.icon" class="m-0 h-4 w-4 border-transparent"/>
                        <span class="font-semibold">{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>

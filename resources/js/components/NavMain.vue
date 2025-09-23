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
</script>

<template>
    <SidebarGroup class="px-2 py-1">
        <SidebarGroupLabel>{{ title }}</SidebarGroupLabel>
        <SidebarMenu >
            <SidebarMenuItem v-for="item in items" :key="item.title">

                <Collapsible v-if="item.hasSubItems"
                :default-open="page.url.match(item.href) ? true : false"
             
                class="group/collapsible"
            >
                <SidebarGroup class="p-0">
                <SidebarGroupLabel
                    as-child
                    class="group/label text-sm cursor-pointer"
                    :is-active="item.href?.match(page.url) ? true : false" 
                    :key="item.title"
                >  
                    <CollapsibleTrigger class="flex p-0 gap-2">
                    <component :is="item.icon" class="m-0 h-4 w-4 border-transparent text-woosoo-white" />
                    <span class="font-semibold text-woosoo-white">{{ item.title }}</span>
                    <ChevronRight class="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-90" />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <CollapsibleContent class="py-2 group-data-[collapsible=icon]:hidden">
                    <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="childItem in item.items" :key="childItem.title">
                        <SidebarMenuButton as-child :is-active="childItem?.href?.match(page.url) ? true : false" :tooltip="childItem.title">
                            <Link :is-active="childItem?.href?.match(page.url) ? true : false" :href="childItem.href ?? '#'">
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
                
                <SidebarMenuButton v-else as-child :is-active="item.href === page.url" :tooltip="item.title">
                    <Link :href="item.href" :is-active="item.href.match(page.url)">
                        <component :is="item.icon" class="m-0 h-4 w-4 border-transparent"/>
                        <span class="font-semibold">{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>

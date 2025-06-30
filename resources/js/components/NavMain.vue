<script setup lang="ts">
import { 
    SidebarGroup, 
    SidebarGroupLabel, 
    SidebarMenu, 
    SidebarMenuButton, 
    SidebarMenuItem ,
    SidebarMenuSub,
    SidebarMenuSubItem,
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
    items: NavItem[];
}>();

const page = usePage();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Platform</SidebarGroupLabel>
        <SidebarMenu >
            <SidebarMenuItem v-for="item in items" :key="item.title">

                <Collapsible v-if="item.items"
                default-close
                
                class="group/collapsible"
            >
                <SidebarGroup class="p-0">
                
                <SidebarGroupLabel
                    as-child
                    class="group/label text-sm text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                >
                    <CollapsibleTrigger class="flex p-0 gap-2">
                    <component :is="item.icon" class="m-0 h-4 w-4" />
                    {{ item.title }}
                    <ChevronRight class="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-90" />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <CollapsibleContent class="p-0 group-data-[collapsible=icon]:hidden">
                    <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="childItem in item.items" :key="childItem.title">
                        <SidebarMenuButton as-child :is-active="childItem.isActive" >
                            <a :href="childItem.href" class="active">{{ childItem.title }}</a>
                        </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                    </SidebarGroupContent>
                </CollapsibleContent>
                </SidebarGroup>

                
            </Collapsible> 
                
                <SidebarMenuButton v-else as-child :is-active="item.href === page.url" :tooltip="item.title">
                    <Link :href="item.href" class="hover:bg-woosoo-accent hover:text-woosoo-dark-gray">
                        <component :is="item.icon"/>
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>

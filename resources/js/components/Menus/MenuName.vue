<script setup lang="ts">
import  { Menu } from '@/types/models'
import { reactive } from 'vue';
import { getInitials } from '@/composables/useInitials';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
const props = defineProps<{
  menu: Menu
}>()

const attributes = reactive([props.menu.category, props.menu.group, props.menu.course])



</script>

<template>
    <div class="flex items-center gap-3">
        <!-- <img
            :src="menu.img_url"
            :alt="menu.name"
            class="w-10 h-10 object-cover rounded-full"
        /> -->
            <Avatar class="size-8 overflow-hidden rounded-full">
                <AvatarImage v-if="menu.img_url" :src="menu.img_url" :alt="menu.name" />
                <AvatarFallback class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white">
                    {{ getInitials(menu?.name) }}
                </AvatarFallback>
            </Avatar>
        <div class="flex flex-col">
            
            <h3 class="font-semibold capitalize">{{ menu.name }}</h3>
            <span class="text-xs capitalize text-muted-foreground">
            {{ attributes.filter(v => v && v.trim()).join(' | ').toLocaleLowerCase() }}
            </span>
            
        </div>
       
    </div>
</template>
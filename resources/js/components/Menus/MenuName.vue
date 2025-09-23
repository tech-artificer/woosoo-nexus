<script setup lang="ts">
import  { Menu } from '@/types/models'
import { reactive } from 'vue';

const props = defineProps<{
  menu: Menu
}>()

const attributes = reactive([props.menu.category, props.menu.group, props.menu.course])



</script>

<template>
    <div class="flex items-center gap-3">
        <img
            :src="menu.img_url"
            :alt="menu.name"
            class="w-10 h-10 object-cover rounded-full"
        />
        <div class="flex flex-col">
            <h3 class="font-semibold capitalize">{{ menu.name }}</h3>
            <span class="text-xs capitalize text-muted-foreground">
            {{ attributes.filter(v => v && v.trim()).join(' | ').toLocaleLowerCase() }}
            </span>
            
        </div>
       
    </div>
</template>
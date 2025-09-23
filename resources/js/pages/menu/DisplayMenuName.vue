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
            <h3 class="font capitalize">{{ menu.name }}</h3>
            <span class="text-xs capitalize text-muted-foreground">
            {{ attributes.filter(v => v && v.trim()).join(' | ').toLocaleLowerCase() }}
            </span>
            <!-- <ul class="flex flex-row gap-1 font-light">
                <li class="font-xs separator capitalize" v-for="attr in attributes" :key="attr">
                    <span v-if="attr">{{ attr .toLocaleLowerCase() }}</span>
                </li>
            </ul> -->
            <!-- <span>{{ menu.category }}</span>| {{ menu.group }} | {{ menu.course }}</span> -->
        </div>
        <!-- <ul class="flex flex-row">
            <li class="font-xs">{{ menu.kitchen_name }}</li>
            <li class="font-xs">{{ menu.receipt_name }}</li>
        </ul> -->
    </div>
</template>
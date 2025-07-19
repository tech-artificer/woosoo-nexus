<script setup lang="ts">
import  { Menu } from '@/types/models'

defineProps<{
  menu: Menu
}>()

</script>

<template>
    <div class="flex items-center gap-3">
        <img
            :src="menu.img_url"
            :alt="menu.name"
            class="w-10 h-10 object-cover rounded-full"
        />
        <span class="capitalize">{{ menu.name }}</span>
        <!-- <ul class="flex flex-row">
            <li class="font-xs">{{ menu.kitchen_name }}</li>
            <li class="font-xs">{{ menu.receipt_name }}</li>
        </ul> -->
    </div>
</template>
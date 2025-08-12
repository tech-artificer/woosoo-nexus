<script setup lang="ts">
import { onMounted } from 'vue'
import { Badge } from '@/components/ui/badge'


defineProps<{
    status?: string;
}>()

onMounted(() => {
   
})

</script>

<template>
<Badge v-if="status === 'confirmed'"  class="text-green-600 text-xs antialiased tracking-tighter font-light bg-green-100">{{ status }}</Badge>
<Badge v-if="status === 'voided'" class="text-red-600 text-xs antialiased tracking-tighter font-light bg-red-100">{{ status }}</Badge>
<Badge v-if="status === 'completed'" class="text-blue-600 text-xs antialiased tracking-tighter font-light bg-blue-100">{{ status }}</Badge>
</template>
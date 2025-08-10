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
<Badge v-if="status === 'confirmed'"  class="text-green-600 bg-green-200">{{ status }}</Badge>
<Badge v-if="status === 'voided'" class="text-red-600 bg-red-200">{{ status }}</Badge>
<Badge v-if="status === 'completed'" class="text-blue-600 bg-blue-200">{{ status }}</Badge>
</template>
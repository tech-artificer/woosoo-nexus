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
<Badge v-if="status === 'confirmed'"  class="text-green-200 bg-green-600">{{ status }}</Badge>
<Badge v-if="status === 'voided'" class="text-red-200 bg-red-600">{{ status }}</Badge>
<Badge v-if="status === 'completed'" class="text-blue-200 bg-blue-600">{{ status }}</Badge>
</template>
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
<Badge v-if="status === 'confirmed'"  class="bg-woosoo-green-500 text-woosoo-green">{{ status }}</Badge>
<Badge v-if="status === 'completed'" class="text-woosoo-blue" variant="secondary">{{ status }}</Badge>
</template>
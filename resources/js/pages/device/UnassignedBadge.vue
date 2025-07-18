<script setup lang="ts">
import { computed, defineProps } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Device } from '@/types/models'
import { MonitorOff} from 'lucide-vue-next'

const props = defineProps<{ row: Device }>();

const device = props.row;
console.log(device);
const hasTable = computed(() => {
    return device.table && device.table.name ? device.table.name : null;
});
</script>

<template>
    <!-- <Badge v-if="!hasTable" variant="destructive"><SquareX /></Badge> -->
    <div v-if="!hasTable" class="text-woosoo-red"><MonitorOff /></div>
    <div v-else class="">{{ hasTable }}</div>
</template>

<script setup lang="ts">
import { computed, defineProps } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Device } from '@/types/models'

const props = defineProps<{ row: Device }>();

const device = props.row;

const hasTable = computed(() => {
    return device.table && device.table.name ? device.table.name : null;
});
</script>

<template>
    <Badge v-if="!hasTable" class="">Unassigned</Badge>
    <span v-else variant="">{{ hasTable }}</span>
</template>

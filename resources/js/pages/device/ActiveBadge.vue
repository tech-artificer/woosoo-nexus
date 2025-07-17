<script setup lang="ts">
import { computed, defineProps } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Device } from '@/types/models'

const props = defineProps<{ row: Device }>();

const device = props.row;

const isActive = computed(() => {
    // return device.table && device.table.name ? device.table.name : null;
    return device.is_active ?? false;

});
</script>

<template>
    <Badge v-if="isActive" class="bg-woosoo-green text-woosoo-green-100">active</Badge>
    <Badge v-else variant="destructive">inactive</Badge>
</template>

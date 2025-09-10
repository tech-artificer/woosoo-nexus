<script setup lang="ts">
import { onMounted } from 'vue'
import { getBranchColumns } from './branch-columns';
import BranchTable from './components/BranchTable.vue';
import { Branch } from '@/types/models';

const columns = getBranchColumns();

const props = defineProps<{
    rows: Branch[], 
}>()

onMounted(() => {
    
})

</script>

<template>
    <BranchTable :rows="props.rows" :columns="columns" />
</template>
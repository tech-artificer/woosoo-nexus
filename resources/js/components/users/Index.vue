<script setup lang="ts">
// import { columns } from '@/components/users/components/columns'
import { User } from '@/types/models';
import DataTable from '@/components/users/components/DataTable.vue'
// import UserAdd from '@/components/users/UserAdd.vue'

defineProps<{
    columns: any[], 
    rows: User[],
}>()  

</script>

<template>
  <div class="hidden h-full flex-1 flex-col md:flex">
    <DataTable :data="rows" :columns="columns" />
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
// import BranchIndex from '@/pages/branch/Index.vue'
// import Profile from '@/pages/branch/Profile.vue'
import { usePage } from '@inertiajs/vue3';
// import { type Branch } from '@/types/models';

const page = usePage();

defineProps<{
    title?: string
    description?: string
}>()

onMounted(() => {

    if(  page.props.server){
        // fetch branches from api
    }else{
        // fetch branches from api
    }

});


</script>

<template>
    <div></div>
        <!-- <BranchIndex :title="title" :description="description" :branch="branches" /> -->
        <!-- <Profile :title="title" :description="description" :branch="branch" /> -->
</template>

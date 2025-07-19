<script setup lang="ts">

import { Check, X } from 'lucide-vue-next';

defineProps<{
    isTrue: boolean;
}>();

</script>

<template>
<Check v-if="isTrue" class="text-green-500" />
<X v-else class="text-red-500" />
</template>
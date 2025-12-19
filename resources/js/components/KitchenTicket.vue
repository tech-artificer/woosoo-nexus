<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import { computed } from 'vue';

const props = defineProps({
  itemData: {
    type: Object,
    required: true,
  },
});

const formattedTime = computed(() => {
  if (props.itemData.time_sent) {
    const parts = props.itemData.time_sent.split(':');
    return `${parts[0]}:${parts[1]}`;
  }
  return '';
});
</script>

<template>
  <div class="w-[250px] bg-white text-black font-mono text-xs leading-tight print:block print:relative print:left-0 print:top-0 print:w-[300px] print:m-0 print:p-2">
    <div class="text-xs leading-tight">
      <div v-for="(item, idx) in itemData" :key="item.id ?? idx">
        ({{ item.quantity }}) {{ item.name }}
      </div>
    </div>
    <div class="text-xs leading-snug mt-2">
      <p class="font-bold">** ITEM NOTES:</p>
      <p>{{ itemData.note || '(No special requests/notes)' }}</p>
    </div>
  </div>
</template>
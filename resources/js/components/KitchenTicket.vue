<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps({
  // The item data you provided in the prompt
  itemData: {
    type: Object,
    required: true
  }
});

// Format time to remove seconds (e.g., 16:37:58 -> 16:37)
const formattedTime = computed(() => {
  if (props.itemData.time_sent) {
    const parts = props.itemData.time_sent.split(':');
    return `${parts[0]}:${parts[1]}`;
  }
  return '';
});
</script>

<template>
  <div class="ticket-container print-only " >
   
    <!-- <p class="text-center font-bold text-lg border-y-2 border-black py-1 mb-2">
      * * * KITCHEN TICKET * * *
    </p>

    <div class="text-sm leading-tight mb-2">
      <p>ORDER: #{{ itemData.order_id }} (CHECK #{{ itemData.order_check_id }})</p>
      <p>TIME SENT: {{ formattedTime }}</p>
      <p class="mt-1">SEAT: {{ itemData.seat_number }} | GUEST: {{ itemData.guest_count }}</p>
      <p>EMPLOYEE: {{ itemData.employee_log_id }}</p>
    </div> -->


    <div  class="text-xs leading-tight">
    <div v-for="item in itemData">
       ({{ item.quantity }}) {{ item.name }}
    </div>
    </div>
    <div class="text-xs leading-snug">
      <p class="font-bold">** ITEM NOTES:</p>
      <p>{{ itemData.note || '(No special requests/notes)' }}</p>
    </div>

  </div>
</template>



<style scoped>
/* A fixed, narrow width simulates a thermal printer */
.ticket-container {
  width: 250px; 
  background: white;
  color: black;
  /* Use a font that looks like a printer font */
  font-family: 'Courier New', Courier, monospace; 
}

/* Print-specific styles to ensure minimal margins and clear text */
@media print {
  body > * {
    visibility: hidden; /* Hide everything else */
  }
  .print-only {
    visibility: visible;
    position: absolute;
    left: 0;
    top: 0;
    width: 300px;
    margin: 0; /* Remove browser margins */
    padding: 10px;
    page-break-after: always; /* Ensure a clean cut if multiple tickets */
  }
}
</style>
<script setup lang="ts">
defineProps<{
    request: string    
}>();

</script>

<template>
    <div class="flex gap-2">
        <img v-if="request === 'clean'" src="/images/ServiceRequests/water_full.svg" class="w-5 h-5 bg-green"/>
        <img v-else src="/images/ServiceRequests/water_full_fill.svg" class="w-5 h-5 bg-green"/>
        <img src="/images/ServiceRequests/credit_card.svg" class="w-5 h-5" />
        <img src="/images/ServiceRequests/concierge.svg" class="w-5 h-5" />
        <img v-if="request == 'clean'" src="/images/ServiceRequests/cleaning_services_fill.svg" class="w-5 h-5" />
        <img v-else src="/images/ServiceRequests/cleaning_services.svg" class="w-5 h-5 bg-green"/>
    </div>
</template>
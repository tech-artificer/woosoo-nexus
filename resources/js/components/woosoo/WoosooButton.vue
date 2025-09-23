<script setup lang="ts">
import { Button } from '@/components/ui/button'

defineProps<{
    text: string
    isSubmitting: boolean
}>();

</script>

<template>
<Button  :disabled="isSubmitting" class="px-6 py-3 rounded cursor-pointer border-1 border-woosoo-accent bg-woosoo-primary-light hover:bg-woosoo-primary-dark hover:text-woosoo-white transition">
   {{ text }}
</Button>
</template>
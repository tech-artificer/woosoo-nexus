<script setup lang="ts">
import { CheckCircle2 } from 'lucide-vue-next'
</script>

<template>
  <div class="kds-empty" role="status" aria-live="polite">
    <CheckCircle2 aria-hidden="true" />
    <strong>Queue clear</strong>
    <span>No tickets match this view.</span>
  </div>
</template>

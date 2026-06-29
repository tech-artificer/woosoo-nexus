<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <!-- WOOSOO STEP 5: subtle header surface tint -->
  <thead
    data-slot="table-header"
    :class="cn('[&_tr]:border-b bg-black/[0.03] dark:bg-white/[0.03]', props.class)"
  >
    <slot />
  </thead>
</template>

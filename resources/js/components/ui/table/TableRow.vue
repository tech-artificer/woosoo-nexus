<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <!-- WOOSOO STEP 5: row hover matches card surface treatment -->
  <tr
    data-slot="table-row"
    :class="cn('hover:bg-black/[0.025] dark:hover:bg-white/[0.025] data-[state=selected]:bg-muted border-b transition-colors', props.class)"
  >
    <slot />
  </tr>
</template>

<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <div
    data-slot="sheet-header"
    :class="cn('flex flex-col gap-1.5 p-4 sm:px-6 sm:pt-6 sm:pb-4', props.class)"
  >
    <slot />
  </div>
</template>

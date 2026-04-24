<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"]; id?: string }>()
</script>

<template>
  <h2
    data-slot="sheet-title"
    :id="props.id"
    :class="cn('text-foreground font-semibold', props.class)"
  >
    <slot />
  </h2>
</template>

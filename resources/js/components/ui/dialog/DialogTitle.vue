<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"]; id?: string }>()
</script>

<template>
  <h2
    data-slot="dialog-title"
    :id="props.id"
    :class="cn('text-lg leading-none font-semibold', props.class)"
  >
    <slot />
  </h2>
</template>

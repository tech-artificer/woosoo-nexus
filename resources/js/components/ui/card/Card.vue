<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="card"
    :class="
      cn(
        'bg-card/88 text-card-foreground flex flex-col gap-6 rounded-[26px] border border-white/65 py-6 shadow-[0_28px_60px_-38px_rgba(37,37,37,0.38)] backdrop-blur-xl dark:border-white/10 dark:bg-white/[0.04]',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>

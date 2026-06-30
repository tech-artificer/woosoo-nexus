<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <!-- WOOSOO STEP 3: border-white/65 → border-black/8 (visible in light mode); softer shadow; lighter blur -->
  <div
    data-slot="card"
    :class="
      cn(
        'bg-card/88 text-card-foreground flex flex-col gap-6 rounded-[26px] border border-black/8 py-6 shadow-[0_24px_55px_-36px_rgba(37,37,37,0.32)] backdrop-blur-sm dark:border-white/10 dark:bg-white/[0.04] dark:shadow-[0_24px_55px_-36px_rgba(0,0,0,0.38)]',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>

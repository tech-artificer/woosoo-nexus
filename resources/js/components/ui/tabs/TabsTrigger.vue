<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { TabsTrigger, type TabsTriggerProps, useForwardProps } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<TabsTriggerProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = reactiveOmit(props, 'class')

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <!-- WOOSOO STEP 3: active state → white card with border (light) / glass (dark) -->
  <TabsTrigger
    data-slot="tabs-trigger"
    v-bind="forwardedProps"
    :class="cn(
      'inline-flex h-[calc(100%-2px)] min-w-16 items-center justify-center gap-1.5 rounded-md border border-transparent px-3 py-1 text-sm font-medium whitespace-nowrap text-foreground/65 transition-[color,box-shadow] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-woosoo-accent/40 disabled:pointer-events-none disabled:opacity-50 data-[state=active]:border-black/8 data-[state=active]:bg-white data-[state=active]:text-foreground data-[state=active]:shadow-sm dark:text-muted-foreground dark:data-[state=active]:border-white/12 dark:data-[state=active]:bg-white/[0.08] dark:data-[state=active]:text-foreground [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=size-])]:size-4',
      props.class,
    )"
  >
    <slot />
  </TabsTrigger>
</template>

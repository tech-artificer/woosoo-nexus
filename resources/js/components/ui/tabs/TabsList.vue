<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { TabsList, type TabsListProps } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<TabsListProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = reactiveOmit(props, 'class')
</script>

<template>
  <!-- WOOSOO STEP 3: warm tinted bg, border, h-10 — matches brand card/sidebar aesthetic -->
  <TabsList
    data-slot="tabs-list"
    v-bind="delegatedProps"
    :class="cn(
      'inline-flex h-10 w-fit self-start items-center justify-start rounded-lg border border-black/8 bg-black/[0.04] p-1 text-muted-foreground dark:border-white/10 dark:bg-white/[0.05]',
      props.class,
    )"
  >
    <slot />
  </TabsList>
</template>

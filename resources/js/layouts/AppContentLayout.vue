<script setup lang="ts">
import PageHeader from '@/components/Layout/PageHeader.vue'
import { computed, useSlots } from 'vue'

const slots = useSlots()
const hasHeader = computed(() => Boolean(slots.header))
</script>

<template>
   <!-- WOOSOO STEP 2: tightened radius/shadow/blur to brand spec; amber gradient subtler -->
   <div id="main-content" class="mx-auto flex w-full max-w-[1680px] flex-1 flex-col px-3 pb-6 md:px-5 md:pb-8 mt-4 md:mt-5">
      <section class="relative overflow-hidden rounded-[26px] border border-black/8 bg-white/78 p-4 shadow-[0_24px_60px_-44px_rgba(37,37,37,0.36)] backdrop-blur-sm dark:border-white/[0.08] dark:bg-white/[0.04] md:p-6">
         <div class="pointer-events-none absolute inset-x-0 top-0 h-20 bg-gradient-to-r from-[#f6b56d]/10 via-transparent to-transparent dark:from-[#f6b56d]/6" />
         <div class="relative flex min-h-0 flex-1 flex-col gap-5">
            <PageHeader v-if="hasHeader">
               <slot name="header" />
            </PageHeader>
            <slot />
         </div>
      </section>
   </div>
</template>

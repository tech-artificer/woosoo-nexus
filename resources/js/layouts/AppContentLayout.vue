<script setup lang="ts">
import PageHeader from '@/components/Layout/PageHeader.vue'
import { computed, useSlots } from 'vue'

const slots = useSlots()
const hasHeader = computed(() => Boolean(slots.header))
</script>

<template>
   <div id="main-content" class="mx-auto flex w-full max-w-[1600px] flex-1 flex-col px-3 pb-5 md:px-5 md:pb-6">
      <section class="relative overflow-hidden rounded-[30px] border border-white/65 bg-white/82 p-4 shadow-[0_32px_90px_-48px_rgba(37,37,37,0.5)] backdrop-blur-xl dark:border-white/12 dark:bg-white/[0.06] md:p-6">
         <div class="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-r from-[#f6b56d]/18 via-transparent to-[#b08047]/12 dark:from-[#f6b56d]/10 dark:to-transparent" />
         <div class="relative flex min-h-0 flex-1 flex-col gap-5">
            <PageHeader v-if="hasHeader">
               <slot name="header" />
            </PageHeader>
            <slot />
         </div>
      </section>
   </div>
</template>
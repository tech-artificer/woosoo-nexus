
<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '@/lib/utils'
import { buttonVariants } from '@/components/ui/button'

import type { ButtonVariants } from '@/components/ui/button'

const props = defineProps<{
  variant?: ButtonVariants['variant']
  size?: ButtonVariants['size']
  loading?: boolean
  disabled?: boolean
  icon?: boolean | object | Function
  type?: 'button' | 'submit' | 'reset'
  class?: string
}>()

const variant = computed(() => (props.variant ?? 'default') as ButtonVariants['variant'])
const size = computed(() => (props.size ?? 'default') as ButtonVariants['size'])
const btnType = computed(() => (props.type ?? 'button') as 'button' | 'submit' | 'reset')

const isIconSlot = computed(() => typeof props.icon === 'function' || typeof props.icon === 'object')

const buttonClass = computed(() =>
  cn(
    'button',
    buttonVariants({ variant: variant.value, size: size.value }),
    props.disabled ? 'opacity-50 pointer-events-none' : '',
    props.class
  )
)
</script>

<template>
  <button
    :type="btnType"
    :class="buttonClass"
    :disabled="disabled || loading"
    :aria-busy="loading ? 'true' : undefined"
    @click="$emit('click', $event)"
    style="background: #fff; height: 48px; min-width: 140px; border-radius: 999px; display: flex; align-items: center; padding: 0; box-shadow: 0 4px 24px 0 rgba(0,0,0,0.08); transition: background 0.3s;"
  >
    <span class="button__icon-accent">
      <span class="button__icon">
        <slot name="icon">
          <component v-if="isIconSlot" :is="icon" />
          <!-- Default icon: white lines, no background -->
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" width="24" height="24">
            <rect x="6" y="7" width="12" height="2" rx="1" fill="#fff"/>
            <rect x="6" y="11" width="12" height="2" rx="1" fill="#fff"/>
            <rect x="6" y="15" width="8" height="2" rx="1" fill="#fff"/>
          </svg>
        </slot>
      </span>
    </span>
    <span class="button__text">
      <slot />
    </span>
    <span v-if="loading" class="button__spinner" aria-hidden="true">
      <svg class="animate-spin mr-2 h-4 w-4 text-inherit" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
      </svg>
    </span>
  </button>
</template>

<style scoped>
  .button {
    text-decoration: none;
    line-height: 1;
    border-radius: 999px;
    overflow: hidden;
    position: relative;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    background: #fff;
    transition: background 0.3s;
    min-width: 140px;
    height: 48px;
    box-shadow: 0 4px 24px 0 rgba(0,0,0,0.08);
    padding: 0;
  }

  .button__icon-accent {
    background: var(--color-woosoo-accent, #F6B56D);
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    min-width: 48px;
    min-height: 48px;
    border-radius: 50%;
    transition: background 0.3s;
    overflow: hidden;
  }

  .button__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    color: #fff;
  }

  .button__text {
    display: flex;
    align-items: center;
    font-weight: 500;
    font-size: 1.1rem;
    color: #222;
    background: transparent;
    padding: 0 1.5rem 0 1.25rem;
    height: 48px;
    border-radius: 0 999px 999px 0;
    transition: color 0.3s, background 0.3s;
  }

  .button__spinner {
    display: flex;
    align-items: center;
    margin-left: 0.5rem;
  }

  .button:hover {
    background: var(--color-woosoo-accent, #F6B56D);
  }
  .button:hover .button__icon-accent {
    background: var(--color-woosoo-accent, #F6B56D);
  }
  .button:hover .button__text {
    color: #fff;
    font-weight: 600;
  }
  .button-decor {
    position: absolute;
    inset: 0;
    background-color: var(--clr);
    transform: translateX(-100%);
    transition: transform .3s;
    z-index: 0;
  }
  .button-content {
    display: flex;
    align-items: center;
    font-weight: 600;
    position: relative;
    overflow: hidden;
  }
  .button__icon {
    width: 48px;
    height: 40px;
    background-color: var(--clr);
    display: grid;
    place-items: center;
  }
  .button__text {
    display: inline-block;
    transition: color .2s;
    padding: 2px 1.5rem 2px;
    padding-left: .75rem;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    max-width: 150px;
  }
  .button:hover .button__text {
    color: #fff;
  }
  .button:hover .button-decor {
    transform: translate(0);
  }

  .button-decor {
    position: absolute;
    inset: 0;
    background-color: var(--clr, #00ad54);
    transform: translateX(-100%);
    transition: transform .3s;
    z-index: 0;
    pointer-events: none;
  }

  .button-content {
    display: flex;
    align-items: center;
    font-weight: 600;
    position: relative;
    overflow: hidden;
    z-index: 1;
  }

  .button__icon {
    width: 48px;
    height: 40px;
    background-color: var(--clr, #00ad54);
    display: grid;
    place-items: center;
    border-radius: 1.5rem 0 0 1.5rem;
    z-index: 2;
  }


  .button__text {
    display: inline-flex;
    align-items: center;
    transition: color .2s;
    padding: 2px 1.5rem 2px;
    padding-left: .75rem;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    max-width: 150px;
    gap: 0.5rem;
    z-index: 2;
  }

  .button__spinner {
    display: inline-flex;
    align-items: center;
    margin-right: 0.25rem;
    z-index: 2;
  }

  .button:hover .button__text {
    color: #fff;
  }
  .button:hover .button-decor {
    transform: translate(0);
  }
</style>

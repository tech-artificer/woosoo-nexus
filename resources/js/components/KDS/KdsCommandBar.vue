<script setup lang="ts">
import { ChefHat, Clock3, Flame, Wifi } from 'lucide-vue-next'

defineProps<{
  active: number
  newCount: number
  preparing: number
  ready: number
  overdue: number
  clock: string
  dateLabel: string
}>()
</script>

<template>
  <header class="kds-command">
    <div class="kds-brand" aria-label="Woosoo Kitchen Live">
      <div class="kds-brand-mark">
        <ChefHat aria-hidden="true" />
      </div>
      <div>
        <div class="kds-brand-name">WOOSOO</div>
        <div class="kds-brand-sub">Kitchen - Live</div>
      </div>
    </div>

    <div class="kds-metrics" aria-label="Kitchen queue counts">
      <div class="kds-metric">
        <strong>{{ active }}</strong>
        <span>Active</span>
      </div>
      <div class="kds-metric">
        <strong class="is-new">{{ newCount }}</strong>
        <span>New</span>
      </div>
      <div class="kds-metric">
        <strong class="is-preparing">{{ preparing }}</strong>
        <span>Preparing</span>
      </div>
      <div class="kds-metric">
        <strong class="is-ready">{{ ready }}</strong>
        <span>Ready</span>
      </div>
      <div class="kds-metric">
        <strong class="is-overdue">{{ overdue }}</strong>
        <span>Overdue</span>
      </div>
    </div>

    <div class="kds-status-clock">
      <span class="kds-online">
        <Wifi aria-hidden="true" />
        Online
      </span>
      <span v-if="overdue > 0" class="kds-rush">
        <Flame aria-hidden="true" />
        Rush
      </span>
      <div class="kds-clock">
        <Clock3 aria-hidden="true" />
        <div>
          <strong>{{ clock }}</strong>
          <span>{{ dateLabel }}</span>
        </div>
      </div>
    </div>
  </header>
</template>
